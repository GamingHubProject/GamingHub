import { useLocation } from 'react-router-dom';
import { Profile } from './Profile';
import { WebTreePage } from './WebTreePage';

/**
 * Everything the static routes didn't match: a Web Tree page, or the
 * pretty door to a profile.
 *
 * /@{name} is served from here rather than as its own route because
 * react-router only makes a whole path segment dynamic — "@:name" is a
 * literal segment to it, not a pattern. Adding ":name" as a real route
 * instead would rank above this splat and swallow every single-segment
 * Web Tree page ("/rules", "/about") on the way past.
 *
 * The '@' is what makes that safe, and is exactly why the profile URL has
 * one: Web Tree slugs come from Str::slug, which never produces an '@', so
 * the two namespaces cannot collide no matter what an admin names a page.
 */
export function CatchAll() {
  const { pathname } = useLocation();
  const handle = pathname.startsWith('/@') ? decodeURIComponent(pathname.slice(2)) : null;

  if (handle) return <Profile handle={handle} />;

  return <WebTreePage />;
}
