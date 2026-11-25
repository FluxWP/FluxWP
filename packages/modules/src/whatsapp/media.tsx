// Medya kaynağı: WP admin'de çerez + _wpnonce'lu adres doğrudan kullanılır (aralıklı akış, önbellek).
// Uygulamada <img src> belirteç gönderemez; dosya istemciyle blob olarak çekilir ve nesne adresi verilir.
import { useEffect, useRef, useState } from 'react';
import { I } from './icons';
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
    <div className={'flxc-au' + (ptt ? ' is-ptt' : '')}>
      <span className="flxc-au-mic"><I n="mic" /></span>
      <button type="button" className="flxc-au-play" onClick={toggle} aria-label={on ? 'pause' : 'play'}>
        <I n={on ? 'pause' : 'play'} />
      </button>
      <div className="flxc-au-mid">
        <input
          type="range"
          className="flxc-au-bar"
          min={0}
          max={1000}
          value={len ? Math.round((pos / len) * 1000) : 0}
          onChange={(e) => {
            const v = (Number(e.target.value) / 1000) * (len || 0);
            setPos(v);
            if (ref.current) ref.current.currentTime = v;
          }}
        />
        <span className="flxc-au-time">{duration(on || pos ? pos : len)}</span>
      </div>
      <button
        type="button"
        className="flxc-au-speed"
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
