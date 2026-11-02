// Medya kaynağı: WP admin'de çerez + _wpnonce'lu adres doğrudan kullanılır (aralıklı akış, önbellek).
// Uygulamada <img src> belirteç gönderemez; dosya istemciyle blob olarak çekilir ve nesne adresi verilir.
import { useEffect, useRef, useState } from 'react';
import { Pause, Play } from 'lucide-react';
import { useHost } from '../host';
import { flxc } from '@fluxwp/core';
import { duration } from './format';

const cache = new Map<string, Promise<string>>();

export function useMediaSrc(url: string | undefined, enabled = true): { src: string; error: boolean } {
  const host = useHost();
  const direct = !host.blobMedia && host.mode === 'wp';
  const [src, setSrc] = useState(direct || !url || url.startsWith('blob:') ? url ?? '' : '');
  const [error, setError] = useState(false);
  useEffect(() => {
    if (!url || direct || url.startsWith('blob:')) {
      setSrc(url ?? '');
      return;
    }
    if (!enabled) return;
    const key = host.scope + '|' + url;
    let p = cache.get(key);
    if (!p) {
      p = flxc(host.client).media(url).then((b) => URL.createObjectURL(b));
      p.catch(() => cache.delete(key));
      cache.set(key, p);
    }
    let live = true;
    p.then((s) => live && setSrc(s), () => live && setError(true));
    return () => {
      live = false;
    };
  }, [url, direct, enabled, host.scope, host.client]);
  return { src, error };
}

/** Aynı anda tek ses çalar. */
let playing: HTMLAudioElement | null = null;
const SPEEDS = [1, 1.5, 2];

export function AudioPlayer({ url, secs, ptt }: { url: string; secs: number; ptt: boolean }) {
  const [want, setWant] = useState(false);
  const { src } = useMediaSrc(url, want);
  const ref = useRef<HTMLAudioElement>(null);
  const [on, setOn] = useState(false);
  const [pos, setPos] = useState(0);
  const [len, setLen] = useState(secs);
  const [speed, setSpeed] = useState(1);

  useEffect(() => {
    const a = ref.current;
    if (a && want && src && a.paused) {
      if (playing && playing !== a) playing.pause();
      playing = a;
      void a.play().catch(() => setOn(false));
    }
  }, [src, want]);

  const toggle = () => {
    const a = ref.current;
    if (!a) return;
    if (!src) return setWant(true);
    if (a.paused) {
      if (playing && playing !== a) playing.pause();
      playing = a;
      void a.play();
    } else a.pause();
  };

  return (
    <div className={'wa-audio' + (ptt ? ' ptt' : '')}>
      <button type="button" className="wa-audio-btn" onClick={toggle} aria-label={on ? 'pause' : 'play'}>
        {on ? <Pause size={18} /> : <Play size={18} />}
      </button>
      <input
        type="range"
        min={0}
        max={len || 1}
        step={0.1}
        value={pos}
        onChange={(e) => {
          const v = Number(e.target.value);
          setPos(v);
          if (ref.current) ref.current.currentTime = v;
        }}
      />
      <span className="wa-audio-time">{duration(on || pos ? pos : len)}</span>
      <button
        type="button"
        className="wa-audio-speed"
        onClick={() => {
          const n = SPEEDS[(SPEEDS.indexOf(speed) + 1) % SPEEDS.length]!;
          setSpeed(n);
          if (ref.current) ref.current.playbackRate = n;
        }}
      >
        {speed}×
      </button>
      <audio
        ref={ref}
        src={src || undefined}
        preload="none"
        onPlay={() => setOn(true)}
        onPause={() => setOn(false)}
        onEnded={() => (setOn(false), setPos(0))}
        onTimeUpdate={(e) => setPos(e.currentTarget.currentTime)}
        onLoadedMetadata={(e) => {
          e.currentTarget.playbackRate = speed;
          if (Number.isFinite(e.currentTarget.duration)) setLen(e.currentTarget.duration);
        }}
      />
    </div>
  );
}
