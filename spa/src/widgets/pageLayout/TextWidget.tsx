import { Markdown, MarkdownField } from '../../components/RichText';
import type { PageLayoutWidgetConfigFormProps } from './registry';

export interface TextWidgetConfig {
  /** Markdown, exactly as its author typed it — the same storage a bio
   *  uses. Rendering happens here rather than on write, so what comes back
   *  to the editor is what was written. */
  markdown: string;
}

export const textWidgetDefaultConfig: TextWidgetConfig = { markdown: '' };

/**
 * Free rich text, on any page. The first widget whose content is authored
 * rather than read from a subject, which is why it is 'General' and valid
 * everywhere — a profile is only its first use, not its purpose.
 */
export function TextWidget({ config }: { config: TextWidgetConfig }) {
  if (!config.markdown) {
    return <p style={{ color: 'var(--muted, #888)', margin: 0 }}>Empty — open the settings to write something.</p>;
  }

  return <Markdown markdown={config.markdown} />;
}

export function TextWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<TextWidgetConfig>) {
  return (
    <label style={{ display: 'block' }}>
      Text
      <div style={{ marginTop: 4 }}>
        <MarkdownField value={config.markdown ?? ''} onChange={(markdown) => onChange({ ...config, markdown })} />
      </div>
    </label>
  );
}
