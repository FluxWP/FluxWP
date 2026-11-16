// Per-network post options of the composer (same set and rules as the classic screen's optDefs).
import type { FlxSocialOptionsInput } from '@fluxwp/graphql/types';
import type { Translate } from './i18n';
import type { Account } from './common';

export type OptKey = keyof FlxSocialOptionsInput;
export type OptDef = {
  type: 'text' | 'url' | 'area' | 'select' | 'check' | 'date' | 'tags';
  label: string;
  hint?: string;
  opts?: [string, string][];
  def?: boolean;
  req?: boolean;
  max?: number;
  when?: Partial<Record<OptKey, string[]>>;
};

/** Option keys come from the server as snake_case ("first_comment"); the GraphQL fields are camelCase. */
export const camelKey = (k: string): OptKey => k.replace(/_([a-z])/g, (_, c: string) => c.toUpperCase()) as OptKey;

export function optionDefs(a: Account, t: Translate): Partial<Record<OptKey, OptDef>> {
  const pv = a.provider;
  const titleLabel =
    ({ youtube: t('Video title'), pinterest: t('Pin title'), linkedin: t('Link title'), tiktok: t('Title (photo post)') } as Record<string, string>)[pv] ?? t('Title');
  return {
    title: { type: 'text', label: titleLabel, max: 100 },
    link: { type: 'url', label: pv === 'pinterest' ? t('Pin link') : t('Link (preview card)') },
    firstComment: { type: 'area', label: pv === 'x' ? t('Reply thread (first reply)') : t('First comment'), hint: t('Added as a comment right after publishing (e.g. a link or hashtags).') },
    privacy: { type: 'select', label: t('Visibility'), opts: [['public', t('Public')], ['unlisted', t('Unlisted')], ['private', t('Private')]] },
    tags: { type: 'tags', label: t('Video tags (comma separated)') },
    flxCategory: {
      type: 'select',
      label: t('Category'),
      opts: [['', t('Default')], ['29', t('Nonprofits & activism')], ['22', t('People & blogs')], ['27', t('Education')], ['19', t('Travel & events')], ['25', t('News & politics')], ['24', t('Entertainment')]],
    },
    playlist: { type: 'select', label: t('Playlist'), opts: [['', t('None')], ...a.playlists.map((p): [string, string] => [p.id, p.name])] },
    madeForKids: { type: 'check', label: t('Made for kids') },
    notify: { type: 'check', label: t('Notify subscribers'), def: true },
    shorts: { type: 'check', label: t('Shorts (vertical, under 60 s)') },
    aiGenerated: { type: 'check', label: t('AI-generated content') },
    igType: { type: 'select', label: t('Post type'), opts: [['feed', t('Post (feed)')], ['reel', t('Reels')], ['story', t('Story')]] },
    shareToFeed: { type: 'check', label: t('Show Reels in the feed too'), def: true, when: { igType: ['reel'] } },
    thumbUrl: { type: 'url', label: t('Cover image address (Reels)'), when: { igType: ['reel'] } },
    liVisibility: { type: 'select', label: t('Visibility'), opts: [['PUBLIC', t('Public')], ['CONNECTIONS', t('Connections only')]] },
    alt: { type: 'text', label: t('Image description (alt text)') },
    replySettings: {
      type: 'select',
      label: t('Who can reply'),
      opts: [['', t('Everyone')], ['following', t('People I follow')], ['mentionedUsers', t('People I mention')], ['verified', t('Verified accounts')]],
    },
    board: { type: 'select', label: t('Board'), opts: [['', t('Pick a board')], ...a.boards.map((b): [string, string] => [b.id, b.name])], req: true },
    tiktokPrivacy: {
      type: 'select',
      label: t('Who can see it'),
      opts: [['PUBLIC_TO_EVERYONE', t('Everyone')], ['MUTUAL_FOLLOW_FRIENDS', t('Friends')], ['FOLLOWER_OF_CREATOR', t('Followers')], ['SELF_ONLY', t('Only me')]],
    },
    disableComment: { type: 'check', label: t('Turn comments off') },
    disableDuet: { type: 'check', label: t('Duet off') },
    disableStitch: { type: 'check', label: t('Stitch off') },
    brandContent: { type: 'check', label: t('Paid partnership (branded content)') },
    brandOrganic: { type: 'check', label: t('Promoting my own brand') },
    autoMusic: { type: 'check', label: t('Automatic music for photos') },
    gbpTopic: { type: 'select', label: t('Post kind'), opts: [['STANDARD', t('Update')], ['EVENT', t('Event')], ['OFFER', t('Offer')]] },
    gbpCta: {
      type: 'select',
      label: t('Button'),
      opts: [['', t('None')], ['LEARN_MORE', t('Learn more')], ['BOOK', t('Book')], ['ORDER', t('Order')], ['SHOP', t('Shop')], ['SIGN_UP', t('Sign up')], ['CALL', t('Call')]],
    },
    gbpCtaUrl: { type: 'url', label: t('Button link') },
    eventTitle: { type: 'text', label: t('Event / offer title'), when: { gbpTopic: ['EVENT', 'OFFER'] } },
    eventStart: { type: 'date', label: t('Start'), when: { gbpTopic: ['EVENT', 'OFFER'] } },
    eventEnd: { type: 'date', label: t('End'), when: { gbpTopic: ['EVENT', 'OFFER'] } },
    offerCode: { type: 'text', label: t('Coupon code'), when: { gbpTopic: ['OFFER'] } },
  };
}

/** Option values of a target as the form edits them (null and empty are dropped). */
export function cleanOptions(o: Record<string, unknown> | null | undefined): FlxSocialOptionsInput {
  const out: Record<string, unknown> = {};
  for (const [k, v] of Object.entries(o ?? {})) {
    if (k === '__typename' || v === null || v === undefined || v === '') continue;
    if (Array.isArray(v) && !v.length) continue;
    out[k] = v;
  }
  return out as FlxSocialOptionsInput;
}

/** Character count the way the networks count it (links count as 23 on X). */
export function textLength(text: string, provider: string): number {
  if (provider !== 'x') return [...text].length;
  return [...text.replace(/https?:\/\/\S+/g, 'x'.repeat(23))].length;
}
