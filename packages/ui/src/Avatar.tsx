export function Avatar({ src, name, size = 28, square }: { src?: string; name: string; size?: number; square?: boolean }) {
  const words = name.split(/\s+/).filter((w) => /^\p{L}/u.test(w));
  const initials = (words.length ? words.map((w) => w[0]).slice(0, 2).join('') : '#').toLocaleUpperCase('tr');
  const style = { width: size, height: size, borderRadius: square ? 8 : size };
  return src ? (
    <img className="avatar" src={src} alt="" style={style} />
  ) : (
    <span className="avatar avatar-fallback" style={{ ...style, fontSize: size * 0.4 }}>
      {initials}
    </span>
  );
}
