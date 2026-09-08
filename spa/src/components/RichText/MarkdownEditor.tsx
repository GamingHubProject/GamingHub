import { useEffect, useRef, useState } from 'react';
import {
  MDXEditor,
  type MDXEditorMethods,
  headingsPlugin,
  listsPlugin,
  quotePlugin,
  linkPlugin,
  linkDialogPlugin,
  markdownShortcutPlugin,
  toolbarPlugin,
  BoldItalicUnderlineToggles,
  StrikeThroughSupSubToggles,
  CodeToggle,
  ListsToggle,
  BlockTypeSelect,
  CreateLink,
  Separator,
} from '@mdxeditor/editor';
import '@mdxeditor/editor/style.css';

/**
 * MDXEditor wrapping the same interface the previous EasyMDE component
 * exposed, so index.tsx's lazy import and MarkdownField's props are
 * unchanged.
 *
 * **Why MDXEditor over EasyMDE.**  EasyMDE fires toolbar actions via
 * onclick, which runs after mousedown has already moved focus off the
 * editor.  In CodeMirror 5 that collapses the anchor point, so by the
 * time _toggleLine reads getCursor('start')/getCursor('end') the
 * selection is gone — every list/heading/ordered-list operation targets
 * the whole document instead of the selected lines.  The bold rendering
 * lag is a separate CodeMirror 5 artefact (gutter redraws before inline
 * content on a programmatic replaceSelection).  Neither is fixable without
 * patching EasyMDE itself.
 *
 * MDXEditor fires all transforms on Lexical's own selection, which never
 * moves when you click inside the editor's toolbar, so every operation is
 * range-scoped to exactly what is selected.
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
  const editorRef = useRef<MDXEditorMethods>(null);
  // Kept in a ref so onChange never goes stale without teardown.
  const notify = useRef(onChange);
  notify.current = onChange;

  // Placeholder: MDXEditor has no native placeholder prop, so we track
  // whether the content is empty to show an overlay.
  const [isEmpty, setIsEmpty] = useState(() => !value.trim());

  // A value replaced from outside (form reset, record loading after first
  // paint) must reach the editor — but only when it really differs from
  // what the editor already holds, or every keystroke would rewrite the
  // document beneath the caret.
  useEffect(() => {
    const instance = editorRef.current;
    if (!instance) return;
    if (value !== instance.getMarkdown()) {
      instance.setMarkdown(value);
      setIsEmpty(!value.trim());
    }
  }, [value]);

  return (
    <div className="gh-mdxeditor-wrapper">
      {isEmpty && placeholder && (
        <span className="gh-mdxeditor-placeholder" aria-hidden>
          {placeholder}
        </span>
      )}
      <MDXEditor
        ref={editorRef}
        markdown={value}
        // initialMarkdownNormalize=true fires when MDXEditor normalises
        // the initial value on mount (e.g. bullet symbol differences).
        // Skipping those prevents a mount-time loop:
        //   setMarkdown → onChange(norm, true) → parent state → value →
        //   useEffect → setMarkdown → repeat.
        onChange={(markdown, isNormalize) => {
          if (!isNormalize) {
            setIsEmpty(!markdown.trim());
            notify.current(markdown);
          }
        }}
        className="gh-mdxeditor"
        contentEditableClassName="gh-mdxeditor-content"
        spellCheck={false}
        plugins={[
          headingsPlugin({ allowedHeadingLevels: [2, 3] }),
          listsPlugin(),
          quotePlugin(),
          linkPlugin(),
          linkDialogPlugin(),
          markdownShortcutPlugin(),
          toolbarPlugin({
            toolbarContents: () => (
              <>
                <BlockTypeSelect />
                <Separator />
                <BoldItalicUnderlineToggles options={['Bold', 'Italic']} />
                <StrikeThroughSupSubToggles options={['Strikethrough']} />
                <CodeToggle />
                <Separator />
                <ListsToggle />
                <Separator />
                <CreateLink />
              </>
            ),
          }),
        ]}
      />
    </div>
  );
}
