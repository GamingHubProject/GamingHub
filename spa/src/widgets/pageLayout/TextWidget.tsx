import { RichTextContent, RichTextField } from '../../components/RichText';
import type { PageLayoutWidgetConfigFormProps } from './registry';

export interface TextWidgetConfig {
  /** Sanitised HTML, produced by the shared editor and sanitised again on
   *  the way into the database (App\Profiles\RichText) — the same
   *  round-trip a bio takes. Never render this anywhere that hasn't been
   *  through that. */
  html: string;
}

export const textWidgetDefaultConfig: TextWidgetConfig = { html: '' };

/**
 * Free rich text, on any page. The first widget whose content is authored
 * rather than read from a subject, which is why it is 'General' and valid
 * everywhere — a profile is only its first use, not its purpose.
 */
export function TextWidget({ config }: { config: TextWidgetConfig }) {
  if (!config.html) {
    return <p style={{ color: 'var(--muted, #888)', margin: 0 }}>Empty — open the settings to write something.</p>;
  }

  return <RichTextContent html={config.html} />;
}

export function TextWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<TextWidgetConfig>) {
  return (
    <label style={{ display: 'block' }}>
      Text
      <div style={{ marginTop: 4 }}>
        <RichTextField value={config.html ?? ''} onChange={(html) => onChange({ ...config, html })} />
      </div>
    </label>
  );
}
