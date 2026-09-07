import { Suspense, lazy } from 'react';

/**
 * Tiptap pulls in a lot of ProseMirror, and the vast majority of visits to
 * a profile are people reading one. So the editor is split into its own
 * chunk and only fetched on an edit surface; RichTextContent below, which
 * is what a reader actually needs, is a few lines and ships in the main
 * bundle.
 */
const RichTextEditor = lazy(() => import('./RichTextEditor'));

export function RichTextField(props: { value: string; onChange: (html: string) => void; placeholder?: string }) {
  return (
    <Suspense fallback={<p>Loading the editor…</p>}>
      <RichTextEditor {...props} />
    </Suspense>
  );
}

/**
 * Renders stored rich text.
 *
 * dangerouslySetInnerHTML is correct here and nowhere else: this value was
 * sanitised on the way into the database by App\Profiles\RichText, which
 * is the single write path for every rich-text field. Sanitising on write
 * rather than on render is what makes rendering a plain insertion — but it
 * also means this component must never be pointed at a string that did not
 * come from that column.
 */
export function RichTextContent({ html, className }: { html: string | null; className?: string }) {
  if (!html) return null;

  return <div className={className} style={{ overflowWrap: 'anywhere' }} dangerouslySetInnerHTML={{ __html: html }} />;
}
