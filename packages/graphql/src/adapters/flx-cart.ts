// WooCommerce cart over GraphQL (flxCart* bridge in Flux GraphQL 0.3.0). One object for every client:
// the headless front end, FluxApp and the Flux Commerce / Flux Donate screens.
//
// Session: the server answers every cart operation with `cart.token` (also the Cart-Token response
// header). This adapter keeps it in `store` and sends it back as the `Cart-Token` request header, so the
// cart follows the shopper without cookies (headless, mobile). A same-site page on the WooCommerce
// cookie session can pass `nonce` (Store API nonce) instead; then no token is sent.
//
// Payment is not here: `cart.checkout.endpoint` is the Store API checkout (POST with the same
// Cart-Token). Card data never goes through GraphQL.
import type { GraphQLClient, GraphQLRequestOptions } from '../client';
import {
  FlxCart,
  FlxCartClear,
  FlxCartCouponApply,
  FlxCartCouponRemove,
  FlxCartCustomerUpdate,
  FlxCartItemAdd,
  FlxCartItemRemove,
  FlxCartItemUpdate,
  FlxCartShippingRateSelect,
} from '../generated/operations';
import type { FlxCartAddressInput, FlxCartFieldsFragment, FlxCartVariationInput } from '../generated/types';
import type { FlxUserError } from '../payload';

export type FlxCartData = FlxCartFieldsFragment;
export type FlxCartLine = FlxCartData['items'][number];

/** Where the cart token lives between requests (localStorage, SecureStore, memory …). */
export interface CartTokenStore {
  get(): string | null | Promise<string | null>;
  set(token: string | null): void | Promise<void>;
}

export interface FlxCartOptions {
  /** Token store; default: memory (one page load). */
  store?: CartTokenStore;
  /** Store API nonce for a same-site page on the WooCommerce cookie session (used when there is no token). */
  nonce?: () => string | null;
}

/** A cart mutation refused the input: `errors` are the payload errors (field → message with payloadErrors()). */
export class FlxCartInputError extends Error {
  readonly errors: readonly FlxUserError[];
  constructor(errors: readonly FlxUserError[]) {
    super(errors[0]?.message ?? 'The cart could not be changed.');
    this.name = 'FlxCartInputError';
    this.errors = errors;
  }
}

export function memoryCartTokenStore(initial: string | null = null): CartTokenStore {
  let t = initial;
  return { get: () => t, set: (v) => void (t = v) };
}

interface Payload {
  cart: FlxCartData | null;
  errors: readonly FlxUserError[];
}

export function flxCart(gql: GraphQLClient, o: FlxCartOptions = {}) {
  const store = o.store ?? memoryCartTokenStore();

  async function headers(): Promise<GraphQLRequestOptions> {
    const token = await store.get();
    if (token) return { headers: { 'Cart-Token': token } };
    const nonce = o.nonce?.() ?? null;
    return nonce ? { headers: { Nonce: nonce } } : {};
  }

  async function keep(cart: FlxCartData): Promise<FlxCartData> {
    if (cart.token && cart.token !== (await store.get())) await store.set(cart.token);
    return cart;
  }

  async function done(p: Payload | null): Promise<FlxCartData> {
    if (!p || p.errors.length || !p.cart) throw new FlxCartInputError(p?.errors ?? []);
    return keep(p.cart);
  }

  return {
    async get(): Promise<FlxCartData> {
      const r = await gql.request(FlxCart, {}, await headers());
      return keep(r.flxCart);
    },
    async add(productId: number, quantity = 1, variation?: FlxCartVariationInput[]): Promise<{ cart: FlxCartData; itemKey: string | null }> {
      const r = await gql.request(FlxCartItemAdd, { input: { productId, quantity, ...(variation ? { variation } : {}) } }, await headers());
      return { cart: await done(r.flxCartItemAdd), itemKey: r.flxCartItemAdd?.itemKey ?? null };
    },
    async update(key: string, quantity: number): Promise<FlxCartData> {
      return done((await gql.request(FlxCartItemUpdate, { input: { key, quantity } }, await headers())).flxCartItemUpdate);
    },
    async remove(key: string): Promise<FlxCartData> {
      return done((await gql.request(FlxCartItemRemove, { input: { key } }, await headers())).flxCartItemRemove);
    },
    async clear(): Promise<FlxCartData> {
      return done((await gql.request(FlxCartClear, { input: {} }, await headers())).flxCartClear);
    },
    async applyCoupon(code: string): Promise<FlxCartData> {
      return done((await gql.request(FlxCartCouponApply, { input: { code } }, await headers())).flxCartCouponApply);
    },
    async removeCoupon(code: string): Promise<FlxCartData> {
      return done((await gql.request(FlxCartCouponRemove, { input: { code } }, await headers())).flxCartCouponRemove);
    },
    async selectShippingRate(rateId: string, packageId?: number): Promise<FlxCartData> {
      const input = packageId === undefined ? { rateId } : { rateId, packageId };
      return done((await gql.request(FlxCartShippingRateSelect, { input }, await headers())).flxCartShippingRateSelect);
    },
    async updateCustomer(billingAddress?: FlxCartAddressInput, shippingAddress?: FlxCartAddressInput): Promise<FlxCartData> {
      const input = { ...(billingAddress ? { billingAddress } : {}), ...(shippingAddress ? { shippingAddress } : {}) };
      return done((await gql.request(FlxCartCustomerUpdate, { input }, await headers())).flxCartCustomerUpdate);
    },
    /** Headers for the Store API checkout POST (same session as the cart). */
    async checkoutHeaders(): Promise<Record<string, string>> {
      return (await headers()).headers ?? {};
    },
  };
}

export type FlxCartApi = ReturnType<typeof flxCart>;
