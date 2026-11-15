/** Validation errors that a mutation returns in its payload (`errors: [FlxUserError!]!`). */
export interface FlxUserError {
  code: string;
  message: string;
  field?: string | null;
}

/** Field → message map of a mutation payload's errors, for forms. */
export function payloadErrors(payload: { errors?: ReadonlyArray<FlxUserError> | null } | null | undefined): Record<string, string> {
  const out: Record<string, string> = {};
  for (const e of payload?.errors ?? []) {
    const key = e.field ?? '';
    if (!(key in out)) out[key] = e.message;
  }
  return out;
}
