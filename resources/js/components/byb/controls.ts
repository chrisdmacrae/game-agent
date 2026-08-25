/**
 * Shared control chrome. Buttons, inputs and selects share the 30/38/46 heights
 * so toolbars line up, and every well uses the same hairline + inset shadow.
 */
export const CONTROL_SURFACE =
    'w-full rounded-[var(--radius-sm)] border border-[var(--border-subtle)] bg-[var(--surface-input)] text-[var(--fg-1)] outline-none [box-shadow:var(--shadow-inset-well)] [transition:var(--transition-control)] placeholder:text-[var(--fg-3)] hover:border-[var(--border-strong)] focus:border-[var(--border-accent)] focus:[box-shadow:var(--shadow-inset-well),0_0_0_3px_var(--surface-accent-soft-hover)] focus:outline-2 focus:outline-offset-2 focus:outline-[var(--focus-ring)] disabled:cursor-not-allowed disabled:opacity-40 motion-reduce:transition-none';

export type ControlSize = 'sm' | 'md' | 'lg';

export const CONTROL_SIZES: Record<ControlSize, string> = {
    sm: 'h-[var(--control-h-sm)] px-2.5 text-[13px]',
    md: 'h-[var(--control-h)] px-3 text-[15px]',
    lg: 'h-[var(--control-h-lg)] px-3.5 text-[15px]',
};

/** Mono uppercase caption used for every field label, eyebrow and stat label. */
export const LABEL_CLASS =
    'font-mono text-[11px] leading-none font-bold tracking-[0.14em] text-[var(--fg-3)] uppercase';
