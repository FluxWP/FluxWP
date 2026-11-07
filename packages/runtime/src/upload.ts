// Multipart upload of one file (wp/v2/media by default). Progress is 0..1 when the platform can report it.
import type { ApiClient } from '@fluxwp/core';

export async function uploadMedia(
  api: ApiClient,
  file: FormData,
  opts?: { path?: string; signal?: AbortSignal; onProgress?: (ratio: number) => void },
): Promise<{ id: number; url: string }> {
  const row = await api<{ id?: number; source_url?: string }>(opts?.path || 'wp/v2/media', {
    method: 'POST',
    formData: file,
    signal: opts?.signal,
    onUploadProgress: opts?.onProgress,
  });
  return { id: Number(row?.id ?? 0), url: row?.source_url ?? '' };
}
