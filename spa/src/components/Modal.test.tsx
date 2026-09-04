import { describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { Modal } from './Modal';

function renderModal(onClose = vi.fn()) {
  render(
    <Modal title="Settings" onClose={onClose}>
      <button type="button">Inside</button>
    </Modal>
  );
  return onClose;
}

describe('Modal', () => {
  it('closes when a press starts and ends on the backdrop', () => {
    const onClose = renderModal();
    const backdrop = screen.getByRole('dialog');

    fireEvent.pointerDown(backdrop);
    fireEvent.click(backdrop);

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('stays open for a press that started inside and merely finished on the backdrop', () => {
    // Dragging a slider past the modal's edge, or releasing over the
    // backdrop after picking from a dropdown, used to close the modal and
    // discard the edit.
    const onClose = renderModal();
    const backdrop = screen.getByRole('dialog');

    fireEvent.pointerDown(screen.getByRole('button', { name: 'Inside' }));
    fireEvent.click(backdrop);

    expect(onClose).not.toHaveBeenCalled();
  });

  it('stays open when the click is entirely inside the modal', () => {
    const onClose = renderModal();

    fireEvent.pointerDown(screen.getByRole('button', { name: 'Inside' }));
    fireEvent.click(screen.getByRole('button', { name: 'Inside' }));

    expect(onClose).not.toHaveBeenCalled();
  });

  it('closes from the explicit close button', () => {
    const onClose = renderModal();

    fireEvent.click(screen.getByLabelText('Close'));

    expect(onClose).toHaveBeenCalledTimes(1);
  });

  it('does not leave the backdrop armed after a press that started inside', () => {
    // Regression guard on the ref itself: a stale "yes, the backdrop was
    // pressed" would close the modal on the *next* backdrop click even
    // though that press began inside.
    const onClose = renderModal();
    const backdrop = screen.getByRole('dialog');

    fireEvent.pointerDown(screen.getByRole('button', { name: 'Inside' }));
    fireEvent.click(backdrop);
    fireEvent.click(backdrop);

    expect(onClose).not.toHaveBeenCalled();
  });
});
