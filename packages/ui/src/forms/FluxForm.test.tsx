// @vitest-environment happy-dom
import { act } from 'react';
import { createRoot } from 'react-dom/client';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { FluxForm } from './FluxForm';
import type { FluxFormData, FluxFormSubmitInput } from './types';

(globalThis as { IS_REACT_ACT_ENVIRONMENT?: boolean }).IS_REACT_ACT_ENVIRONMENT = true;

const form: FluxFormData = {
  id: '5',
  title: 'Contact',
  formType: 'FORM',
  steps: 2,
  buttonLabel: 'Send now',
  successTitle: 'Thanks!',
  successMessage: 'We got it.',
  stepList: [
    { number: 1, title: 'You', help: '' },
    { number: 2, title: 'Message', help: '' },
  ],
  fields: [
    { id: 'name', type: 'text', label: 'Name', required: true, placeholder: '', options: [], step: 1, width: 50 },
    { id: 'pick', type: 'radio', label: 'Pick', required: false, placeholder: '', options: [{ value: 'a', label: 'A' }, { value: 'b', label: 'B' }], step: 1, width: 50 },
    { id: 'note', type: 'textarea', label: 'Note', required: true, placeholder: '', options: [], step: 2 },
  ],
};

let host: HTMLDivElement;
afterEach(() => host?.remove());

async function mount(onSubmit: (i: FluxFormSubmitInput) => Promise<{ submission?: { trackCode: string } | null; errors?: { message: string; field?: string | null }[] }>) {
  host = document.createElement('div');
  document.body.appendChild(host);
  const root = createRoot(host);
  await act(async () => root.render(<FluxForm form={form} onSubmit={onSubmit} />));
  return host;
}

const q = <T extends Element>(sel: string) => host.querySelector<T>(sel)!;
async function type(sel: string, v: string) {
  const el = q<HTMLInputElement | HTMLTextAreaElement>(sel);
  const proto = el instanceof HTMLTextAreaElement ? HTMLTextAreaElement.prototype : HTMLInputElement.prototype;
  await act(async () => {
    Object.getOwnPropertyDescriptor(proto, 'value')!.set!.call(el, v);
    el.dispatchEvent(new Event('input', { bubbles: true }));
  });
}
async function submit() {
  await act(async () => {
    q<HTMLFormElement>('form').dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
  });
}

describe('FluxForm', () => {
  it('walks the steps, checks fields, sends the answers and shows the result', async () => {
    const send = vi.fn(async () => ({ submission: { trackCode: 'ABC123DEF456' }, errors: [] }));
    await mount(send);
    expect(host.textContent).toContain('Step 1 of 2');
    expect(q('.flxff-cell').getAttribute('style')).toContain('calc(50%');
    await submit();
    expect(host.textContent).toContain('This field is required.');
    expect(q('#flxff-5-name').getAttribute('aria-invalid')).toBe('true');
    await type('#flxff-5-name', 'Ada');
    await act(async () => q<HTMLInputElement>('input[value="b"]').click());
    await submit();
    expect(host.textContent).toContain('Step 2 of 2');
    expect(q('button[type="submit"]').textContent).toBe('Send now');
    await type('#flxff-5-note', 'Hello');
    await submit();
    expect(send).toHaveBeenCalledWith({
      formId: '5',
      fields: [
        { fieldId: 'name', value: 'Ada' },
        { fieldId: 'pick', value: 'b' },
        { fieldId: 'note', value: 'Hello' },
      ],
      honeypot: '',
      captchaToken: null,
    });
    expect(host.textContent).toContain('Thanks!');
    expect(host.textContent).toContain('ABC123DEF456');
  });

  it('shows server errors on their field and goes back to its step', async () => {
    const send = vi.fn(async () => ({ submission: null, errors: [{ message: 'Name taken', field: 'fields.name' }] }));
    await mount(send);
    await type('#flxff-5-name', 'Ada');
    await submit();
    await type('#flxff-5-note', 'Hi');
    await submit();
    expect(host.textContent).toContain('Step 1 of 2');
    expect(host.textContent).toContain('Name taken');
  });

  it('says so when the request fails', async () => {
    await mount(async () => {
      throw new Error('network');
    });
    await type('#flxff-5-name', 'Ada');
    await submit();
    await type('#flxff-5-note', 'Hi');
    await submit();
    expect(host.textContent).toContain('The form could not be sent.');
  });
});
