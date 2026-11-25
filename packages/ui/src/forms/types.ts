// Shapes of a public Flux Forms form, as the flxForm GraphQL query returns it. Structural on purpose: any client
// (Apollo, urql, fetch, the @fluxwp/graphql codegen types) can pass its own result without a cast.

export interface FluxFormOption {
  value: string;
  label: string;
  /** Payment choices: price of the option in the form currency. */
  price?: number | null;
  /** Image choices: picture of the option. */
  image?: string;
}

export interface FluxFormStep {
  number: number;
  title: string;
  help: string;
}

export interface FluxFormField {
  id: string;
  /** Flux Forms element type, lowercase (text, email, select, chlcheckbox, conturylist, heading, ...). */
  type: string;
  label: string;
  required: boolean;
  placeholder: string;
  help?: string;
  /** Step the field is on (1 when the form has one step). */
  step?: number;
  /** Width in percent: 100, 75, 50, 33 or 25. */
  width?: number;
  /** Starting value. For an html element: its sanitized HTML. */
  defaultValue?: string;
  /** Text: minimum length. Number and slider: minimum value. Date: earliest date (YYYY-MM-DD, 1 = today). */
  min?: string;
  /** Text: maximum length. Number and slider: maximum value. Date: latest date. */
  max?: string;
  options: FluxFormOption[];
  /** Matrix rows. */
  rows?: FluxFormOption[];
  /** A short public list of the element's own settings (op_style, minSelect, maxSelect, file, on, off, href, ...). */
  settings?: unknown;
}

export interface FluxFormData {
  id: string;
  title: string;
  formType: string;
  steps: number;
  buttonLabel: string;
  successTitle: string;
  successMessage: string;
  currency?: string;
  stepList?: FluxFormStep[];
  fields: FluxFormField[];
}

/** One answer as flxFormSubmit takes it. */
export interface FluxFormAnswer {
  fieldId: string;
  value: string;
}

/** The flxFormSubmit input. */
export interface FluxFormSubmitInput {
  formId: string;
  fields: FluxFormAnswer[];
  honeypot: string;
  captchaToken?: string | null;
}

export interface FluxFormError {
  message: string;
  code?: string | null;
  /** "fields.<field id>" when the error belongs to a field. */
  field?: string | null;
}

/** The flxFormSubmit payload. */
export interface FluxFormSubmitResult {
  submission?: { id?: string | number; trackCode: string } | null;
  errors?: FluxFormError[] | null;
}

/** A field's value while it is being filled: one string, or a list for multiple choices. */
export type FluxFormValue = string | string[];
export type FluxFormValues = Record<string, FluxFormValue>;

export interface FluxFormLabels {
  submit: string;
  sending: string;
  next: string;
  back: string;
  stepOf: string;
  required: string;
  email: string;
  url: string;
  tel: string;
  number: string;
  min: string;
  max: string;
  minLength: string;
  maxLength: string;
  minDate: string;
  maxDate: string;
  minSelect: string;
  maxSelect: string;
  choose: string;
  yes: string;
  no: string;
  on: string;
  off: string;
  file: string;
  fileTooBig: string;
  uploading: string;
  uploadFailed: string;
  total: string;
  unsupported: string;
  failed: string;
  thanks: string;
  trackCode: string;
}
