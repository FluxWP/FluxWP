import Link from 'next/link';

export default function NotFound() {
  return (
    <article className="entry">
      <h1>404</h1>
      <p>
        <Link href="/">←</Link>
      </p>
    </article>
  );
}
