// Server-side speech to text: record with MediaRecorder, convert to 16 kHz mono WAV (every provider accepts it).
export const canRecord = (): boolean => typeof window !== 'undefined' && !!window.MediaRecorder && !!navigator.mediaDevices?.getUserMedia;

export async function toWav(blob: Blob): Promise<Blob> {
  const w = window as unknown as { AudioContext?: typeof AudioContext; webkitAudioContext?: typeof AudioContext; OfflineAudioContext?: typeof OfflineAudioContext; webkitOfflineAudioContext?: typeof OfflineAudioContext };
  const AC = w.AudioContext ?? w.webkitAudioContext;
  const OAC = w.OfflineAudioContext ?? w.webkitOfflineAudioContext;
  if (!AC || !OAC) return blob;
  try {
    const ctx = new AC();
    const ab = await ctx.decodeAudioData(await blob.arrayBuffer());
    void ctx.close();
    const oc = new OAC(1, Math.max(1, Math.ceil(ab.duration * 16000)), 16000);
    const src = oc.createBufferSource();
    src.buffer = ab;
    src.connect(oc.destination);
    src.start();
    const d = (await oc.startRendering()).getChannelData(0);
    const v = new DataView(new ArrayBuffer(44 + d.length * 2));
    const s = (o: number, str: string) => {
      for (let k = 0; k < str.length; k++) v.setUint8(o + k, str.charCodeAt(k));
    };
    s(0, 'RIFF');
    v.setUint32(4, 36 + d.length * 2, true);
    s(8, 'WAVEfmt ');
    v.setUint32(16, 16, true);
    v.setUint16(20, 1, true);
    v.setUint16(22, 1, true);
    v.setUint32(24, 16000, true);
    v.setUint32(28, 32000, true);
    v.setUint16(32, 2, true);
    v.setUint16(34, 16, true);
    s(36, 'data');
    v.setUint32(40, d.length * 2, true);
    for (let i = 0; i < d.length; i++) {
      const x = Math.max(-1, Math.min(1, d[i]!));
      v.setInt16(44 + i * 2, x < 0 ? x * 0x8000 : x * 0x7fff, true);
    }
    return new Blob([v], { type: 'audio/wav' });
  } catch {
    return blob;
  }
}

export const pickMime = (): string => ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4'].find((m) => MediaRecorder.isTypeSupported?.(m)) ?? '';
