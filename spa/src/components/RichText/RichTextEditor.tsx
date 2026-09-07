import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import { useEffect } from 'react';
import { RICH_TEXT_HEADING_LEVELS } from './schema';

/**
 * The editor itself, in its own module and never imported directly —
 * `RichText/index.tsx` loads it lazily, so a visitor reading a profile
 * downloads none of ProseMirror. Default-exported for exactly that
 * reason: React.lazy takes a module with a default.
 *
 * Every extension enabled here is one the server's allowlist accepts (see
 * schema.ts). The ones StarterKit would otherwise bring — horizontal
 * rules, underline — are turned off rather than left on and stripped
 * server-side, because a button that silently does nothing is worse than
 * no button.
 */
export default function RichTextEditor({
  value,
  onChange,
  placeholder,
}: {
  value: string;
  onChange: (html: string) => void;
  placeholder?: string;
}) {
  const editor = useEditor({
    extensions: [
      StarterKit.configure({
        heading: { levels: [...RICH_TEXT_HEADING_LEVELS] },
        horizontalRule: false,
        underline: false,
        // Configured separately below so link handling is explicit rather
        // than whatever the bundle's default happens to be.
        link: false,
      }),
      Link.configure({
        openOnClick: false,
        autolink: true,
        // Mirrors what the server forces onto every stored link. Setting
        // it here too means the editor shows the reader what will
        // actually be saved.
        HTMLAttributes: { rel: 'noreferrer noopener', target: '_blank' },
        protocols: ['http', 'https', 'mailto'],
      }),
    ],
    content: value,
    onUpdate: ({ editor }) => onChange(editor.getHTML()),
  });

  // Only when the incoming value genuinely differs from what the editor
  // already holds — assigning unconditionally on every render would reset
  // the caret to the start of the document on every keystroke.
  useEffect(() => {
    if (editor && value !== editor.getHTML()) {
      editor.commands.setContent(value, { emitUpdate: false });
    }
  }, [editor, value]);

  if (!editor) return <p>Loading the editor…</p>;

  const button = (label: string, isActive: boolean, onClick: () => void, title: string) => (
    <button
      type="button"
      title={title}
      aria-pressed={isActive}
      onClick={onClick}
      style={{
        padding: '2px 8px',
        border: '1px solid var(--border, #ddd)',
        borderRadius: 4,
        background: isActive ? 'var(--accent, #6544e0)' : 'transparent',
        color: isActive ? 'var(--accent-contrast, #fff)' : 'inherit',
        cursor: 'pointer',
      }}
    >
      {label}
    </button>
  );

  return (
    <div style={{ border: '1px solid var(--border, #ddd)', borderRadius: 6 }}>
      <div style={{ display: 'flex', flexWrap: 'wrap', gap: 4, padding: 6, borderBottom: '1px solid var(--border, #ddd)' }}>
        {button('B', editor.isActive('bold'), () => editor.chain().focus().toggleBold().run(), 'Bold')}
        {button('I', editor.isActive('italic'), () => editor.chain().focus().toggleItalic().run(), 'Italic')}
        {button('S', editor.isActive('strike'), () => editor.chain().focus().toggleStrike().run(), 'Strikethrough')}
        {button('H2', editor.isActive('heading', { level: 2 }), () => editor.chain().focus().toggleHeading({ level: 2 }).run(), 'Heading')}
        {button('H3', editor.isActive('heading', { level: 3 }), () => editor.chain().focus().toggleHeading({ level: 3 }).run(), 'Subheading')}
        {button('“ ”', editor.isActive('blockquote'), () => editor.chain().focus().toggleBlockquote().run(), 'Quote')}
        {button('• List', editor.isActive('bulletList'), () => editor.chain().focus().toggleBulletList().run(), 'Bulleted list')}
        {button('1. List', editor.isActive('orderedList'), () => editor.chain().focus().toggleOrderedList().run(), 'Numbered list')}
        {button('Code', editor.isActive('code'), () => editor.chain().focus().toggleCode().run(), 'Inline code')}
        {button(
          'Link',
          editor.isActive('link'),
          () => {
            if (editor.isActive('link')) {
              editor.chain().focus().unsetLink().run();
              return;
            }
            const href = window.prompt('Link to');
            if (href) editor.chain().focus().setLink({ href }).run();
          },
          'Link'
        )}
      </div>
      <EditorContent editor={editor} data-placeholder={placeholder} style={{ padding: 10, minHeight: 160 }} />
    </div>
  );
}
