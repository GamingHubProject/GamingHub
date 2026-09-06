import { Component } from 'react';
import type { ErrorInfo, ReactNode } from 'react';

/**
 * One widget's render failure must not cost the visitor the whole page.
 *
 * Without a boundary here, anything a widget component throws propagates
 * to React Router's route-level error element, which swaps the entire page
 * for "Unexpected Application Error!" — a blank Home page because one
 * stored widget row had a config or a placement its renderer didn't
 * expect (see isPageLayoutWidgetValidFor, and the ContentStripWidget
 * missing-`items` crash before it). A boundary per widget turns that into
 * one placeholder card in an otherwise working page.
 *
 * Deliberately a boundary *around* the component rather than checks inside
 * each one: the failure mode is open-ended (any renderer, any field of an
 * untrusted config blob), so the containment has to be too. The explicit
 * checks that do exist stay — this is the backstop for what they miss, not
 * a replacement.
 *
 * `resetKey` remounts the boundary when the thing being rendered changes
 * (a different widget id, or a config just edited in the admin UI) —
 * otherwise a boundary that has caught once stays stuck on the fallback
 * even after the admin fixes the config that broke it.
 */
export class WidgetErrorBoundary extends Component<
  { children: ReactNode; label: string; resetKey?: unknown },
  { failed: boolean }
> {
  state = { failed: false };

  static getDerivedStateFromError() {
    return { failed: true };
  }

  componentDidUpdate(prevProps: { resetKey?: unknown }) {
    if (this.state.failed && prevProps.resetKey !== this.props.resetKey) {
      this.setState({ failed: false });
    }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    // Console only — there's no error-reporting sink in the SPA to send
    // this to, and silently swallowing it would make a widget that renders
    // an empty placeholder for weeks indistinguishable from one nobody
    // configured.
    console.error(`Widget "${this.props.label}" failed to render`, error, info);
  }

  render() {
    if (this.state.failed) {
      return (
        <p style={{ padding: 12, opacity: 0.7, fontSize: '0.85rem' }}>
          This widget could not be displayed.
        </p>
      );
    }
    return this.props.children;
  }
}
