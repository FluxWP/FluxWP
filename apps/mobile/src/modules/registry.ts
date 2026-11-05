// Custom native screens (`native:<id>`, plan §6 step 2). WhatsApp, Tasks and Donation review views come
// in c2; until then those modules resolve to their manifest or web page.
import { lazy, type ComponentType } from 'react';

export const NATIVE: Record<string, ComponentType<{ params: Record<string, string> }>> = {
  dashboard: lazy(() => import('./dashboard')),
};
