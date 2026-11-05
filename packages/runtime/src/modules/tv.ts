// Flux TV Remote (flxtv/v1). The site accepts commands only from the bridge's network.
export interface TvApp {
  id: string;
  name: string;
}

export interface TvState {
  online: boolean;
  /** False when this device is not on the bridge's Wi-Fi. Commands must not be sent. */
  same_wifi: boolean;
  name: string;
  volume: number;
  muted: boolean;
  app: string;
  apps: TvApp[];
  message?: string;
}

export type TvCommand =
  | { cmd: 'key'; key: string }
  | { cmd: 'text'; text: string }
  | { cmd: 'app'; app: string }
  | { cmd: 'power' }
  | { cmd: 'volume'; delta: number }
  | { cmd: 'mute' };

export function tvCommandAllowed(state: Pick<TvState, 'same_wifi' | 'online'> | null | undefined): boolean {
  return !!state && state.same_wifi && state.online;
}

/** Keys the on-screen remote sends. Same set the bridge understands. */
export const TV_KEYS = {
  up: 'DPAD_UP',
  down: 'DPAD_DOWN',
  left: 'DPAD_LEFT',
  right: 'DPAD_RIGHT',
  ok: 'DPAD_CENTER',
  back: 'BACK',
  home: 'HOME',
  play: 'MEDIA_PLAY_PAUSE',
} as const;
