import { describe, expect, it } from 'vitest';
import { FLUX_FORM_LABELS } from './labels';
import { dateLimit, fieldKind, formSteps, initialValues, splitErrors, toAnswers, total, validateField, validateStep } from './logic';
import type { FluxFormData, FluxFormField } from './types';

const L = FLUX_FORM_LABELS.en;
const f = (p: Partial<FluxFormField> & { id: string; type: string }): FluxFormField => ({ label: p.id, required: false, placeholder: '', options: [], ...p });

const form: FluxFormData = {
  id: '7',
  title: 'T',
  formType: 'FORM',
  steps: 2,
  buttonLabel: 'Go',
  successTitle: '',
  successMessage: '',
  currency: 'USD',
  stepList: [
    { number: 1, title: 'One', help: '' },
    { number: 2, title: 'Two', help: '' },
  ],
  fields: [
    f({ id: 'name', type: 'text', required: true, step: 1, min: '2', max: '5' }),
    f({ id: 'mail', type: 'email', step: 1 }),
    f({ id: 'tags', type: 'checkbox', step: 2, defaultValue: 'a,zz', options: [{ value: 'a', label: 'A', price: 5 }, { value: 'b', label: 'B', price: 2.5 }], settings: { maxSelect: 1 } }),
    f({ id: 'on', type: 'switch', step: 2, defaultValue: 'yes' }),
    f({ id: 'h', type: 'heading', step: 2 }),
    f({ id: 'sig', type: 'esign', step: 2, required: true }),
  ],
};

describe('forms logic', () => {
  it('maps types to components', () => {
    expect(fieldKind('chlCheckBox')).toBe('choices');
    expect(fieldKind('conturylist')).toBe('select');
    expect(fieldKind('imgradio')).toBe('images');
    expect(fieldKind('esign')).toBe('unsupported');
    expect(fieldKind('nope')).toBe('unsupported');
  });

  it('starts with defaults, keeps only listed options', () => {
    expect(initialValues(form)).toEqual({ name: '', mail: '', tags: ['a'], on: '1' });
  });

  it('lists steps that have fields', () => {
    expect(formSteps(form)).toEqual([1, 2]);
    expect(formSteps({ ...form, fields: [f({ id: 'x', type: 'text' })] })).toEqual([1]);
  });

  it('checks required, length, email, number and date limits', () => {
    const name = form.fields[0]!;
    expect(validateField(name, '', L)).toBe(L.required);
    expect(validateField(name, 'a', L)).toBe('Enter at least 2 characters.');
    expect(validateField(name, 'abcdef', L)).toBe('Enter at most 5 characters.');
    expect(validateField(name, 'abc', L)).toBe('');
    expect(validateField(form.fields[1]!, 'x@', L)).toBe(L.email);
    expect(validateField(form.fields[1]!, '', L)).toBe('');
    const n = f({ id: 'n', type: 'number', min: '18', max: '99' });
    expect(validateField(n, '12', L)).toBe('Enter 18 or more.');
    expect(validateField(n, 'x', L)).toBe(L.number);
    expect(validateField(n, '40', L)).toBe('');
    const d = f({ id: 'd', type: 'date', min: '2026-01-01' });
    expect(validateField(d, '2025-12-31', L)).toBe('Choose 2026-01-01 or later.');
    expect(validateField(f({ id: 't', type: 'tel' }), '+90 530 121 39 33', L)).toBe('');
  });

  it('checks choice counts and skips elements without answers', () => {
    expect(validateField(form.fields[2]!, ['a', 'b'], L)).toBe('Choose at most 1.');
    expect(validateField(form.fields[5]!, '', L)).toBe('');
    const sw = f({ id: 's', type: 'switch', required: true });
    expect(validateField(sw, '0', L)).toBe(L.required);
  });

  it('validates one step at a time', () => {
    expect(validateStep(form, { name: '' }, L, 1)).toEqual({ name: L.required });
    expect(validateStep(form, { name: '' }, L, 2)).toEqual({});
  });

  it('builds the submit answers', () => {
    expect(toAnswers(form, { name: ' Ada ', mail: '', tags: ['a', 'b'], on: '0' })).toEqual([
      { fieldId: 'name', value: 'Ada' },
      { fieldId: 'tags', value: 'a@efb!b' },
      { fieldId: 'on', value: '0' },
    ]);
  });

  it('splits server errors', () => {
    expect(splitErrors([{ message: 'Bad', field: 'fields.name' }, { message: 'Captcha', field: null }, { message: 'Twice', field: 'fields.name' }])).toEqual({
      fields: { name: 'Bad' },
      form: ['Captcha'],
    });
  });

  it('sums option prices', () => {
    expect(total(form, { tags: ['a', 'b'] })).toBe(7.5);
  });

  it('reads 1 as today in date limits', () => {
    expect(dateLimit('1', new Date(2026, 10, 5))).toBe('2026-11-05');
    expect(dateLimit('soon')).toBe('');
  });
});
