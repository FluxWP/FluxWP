import { useEffect, useState } from 'react';

/** Desktop list pattern: table + master-detail from this width up (plan §14). */
export const TABLE_MIN = 1100;

export function useMinWidth(px: number): boolean {
  const [ok, setOk] = useState(() => typeof matchMedia === 'function' && matchMedia(`(min-width: ${px}px)`).matches);
  useEffect(() => {
    const mq = matchMedia(`(min-width: ${px}px)`);
    const on = () => setOk(mq.matches);
    on();
    mq.addEventListener('change', on);
    return () => mq.removeEventListener('change', on);
  }, [px]);
  return ok;
}
