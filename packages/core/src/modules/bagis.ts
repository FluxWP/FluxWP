// Flux Donate › Onay bekleyenler (flxb/v1/app/*). Sözleşme: docs/fluxapp/MODUL-API.md §3
import type { ApiClient } from '../api/client';
import type { Page } from './common';

export interface PendingDonation {
  id: number;
  order_id: number;
  title: string;
  amount?: number;
  amount_label: string;
  at?: string;
  date_label: string;
  waiting_label: string;
  donor: { name: string; phone: string; email: string };
  ref: { name: string } | null;
  lines: { title: string; quantity: number; amount_label: string }[];
  receipt: { url: string; type: 'image' | 'pdf'; name?: string; at_label: string; warn?: string; count?: number } | null;
  note: string;
  web_url: string;
}

export const bagisReview = (api: ApiClient) => ({
  pending: (page = 1) => api<Page<PendingDonation>>('flxb/v1/app/pending', { query: { page, per_page: 30 } }),
  approve: (id: number) => api<{ ok: boolean; status_label: string; certificates: number }>(`flxb/v1/app/donations/${id}/approve`, { method: 'POST' }),
  reject: (id: number, reason: string, notify = true) =>
    api<{ ok: boolean; status_label: string }>(`flxb/v1/app/donations/${id}/reject`, { method: 'POST', body: { reason, notify } }),
});
