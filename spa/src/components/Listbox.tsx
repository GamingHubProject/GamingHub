import { useEffect, useId, useRef, useState } from 'react';

/**
 * A click-to-open, click-to-select dropdown, replacing the bare native
 * <select> the style controls used to use.
 *
 * The native element was a real regression, confirmed on Brave/CachyOS:
 * its popup is drawn by the OS rather than the page, and on that platform
 * it closes on mouseup — so a plain click opens and immediately dismisses
 * it, and the only way to pick anything is to press, drag onto an option
 * and release. Filament's own dropdowns on the same machine behave
 * normally because they're in-page DOM, which is exactly what this is.
 *
 * Closing on `pointerdown` rather than `click` is deliberate: a click
 * listener fires after a press that *began* inside the list, which is the
 * same class of stray-close bug in a different disguise.
 */
export interface ListboxOption<T extends string> {
  value: T;
  label: string;
}

export function Listbox<T extends string>({
  value,
  options,
  onChange,
  label,
  id,
  disabled = false,
}: {
  value: T;
  options: ListboxOption<T>[];
  onChange: (value: T) => void;
  /** Accessible name. Rendered by the caller as a visible <label> when it has one. */
  label?: string;
  id?: string;
  disabled?: boolean;
}) {
  const generatedId = useId();
  const buttonId = id ?? generatedId;
  const [open, setOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(0);
  const rootRef = useRef<HTMLDivElement>(null);
  const listRef = useRef<HTMLUListElement>(null);

  const selectedIndex = Math.max(0, options.findIndex((option) => option.value === value));
  const selected = options[selectedIndex];

  useEffect(() => {
    if (!open) return;

    function handlePointerDown(event: PointerEvent) {
      if (!rootRef.current?.contains(event.target as Node)) setOpen(false);
    }
    // Capture, so an outside click closes this before whatever it landed
    // on reacts to it.
    document.addEventListener('pointerdown', handlePointerDown, true);
    return () => document.removeEventListener('pointerdown', handlePointerDown, true);
  }, [open]);

  useEffect(() => {
    if (open) {
      setActiveIndex(selectedIndex);
      listRef.current?.focus();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open]);

  function choose(index: number) {
    const option = options[index];
    if (option) onChange(option.value);
    setOpen(false);
  }

  function handleKeyDown(event: React.KeyboardEvent) {
    if (event.key === 'Escape') {
      setOpen(false);
      return;
    }
    if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
      event.preventDefault();
      const delta = event.key === 'ArrowDown' ? 1 : -1;
      setActiveIndex((i) => Math.min(options.length - 1, Math.max(0, i + delta)));
      return;
    }
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      choose(activeIndex);
    }
  }

  return (
    <div ref={rootRef} style={{ position: 'relative', display: 'inline-block' }}>
      <button
        type="button"
        id={buttonId}
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-label={label}
        disabled={disabled}
        onClick={() => setOpen((o) => !o)}
        style={{
          font: 'inherit',
          color: 'inherit',
          background: 'var(--surface, #fff)',
          border: '1px solid var(--border, #ddd)',
          borderRadius: 'calc(var(--radius, 8px) / 2)',
          padding: '4px 26px 4px 8px',
          cursor: disabled ? 'not-allowed' : 'pointer',
          opacity: disabled ? 0.6 : 1,
          textAlign: 'left',
          minWidth: 120,
          position: 'relative',
        }}
      >
        {selected?.label ?? ''}
        <span aria-hidden="true" style={{ position: 'absolute', right: 8, opacity: 0.6, fontSize: '0.7em' }}>
          ▼
        </span>
      </button>

      {open && (
        <ul
          ref={listRef}
          role="listbox"
          tabIndex={-1}
          aria-labelledby={buttonId}
          aria-activedescendant={`${buttonId}-opt-${activeIndex}`}
          onKeyDown={handleKeyDown}
          style={{
            position: 'absolute',
            top: '100%',
            left: 0,
            zIndex: 1100,
            margin: '2px 0 0',
            padding: 2,
            listStyle: 'none',
            minWidth: '100%',
            maxHeight: 240,
            overflowY: 'auto',
            background: 'var(--surface, #fff)',
            color: 'var(--text, inherit)',
            border: '1px solid var(--border, #ddd)',
            borderRadius: 'calc(var(--radius, 8px) / 2)',
            boxShadow: '0 4px 16px rgba(0,0,0,0.15)',
            outline: 'none',
          }}
        >
          {options.map((option, index) => (
            <li
              key={option.value}
              id={`${buttonId}-opt-${index}`}
              role="option"
              aria-selected={option.value === value}
              onPointerDown={(event) => {
                // Keep focus on the list, and commit on the press rather
                // than waiting for a click that a re-render may eat.
                event.preventDefault();
                choose(index);
              }}
              onPointerEnter={() => setActiveIndex(index)}
              style={{
                padding: '5px 10px',
                borderRadius: 3,
                cursor: 'pointer',
                whiteSpace: 'nowrap',
                background: index === activeIndex ? 'var(--surface-muted, rgba(0,0,0,0.07))' : 'transparent',
                fontWeight: option.value === value ? 600 : 400,
              }}
            >
              {option.label}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
