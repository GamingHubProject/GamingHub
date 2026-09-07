/**
 * The elements a rendered rich-text document may contain.
 *
 * This is the frontend half of a contract whose other half is
 * App\Profiles\RichText::ELEMENTS, which is what a *server-side* render
 * allows. `RichTextTest` reads this file and fails when the two stop
 * matching, so the two renderers can never disagree about what a stored
 * document turns into.
 *
 * Rich text is stored as Markdown. Anything Markdown can produce that
 * isn't listed here (a table, an image) is simply not rendered — the
 * renderer drops the element and keeps its text, so no words are ever
 * lost to the allowlist.
 */
export const RICH_TEXT_ELEMENTS = [
  'p',
  'br',
  'strong',
  'em',
  // GFM strikethrough renders as <del>, not <s>.
  'del',
  'code',
  'pre',
  'blockquote',
  'h2',
  'h3',
  'ul',
  'ol',
  'li',
  'a',
] as const;

/**
 * Every heading level Markdown can express, folded onto the two the
 * allowlist keeps.
 *
 * Rich text sits inside a page that already owns the h1, so people minting
 * their own would break the outline of every page their bio appears on.
 * Dropping them instead would drop the words with them — '#' is the
 * obvious thing to type in Markdown. RichText::foldHeadings() does the
 * same on the server.
 */
export const HEADING_FOLD = { h1: 'h2', h4: 'h3', h5: 'h3', h6: 'h3' } as const;
