import { describe, expect, it } from 'vitest';
import { canDeleteForEveryone, chatMatches, isDeletedMessage, type FlxcChat, type FlxcMessage } from './flxc';

const m = (p: Partial<FlxcMessage>): FlxcMessage => ({
  id: 1, chat: 1, wa: 'W', dir: 'out', type: 'text', body: 'x', status: 'sent', error: '', user: '', userId: 5, source: 'agent', ts: 0, rev: 0,
  react: {}, edited: false, sender: '', ...p,
});

describe('flx-chat 1.38 message deletion', () => {
  it('treats the deleted flag and the legacy type as deleted', () => {
    expect(isDeletedMessage(m({ deleted: true }))).toBe(true);
    expect(isDeletedMessage(m({ type: 'deleted' }))).toBe(true);
    expect(isDeletedMessage(m({}))).toBe(false);
  });

  it('allows delete for everyone only inside the window, for the sender or a manager', () => {
    const now = 1_000;
    const own = m({ revokeUntil: 2_000 });
    expect(canDeleteForEveryone(own, { id: 5, manage: false }, now)).toBe(true);
    expect(canDeleteForEveryone(own, { id: 6, manage: false }, now)).toBe(false);
    expect(canDeleteForEveryone(own, { id: 6, manage: true }, now)).toBe(true);
    expect(canDeleteForEveryone(own, { id: 5, manage: false }, 3_000)).toBe(false);
    expect(canDeleteForEveryone(m({}), { id: 5, manage: true }, now)).toBe(false);
    expect(canDeleteForEveryone(m({ dir: 'note', revokeUntil: 2_000 }), { id: 5, manage: true }, now)).toBe(false);
    expect(canDeleteForEveryone(m({ deleted: true, revokeUntil: 2_000 }), { id: 5, manage: true }, now)).toBe(false);
  });
});

describe('flx-chat 1.37 channels', () => {
  const chat = { status: 'open', labels: [], unread: 0, assigned: 0, ai: 0 } as unknown as FlxcChat;
  it('filters by channel and treats a missing channel as WhatsApp', () => {
    expect(chatMatches({ ...chat, channel: 'web' }, 'all', 1, '', '', 'web')).toBe(true);
    expect(chatMatches({ ...chat, channel: 'web' }, 'all', 1, '', '', 'whatsapp')).toBe(false);
    expect(chatMatches(chat, 'all', 1, '', '', 'whatsapp')).toBe(true);
    expect(chatMatches(chat, 'all', 1, '', '', '')).toBe(true);
  });
});
