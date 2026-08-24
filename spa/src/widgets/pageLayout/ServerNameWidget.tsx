import type { PageLayoutWidgetConfigFormProps, PageLayoutWidgetContext } from './registry';

export interface ServerNameWidgetConfig {
  font_size: number;
  text_color: string;
}

export const serverNameWidgetDefaultConfig: ServerNameWidgetConfig = {
  font_size: 24,
  text_color: '#ffffff',
};

/**
 * Split out of what used to be ServerBannerWidget's built-in <h1> — the
 * banner is now purely a background layer (see its own docblock), and the
 * name is its own independent, layerable widget so an admin can place it
 * over the banner (or anywhere else) like any other widget. font_size/
 * text_color exist because the banner's own background image is arbitrary
 * admin-chosen art — a fixed color has no chance of staying readable
 * against all of them, so this is configurable per-server rather than a
 * theme constant. The text-shadow below isn't configurable and always
 * applies while layered — a legibility floor under whatever color is
 * picked, not a style choice. validFor: ['server'] guarantees
 * context.server is set — see registry.ts.
 */
export function ServerNameWidget({
  context,
  config,
  layered,
}: {
  context: PageLayoutWidgetContext;
  config: ServerNameWidgetConfig;
  layered?: boolean;
}) {
  const server = context.server!;

  return (
    <div style={{ padding: layered ? 0 : '8px 12px', height: '100%', display: 'flex', alignItems: 'center' }}>
      <h1
        style={{
          margin: 0,
          fontSize: config.font_size,
          color: layered ? config.text_color : undefined,
          textShadow: layered ? '0 1px 3px rgba(0, 0, 0, 0.8)' : undefined,
        }}
      >
        {server.name}
      </h1>
    </div>
  );
}

export function ServerNameWidgetConfigForm({ config, onChange }: PageLayoutWidgetConfigFormProps<ServerNameWidgetConfig>) {
  return (
    <div style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
      <label>
        Text size ({config.font_size}px)
        <div style={{ marginTop: 4 }}>
          <input
            type="range"
            min={12}
            max={64}
            step={1}
            value={config.font_size}
            onChange={(event) => onChange({ ...config, font_size: Number(event.target.value) })}
          />
        </div>
      </label>
      <label style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
        Text color
        <input
          type="color"
          value={config.text_color}
          onChange={(event) => onChange({ ...config, text_color: event.target.value })}
        />
      </label>
    </div>
  );
}
