import { useEffect, useRef, type ReactNode } from 'react';

type Props = {
  checked: boolean;
  onChange: (checked: boolean) => void;
  /** Etiket (düz metin ya da düğüm). */
  children?: ReactNode;
  /** Etiketin altındaki küçük açıklama. */
  note?: ReactNode;
  /** Anahtar görünümü (role="switch"). */
  switch?: boolean;
  /** Radyo (tek seçim); `name` ile gruplanır. */
  radio?: boolean;
  /** Kısmen seçili (yalnız onay kutusu). */
  indeterminate?: boolean;
  disabled?: boolean;
  /** Hata iletisi; verilirse kutu hata durumunda gösterilir. */
  error?: string;
  name?: string;
  value?: string;
  id?: string;
  /** Etiket yoksa ekran okuyucu için ad. */
  'aria-label'?: string;
  className?: string;
};

/**
 * Ortak onay kutusu / radyo / anahtar. Gerçek `<input>` korunur (klavye, form, ekran okuyucu);
 * görünüm @fluxwp/ui stilleri ve flx-brand `flx_check` ile aynıdır.
 */
export function Check({ checked, onChange, children, note, switch: asSwitch, radio, indeterminate, disabled, error, name, value, id, className, ...rest }: Props) {
  const ref = useRef<HTMLInputElement>(null);
  useEffect(() => {
    if (ref.current) ref.current.indeterminate = !!indeterminate && !checked && !radio;
  }, [indeterminate, checked, radio]);
  return (
    <>
      <label className={['chk', disabled ? 'is-disabled' : '', className ?? ''].filter(Boolean).join(' ')}>
        <input
          ref={ref}
          id={id}
          name={name}
          value={value}
          type={radio ? 'radio' : 'checkbox'}
          role={asSwitch && !radio ? 'switch' : undefined}
          checked={checked}
          disabled={disabled}
          aria-invalid={error ? true : undefined}
          aria-label={rest['aria-label']}
          onChange={(e) => onChange(e.target.checked)}
        />
        {children != null || note != null ? (
          <span className="chk__text">
            {children}
            {note != null ? <small>{note}</small> : null}
          </span>
        ) : null}
      </label>
      {error ? (
        <p className="chk__msg" role="alert">
          {error}
        </p>
      ) : null}
    </>
  );
}
