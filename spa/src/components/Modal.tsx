import { useRef, type ReactNode } from 'react';

export function Modal({
  title,
  onClose,
  children,
}: {
  title: string;
  onClose: () => void;
  children: ReactNode;
}) {
  // A bare onClick on the backdrop also fires for a press that *started*
  // inside the modal and merely finished outside it — dragging a slider
  // past the edge, or releasing over the backdrop after picking from a
  // dropdown. Requiring the press to both start and end on the backdrop
  // makes "click outside to close" mean what it says.
  const pressedBackdrop = useRef(false);

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={title}
      onPointerDown={(event) => {
        pressedBackdrop.current = event.target === event.currentTarget;
      }}
      style={{
        position: 'fixed',
        inset: 0,
        background: 'rgba(0, 0, 0, 0.5)',
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        zIndex: 1000,
      }}
      onClick={(event) => {
        if (event.target === event.currentTarget && pressedBackdrop.current) onClose();
        pressedBackdrop.current = false;
      }}
    >
      <div
        style={{
          background: 'var(--surface, #fff)',
          color: 'var(--text, #111)',
          borderRadius: 8,
          padding: 24,
          minWidth: 320,
          maxWidth: '90vw',
          maxHeight: '85vh',
          overflowY: 'auto',
        }}
      >
        <div style={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', marginBottom: 16 }}>
          <h2 style={{ margin: 0, fontSize: '1.1rem' }}>{title}</h2>
          <button onClick={onClose} aria-label="Close">
            ×
          </button>
        </div>
        {children}
      </div>
    </div>
  );
}
