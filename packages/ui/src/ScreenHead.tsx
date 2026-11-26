// A page inside a tabbed Screen sets the screen header (title, subtitle, buttons), as each classic page printed its
// own header. The screen calls useScreenHead() and passes the result to <Screen>; pages call usePageHead() and put
// their buttons in <PageActions>.
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

export interface PageHead {
  title?: string | undefined;
  sub?: string | undefined;
}

export interface HeadApi {
  set: (h: PageHead) => void;
  slot: HTMLElement | null;
}

const HeadContext = createContext<HeadApi | null>(null);

/** Screen side: the page's title and subtitle (or undefined), the ref for the action slot and the value for ScreenHeadProvider. */
export function useScreenHead() {
  const [head, setHead] = useState<PageHead>({});
  const [slot, setSlot] = useState<HTMLElement | null>(null);
  const set = useCallback((h: PageHead) => setHead((cur) => (cur.title === h.title && cur.sub === h.sub ? cur : h)), []);
  const api = useMemo<HeadApi>(() => ({ set, slot }), [set, slot]);
  return { title: head.title, sub: head.sub, setSlot, api };
}

/** Wraps the pages so usePageHead / PageActions reach the screen. */
export function ScreenHeadProvider({ value, children }: { value: HeadApi; children: ReactNode }) {
  return <HeadContext.Provider value={value}>{children}</HeadContext.Provider>;
}

/** Page side: sets the screen title and subtitle while the page is open. */
export function usePageHead(title: string | undefined, sub?: string) {
  const api = useContext(HeadContext);
  const set = api?.set;
  useEffect(() => {
    set?.({ title, sub });
  }, [set, title, sub]);
  useEffect(() => () => set?.({}), [set]);
}

/** Page side: buttons in the screen header. */
export function PageActions({ children }: { children: ReactNode }) {
  const slot = useContext(HeadContext)?.slot ?? null;
  return slot ? createPortal(children, slot) : null;
}
