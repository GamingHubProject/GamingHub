import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { Listbox } from './Listbox';

const OPTIONS = [
  { value: 'color', label: 'Solid color' },
  { value: 'pattern', label: 'Pattern' },
  { value: 'image', label: 'Image' },
];

function setup(value = 'color', onChange = vi.fn(), disabled = false) {
  render(<Listbox label="Type" value={value} options={OPTIONS} onChange={onChange} disabled={disabled} />);
  return onChange;
}

describe('Listbox', () => {
  it('shows the selected option and keeps the list closed until asked', () => {
    setup('pattern');

    expect(screen.getByRole('button', { name: 'Type' })).toHaveTextContent('Pattern');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('opens on a single click and selects on a single click — the behaviour native <select> lost', () => {
    // The whole reason this component exists: on the reporting platform a
    // native select's OS-drawn popup dismissed on mouseup, so a plain
    // click opened and immediately closed it and only press-drag-release
    // could pick anything. An in-page list has no such problem.
    const onChange = setup();

    fireEvent.click(screen.getByRole('button', { name: 'Type' }));
    expect(screen.getByRole('listbox')).toBeInTheDocument();

    fireEvent.pointerDown(screen.getByRole('option', { name: 'Image' }));

    expect(onChange).toHaveBeenCalledWith('image');
    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('marks the current value as the selected option', () => {
    setup('pattern');
    fireEvent.click(screen.getByRole('button', { name: 'Type' }));

    expect(screen.getByRole('option', { name: 'Pattern' })).toHaveAttribute('aria-selected', 'true');
    expect(screen.getByRole('option', { name: 'Image' })).toHaveAttribute('aria-selected', 'false');
  });

  it('closes on Escape without selecting anything', () => {
    const onChange = setup();
    fireEvent.click(screen.getByRole('button', { name: 'Type' }));

    fireEvent.keyDown(screen.getByRole('listbox'), { key: 'Escape' });

    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
    expect(onChange).not.toHaveBeenCalled();
  });

  it('moves through options with the arrow keys and commits on Enter', () => {
    const onChange = setup('color');
    fireEvent.click(screen.getByRole('button', { name: 'Type' }));

    fireEvent.keyDown(screen.getByRole('listbox'), { key: 'ArrowDown' });
    fireEvent.keyDown(screen.getByRole('listbox'), { key: 'Enter' });

    expect(onChange).toHaveBeenCalledWith('pattern');
  });

  it('closes when a press lands outside it', () => {
    setup();
    fireEvent.click(screen.getByRole('button', { name: 'Type' }));

    fireEvent.pointerDown(document.body);

    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('does not open at all when disabled', () => {
    setup('color', vi.fn(), true);

    fireEvent.click(screen.getByRole('button', { name: 'Type' }));

    expect(screen.queryByRole('listbox')).not.toBeInTheDocument();
  });

  it('exposes the open state to assistive tech', () => {
    setup();
    const button = screen.getByRole('button', { name: 'Type' });

    expect(button).toHaveAttribute('aria-expanded', 'false');
    fireEvent.click(button);
    expect(button).toHaveAttribute('aria-expanded', 'true');
  });
});
