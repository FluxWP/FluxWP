import { describe, expect, it, vi } from 'vitest';
import { createClient } from '@fluxwp/core';
import { createGraphQLClient } from '../src/client';
import { flxCart, FlxCartInputError, memoryCartTokenStore, type FlxCartData } from '../src/adapters/flx-cart';

const json = (status: number, body: unknown) => new Response(JSON.stringify(body), { status, headers: { 'content-type': 'application/json' } });
const money = (amount: number) => ({ amount, currency: 'TRY', formatted: `${amount} TRY` });
const address = { firstName: '', lastName: '', company: '', address1: '', address2: '', city: '', state: '', postcode: '', country: '', email: null, phone: '' };
const cart = (token: string | null, itemsCount = 0): FlxCartData => ({
  token, nonce: null, itemsCount, items: [], coupons: [], fees: [], needsPayment: itemsCount > 0, needsShipping: false, hasCalculatedShipping: false,
  totals: { currency: 'TRY', itemsSubtotal: money(0), itemsTax: money(0), fees: money(0), discount: money(0), shipping: null, tax: money(0), total: money(0), taxLines: [] },
  shippingPackages: [], paymentMethods: ['bacs'], paymentRequirements: ['products'], notices: [], billingAddress: address, shippingAddress: address,
  checkout: { endpoint: 'https://x.org/wp-json/wc/store/v1/checkout', pageUrl: null, cartPageUrl: null },
});

function setup(...responses: Response[]) {
  const f = vi.fn<typeof fetch>();
  for (const r of responses) f.mockResolvedValueOnce(r);
  const api = createClient({ siteUrl: 'https://x.org', fetchImpl: f, getCredentials: async () => null });
  return { f, gql: createGraphQLClient({ api }) };
}
const header = (f: ReturnType<typeof vi.fn<typeof fetch>>, call: number, name: string) => new Headers(f.mock.calls[call]?.[1]?.headers).get(name);

describe('cart bridge client', () => {
  it('keeps the cart token from the answer and sends it back as Cart-Token', async () => {
    const { f, gql } = setup(
      json(200, { data: { flxCart: cart('tok1') } }),
      json(200, { data: { flxCartItemAdd: { cart: cart('tok1', 2), itemKey: 'abc', errors: [] } } }),
    );
    const store = memoryCartTokenStore();
    const c = flxCart(gql, { store });
    expect((await c.get()).itemsCount).toBe(0);
    expect(header(f, 0, 'Cart-Token')).toBeNull();
    expect(await store.get()).toBe('tok1');
    const r = await c.add(7, 2);
    expect(r.itemKey).toBe('abc');
    expect(header(f, 1, 'Cart-Token')).toBe('tok1');
    expect(await c.checkoutHeaders()).toEqual({ 'Cart-Token': 'tok1' });
  });

  it('reads with a persisted GET and writes with POST', async () => {
    const { f, gql } = setup(json(200, { data: { flxCart: cart(null) } }), json(200, { data: { flxCartClear: { cart: cart(null), errors: [] } } }));
    const c = flxCart(gql);
    await c.get();
    await c.clear();
    expect(f.mock.calls[0]?.[1]?.method ?? 'GET').toBe('GET');
    expect(f.mock.calls[1]?.[1]?.method).toBe('POST');
  });

  it('uses the Store API nonce when there is no token (same-site cookie session)', async () => {
    const { f, gql } = setup(json(200, { data: { flxCartItemRemove: { cart: cart(null), errors: [] } } }));
    await flxCart(gql, { nonce: () => 'n1' }).remove('k');
    expect(header(f, 0, 'Nonce')).toBe('n1');
    expect(header(f, 0, 'Cart-Token')).toBeNull();
  });

  it('throws FlxCartInputError with the payload errors', async () => {
    const { gql } = setup(json(200, { data: { flxCartCouponApply: { cart: null, errors: [{ code: 'INVALID_INPUT', message: 'Coupon "x" does not exist.', field: 'code' }] } } }));
    const e = await flxCart(gql).applyCoupon('x').catch((x: unknown) => x);
    expect(e).toBeInstanceOf(FlxCartInputError);
    expect((e as FlxCartInputError).errors[0]?.field).toBe('code');
  });
});
