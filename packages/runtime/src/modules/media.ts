// WordPress media upload (wp/v2/media), multipart, with byte progress when XHR is available.
import type { ApiClient } from '@fluxwp/core';

export interface MediaFile {
  uri: string;
  name: string;
  type: string;
  /** Web preview: the picked file. Native sends `{ uri, name, type }` through FormData. */
  blob?: Blob;
}

export interface WpMedia {
  id: number;
  source_url: string;
  alt_text?: string;
  mime_type?: string;
  title?: { rendered?: string };
  media_details?: { width?: number; height?: number; sizes?: { thumbnail?: { source_url: string }; medium?: { source_url: string } } };
}

export function mediaForm(file: MediaFile, alt?: string): FormData {
  const fd = new FormData();
  if (file.blob) fd.append('file', file.blob, file.name);
  else {
    // React Native FormData file descriptor.
    fd.append('file', { uri: file.uri, name: file.name, type: file.type } as unknown as Blob);
  }
  if (alt) fd.append('alt_text', alt);
  fd.append('title', file.name.replace(/\.[^.]+$/, ''));
  return fd;
}

export function mediaThumb(m: WpMedia): string {
  return m.media_details?.sizes?.thumbnail?.source_url || m.media_details?.sizes?.medium?.source_url || m.source_url;
}

/** Picked camera/gallery file. Distinct from the form-field `uploadMedia` in `upload.ts`. */
export async function uploadPickedMedia(opts: {
  api: ApiClient;
  file: MediaFile;
  alt?: string;
  onProgress?: (ratio: number) => void;
}): Promise<WpMedia> {
  const media = await opts.api<WpMedia>('wp/v2/media', {
    method: 'POST',
    formData: mediaForm(opts.file, opts.alt),
    onUploadProgress: opts.onProgress,
  });
  opts.onProgress?.(1);
  return media;
}
