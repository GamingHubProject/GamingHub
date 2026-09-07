/**
 * The one list of tags rich text may contain.
 *
 * This is the frontend half of a contract whose other half is
 * App\Profiles\RichText::ELEMENTS. The server sanitises every rich-text
 * write against that list, so anything the editor can emit but the server
 * does not allow is formatting a person applies and then watches vanish on
 * save. `RichTextAllowlistTest` reads this file and fails if the two
 * lists stop matching, because nothing else would notice until somebody's
 * bio quietly lost its headings.
 *
 * Adding a tag means: add it here, add it (with its allowed attributes) in
 * PHP, and enable whatever Tiptap extension emits it below.
 */
export const RICH_TEXT_ELEMENTS = [
  'p',
  'br',
  'strong',
  'em',
  's',
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

/** Headings are deliberately h2/h3 only: a bio sits inside a page that
 *  already has an h1, and letting people mint their own h1 breaks the
 *  document outline of every page their profile appears on. */
export const RICH_TEXT_HEADING_LEVELS = [2, 3] as const;
