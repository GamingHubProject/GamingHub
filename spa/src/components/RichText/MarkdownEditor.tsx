import { useEffect, useRef } from 'react';
import EasyMDE from 'easymde';
import 'easymde/dist/easymde.min.css';

/**
 * EasyMDE, in its own module and never imported directly — index.tsx loads
 * it lazily, so somebody reading a profile downloads none of it.
 *
 * **Why a Markdown source editor rather than another WYSIWYG.** The
 * v0.1.024.00 editor kept an HTML document model, where toggling a list is
 * a block operation: switching it off reflowed text that had already been
 * written, which is not what anybody means by turning a list off. Every
 * WYSIWYG in this space (Milkdown on ProseMirror, MDXEditor on Lexical)
 * shares that model and would behave the same way. In a source editor a
 * list is a line prefix, so EasyMDE's toolbar edits the selected lines and
 * nothing else — the behaviour asked for, by construction rather than by
 * configuration.
 *
 * EasyMDE is the maintained successor to SimpleMDE, which has had no
 * release since 2022.
 *
 * This component is a mount/unmount wrapper and nothing more: no editing
 * behaviour is reimplemented here, which is the point of taking a library.
 */
/**
 * EasyMDE's own actions, one per toolbar button. Every one of these is the
 * library's — nothing here reimplements an editing command, which is the
 * whole reason for taking a library rather than writing an editor. They
 * are listed explicitly because EasyMDE only resolves its built-ins for
 * *string* toolbar entries, and these entries are objects so they can
 * carry a text label.
 */
const ACTIONS: Record<string, (editor: EasyMDE) => void> = {
  'bold': EasyMDE.toggleBold,
  'italic': EasyMDE.toggleItalic,
  'strikethrough': EasyMDE.toggleStrikethrough,
  'heading': EasyMDE.toggleHeadingSmaller,
  'quote': EasyMDE.toggleBlockquote,
  'code': EasyMDE.toggleCodeBlock,
  'unordered-list': EasyMDE.toggleUnorderedList,
  'ordered-list': EasyMDE.toggleOrderedList,
  'link': EasyMDE.drawLink,
  'preview': EasyMDE.togglePreview,
};

function button(name: string, text: string, title: string) {
  return {
    name,
    action: ACTIONS[name],
    text,
    title,
    // Required by the toolbar type and normally the Font Awesome glyph.
    // These buttons carry a text label instead, so it is only a styling
    // hook.
    className: 'easymde-label',
    // The preview button has to stay clickable while the preview is up,
    // or there is no way back out of it.
    noDisable: name === 'preview',
  };
}

export default function MarkdownEditor({
  value,
  onChange,
  placeholder,
}: {
  value: string;
  onChange: (markdown: string) => void;
  placeholder?: string;
}) {
  const textarea = useRef<HTMLTextAreaElement>(null);
  const editor = useRef<EasyMDE | null>(null);
  // Held in a ref so the CodeMirror change handler always calls the
  // current one without the editor having to be torn down and rebuilt on
  // every render of the form around it.
  const notify = useRef(onChange);
  notify.current = onChange;

  useEffect(() => {
    if (!textarea.current) return;

    const instance = new EasyMDE({
      element: textarea.current,
      initialValue: value,
      placeholder,
      spellChecker: false,
      autoDownloadFontAwesome: false,
      status: false,
      // Exactly the formatting the allowlist keeps, so no button produces
      // something the renderer then refuses. Headings are one button:
      // EasyMDE cycles h1/h2/h3 and every level folds onto h2/h3 anyway.
      //
      // Spelled out as objects rather than the shorthand names because
      // EasyMDE's built-in buttons are Font Awesome glyphs, and this app
      // does not pull a webfont off a CDN to render its own chrome —
      // `autoDownloadFontAwesome: false` above would otherwise leave a row
      // of blank buttons. Text labels also match what the previous editor
      // looked like, so nobody has to relearn the toolbar.
      toolbar: [
        button('bold', 'B', 'Bold'),
        button('italic', 'I', 'Italic'),
        button('strikethrough', 'S', 'Strikethrough'),
        '|',
        button('heading', 'H', 'Heading'),
        button('quote', '“ ”', 'Quote'),
        button('code', 'Code', 'Code'),
        '|',
        button('unordered-list', '• List', 'Bulleted list'),
        button('ordered-list', '1. List', 'Numbered list'),
        '|',
        button('link', 'Link', 'Link'),
        '|',
        button('preview', 'Preview', 'Toggle preview'),
      ],
      renderingConfig: {
        // The preview is the one place this component turns Markdown into
        // an HTML string. Raw HTML is stripped from it for the same reason
        // neither real renderer will run it — an author pasting a script
        // tag should see it do nothing here too, rather than discovering
        // the difference after saving.
        sanitizerFunction: (html: string) => html.replace(/<script[\s\S]*?<\/script>/gi, ''),
      },
    });

    instance.codemirror.on('change', () => notify.current(instance.value()));
    editor.current = instance;

    return () => {
      instance.toTextArea();
      editor.current = null;
    };
    // Mounted once: `value` is read for the initial content only, and
    // syncing it back in on every change is what would fight the cursor.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  // A value replaced from outside (a form reset, a record loading after
  // first paint) still has to reach the editor — but only when it really
  // differs, or every keystroke would rewrite the document underneath the
  // caret.
  useEffect(() => {
    const instance = editor.current;
    if (instance && value !== instance.value()) {
      instance.value(value);
    }
  }, [value]);

  return <textarea ref={textarea} defaultValue={value} />;
}
