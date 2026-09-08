import { Suspense, lazy } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { HEADING_FOLD, RICH_TEXT_ELEMENTS } from './schema';

/**
 * MDXEditor brings Lexical with it, and the overwhelming majority of
 * visits to a profile are people reading one — so the editor is its own
 * chunk, fetched only on an edit surface. The renderer below is what a
 * reader actually needs.
 */
const MarkdownEditor = lazy(() => import('./MarkdownEditor'));

export function MarkdownField(props: { value: string; onChange: (markdown: string) => void; placeholder?: string }) {
  return (
    <Suspense fallback={<p>Loading the editor…</p>}>
      <MarkdownEditor {...props} />
    </Suspense>
  );
}

/**
 * Renders stored Markdown.
 *
 * No `dangerouslySetInnerHTML` anywhere: react-markdown builds React
 * elements straight from the Markdown AST, so at no point does an HTML
 * string produced from somebody's bio get inserted into the page. Raw HTML
 * embedded in the source is dropped rather than parsed (`skipHtml`), and
 * link URLs go through react-markdown's own transform, which refuses
 * javascript: and friends.
 *
 * That is why storing Markdown rather than sanitised HTML is the safer of
 * the two: the value is inert text until something renders it, and the
 * thing that renders it here cannot execute markup at all.
 */
export function Markdown({ markdown, className }: { markdown: string | null; className?: string }) {
  if (!markdown) return null;

  return (
    <div className={className} style={{ overflowWrap: 'anywhere' }}>
      <ReactMarkdown
        remarkPlugins={[remarkGfm]}
        skipHtml
        // Anything outside the allowlist loses its tag and keeps its text,
        // rather than taking the text down with it.
        allowedElements={[...RICH_TEXT_ELEMENTS, ...Object.keys(HEADING_FOLD)]}
        unwrapDisallowed
        components={{
          ...HEADING_FOLD,
          // Rich text can link anywhere, so every link is treated as
          // hostile: a new context, and no referrer or window handle
          // handed over. The server-side renderer forces the same pair.
          a: ({ children, ...props }) => (
            <a {...props} rel="noreferrer noopener" target="_blank">
              {children}
            </a>
          ),
        }}
      >
        {markdown}
      </ReactMarkdown>
    </div>
  );
}
