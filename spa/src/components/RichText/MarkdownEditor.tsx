import { useState, useEffect, useRef } from 'react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { HEADING_FOLD, RICH_TEXT_ELEMENTS } from './schema';

/**
 * Plain-textarea markdown editor with a Write / Preview toggle.
 *
 * No library owns the selection, intercepts keyboard events, or manages
 * focus. The browser's native textarea handles all of that. Dark theme is
 * plain CSS on a textarea. Users already know **bold**, *italic*, - list
 * from Discord / Reddit / GitHub — no toolbar required.
 *
 * The Preview tab reuses the same react-markdown + remark-gfm pipeline
 * already used by the Markdown renderer in index.tsx, so what the editor
 * shows is exactly what readers see.
 */
export default function MarkdownEditor({
  value,
  onChange,
  placeholder,
}: {
  value: string;
  onChange: (markdown: string) => void;
  placeholder?: string;
}) {
  const [tab, setTab] = useState<'write' | 'preview'>('write');
  const textareaRef = useRef<HTMLTextAreaElement>(null);

  useEffect(() => {
    if (tab === 'write') textareaRef.current?.focus();
  }, [tab]);

  return (
    <div className="gh-rte">
      <div className="gh-rte-tabs" role="tablist">
        <button
          role="tab"
          aria-selected={tab === 'write'}
          className={'gh-rte-tab' + (tab === 'write' ? ' gh-rte-tab--active' : '')}
          onClick={() => setTab('write')}
          type="button"
        >
          Write
        </button>
        <button
          role="tab"
          aria-selected={tab === 'preview'}
          className={'gh-rte-tab' + (tab === 'preview' ? ' gh-rte-tab--active' : '')}
          onClick={() => setTab('preview')}
          type="button"
        >
          Preview
        </button>
      </div>

      {tab === 'write' ? (
        <div className="gh-rte-write">
          <textarea
            ref={textareaRef}
            className="gh-rte-textarea"
            value={value}
            onChange={(e) => onChange(e.target.value)}
            placeholder={placeholder}
            rows={6}
            spellCheck={false}
          />
          <p className="gh-rte-hints">
            <code>**bold**</code>
            {' · '}
            <code>*italic*</code>
            {' · '}
            <code>~~strike~~</code>
            {' · '}
            <code>## Heading</code>
            {' · '}
            <code>- list</code>
            {' · '}
            <code>[link](url)</code>
          </p>
        </div>
      ) : (
        <div className="gh-rte-preview">
          {value.trim() ? (
            <ReactMarkdown
              remarkPlugins={[remarkGfm]}
              skipHtml
              allowedElements={[...RICH_TEXT_ELEMENTS, ...Object.keys(HEADING_FOLD)]}
              unwrapDisallowed
              components={{
                ...HEADING_FOLD,
                a: ({ children, ...props }) => (
                  <a {...props} rel="noreferrer noopener" target="_blank">
                    {children}
                  </a>
                ),
              }}
            >
              {value}
            </ReactMarkdown>
          ) : (
            <p className="gh-rte-preview-empty">Nothing to preview.</p>
          )}
        </div>
      )}
    </div>
  );
}
