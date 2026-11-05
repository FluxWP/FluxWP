// Flux Social composer (flx-social/v1). Media is WordPress attachment ids.
export interface SocialAccount {
  id: number;
  name: string;
  provider: string;
  avatar?: string;
  color?: string;
}

export interface SocialDraft {
  content: string;
  accountIds: number[];
  mediaIds?: number[];
  /** Unix seconds. 0 = publish now (or send to approval, the server decides). */
  scheduledAt?: number;
}

export function socialPostBody(d: SocialDraft) {
  const when = d.scheduledAt ?? 0;
  return {
    title: d.content.trim().slice(0, 80),
    content: d.content.trim(),
    media: d.mediaIds ?? [],
    source: 'app' as const,
    scheduled_at: when,
    action: when > 0 ? 'schedule' : 'publish',
    targets: d.accountIds.map((account_id) => ({ account_id })),
  };
}
