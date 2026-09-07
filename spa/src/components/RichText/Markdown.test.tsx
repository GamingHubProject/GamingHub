import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { Markdown } from './index';

/**
 * The renderer a reader actually meets. It builds React elements from the
 * Markdown AST, so no HTML string produced from somebody's bio is ever
 * inserted into the page — which is the property that makes storing
 * Markdown safer than storing sanitised HTML, not just more editable.
 */
describe('Markdown', () => {
  it('renders every piece of formatting the toolbar offers', () => {
    const { container } = render(
      <Markdown markdown={'## Heading\n\n**bold** *italic* ~~struck~~ `code`\n\n- one\n- two\n\n1. first\n\n> quoted\n\n[link](https://example.com)'} />
    );

    expect(screen.getByRole('heading', { level: 2, name: 'Heading' })).toBeInTheDocument();
    expect(container.querySelector('strong')).toHaveTextContent('bold');
    expect(container.querySelector('em')).toHaveTextContent('italic');
    expect(container.querySelector('del')).toHaveTextContent('struck');
    expect(container.querySelector('code')).toHaveTextContent('code');
    expect(container.querySelectorAll('ul li')).toHaveLength(2);
    expect(container.querySelector('ol li')).toHaveTextContent('first');
    expect(container.querySelector('blockquote')).toHaveTextContent('quoted');
    expect(screen.getByRole('link', { name: 'link' })).toHaveAttribute('href', 'https://example.com');
  });

  it('never renders raw html from the source', () => {
    const { container } = render(<Markdown markdown={'Hello\n\n<script>alert(1)</script>\n\n<img src="x" onerror="y">'} />);

    expect(container.querySelector('script')).toBeNull();
    expect(container.querySelector('img')).toBeNull();
    expect(container).toHaveTextContent('Hello');
  });

  it('refuses a javascript link while keeping its text', () => {
    render(<Markdown markdown={'[Click me](javascript:alert(1))'} />);

    const link = screen.getByText('Click me');
    expect(link.getAttribute('href') ?? '').not.toContain('javascript');
  });

  it('treats every link as hostile', () => {
    render(<Markdown markdown={'[Example](https://example.com)'} />);

    const link = screen.getByRole('link', { name: 'Example' });
    expect(link).toHaveAttribute('rel', 'noreferrer noopener');
    expect(link).toHaveAttribute('target', '_blank');
  });

  /** '#' is the obvious thing to type, and the allowlist keeps only h2/h3
   *  — folding rather than dropping is what stops the words going with the
   *  element. The server renderer folds the same levels. */
  it('folds every heading level onto the two the allowlist keeps', () => {
    render(<Markdown markdown={'# Top\n\n###### Deep'} />);

    expect(screen.getByRole('heading', { level: 2, name: 'Top' })).toBeInTheDocument();
    expect(screen.getByRole('heading', { level: 3, name: 'Deep' })).toBeInTheDocument();
  });

  it('renders an image as nothing while keeping the words around it', () => {
    const { container } = render(<Markdown markdown={'before ![alt](https://example.com/x.png) after'} />);

    expect(container.querySelector('img')).toBeNull();
    expect(container).toHaveTextContent('before');
    expect(container).toHaveTextContent('after');
  });

  it('renders nothing at all for an empty value', () => {
    const { container } = render(<Markdown markdown={null} />);

    expect(container).toBeEmptyDOMElement();
  });

  it('keeps markup quoted inside a code fence as text', () => {
    const { container } = render(<Markdown markdown={'```\n<script>alert(1)</script>\n```'} />);

    expect(container.querySelector('script')).toBeNull();
    expect(container.querySelector('pre')).toHaveTextContent('<script>alert(1)</script>');
  });
});
