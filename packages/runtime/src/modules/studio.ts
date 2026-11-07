// Flux Studio generate + gallery (flx-studio/v1).
export type StudioKind = 'image' | 'video';
export type StudioState = 'queued' | 'running' | 'done' | 'failed' | 'cancelled';

export interface StudioItem {
  id: number;
  kind: StudioKind;
  state: StudioState;
  prompt: string;
  url?: string;
  thumb?: string;
  error?: string;
  job?: string;
}

export interface StudioModel {
  id: string;
  label: string;
  kind: StudioKind;
}

export function studioGenerateBody(input: { prompt: string; kind: StudioKind; model?: string; n?: number }) {
  return {
    prompt: input.prompt.trim(),
    kind: input.kind,
    ...(input.model ? { model: input.model } : {}),
    n: input.kind === 'video' ? 1 : Math.max(1, Math.min(4, input.n ?? 1)),
  };
}

export function studioBusy(items: StudioItem[]): string[] {
  return items.filter((i) => i.state === 'queued' || i.state === 'running').map((i) => i.job).filter((j): j is string => !!j);
}
