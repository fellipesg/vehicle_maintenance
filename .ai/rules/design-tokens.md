---
paths:
  - 'resources/css/**'
  - 'resources/views/**'
  - 'resources/js/**'
---

# Design Tokens

## Color comes from semantic roles
Views, components and JS templates use the roles defined in resources/css/app.css (@theme static): bg-background, bg-surface, bg-surface-muted, text-foreground, text-muted-foreground, text-subtle-foreground, border-border, border-border-strong, border-input, ring/outline-ring, bg-primary text-primary-foreground, text-link, bg-accent, bg-danger/bg-danger-soft text-danger, success, warning, info (bg-*-soft + text-*), and bg-sidebar/text-sidebar-*/border-sidebar-border in the admin sidebar. Never raw palette (red-600, gray-500, amber-50, green-*, blue-*) in new code. Shape and motion also come from tokens: rounded-control / rounded-card / rounded-overlay, duration-fast|base|slow (150/200/250ms) with ease-smooth-out, always paired with motion-reduce:transition-none or motion-safe:. Never transition-all.

## Dark surfaces use .theme-inverse, not dark:
The app has no dark mode (dark: only applies under .dark, which the app never sets). A dark surface (guest layout, landing hero, admin sidebar) gets class="theme-inverse" and the same role utilities switch value on their own; a light panel inside it (dialog, toaster, dropdown) gets class="theme-default". Do not re-color with !text-white / !bg-automotive-* overrides inside a theme-inverse scope.

## @tailwindcss/forms stays on strategy 'base'
The forms plugin must keep strategy: 'base'. The 'class' strategy generates .form-input/.form-select in the utilities layer and overrides the component classes of the same name. .form-select keeps pr-10 pl-3 (room for the plugin's arrow) and .form-input/.form-select use text-base sm:text-sm (no iOS zoom on focus).

## Contrast is WCAG 2.1 AA
Text ≥ 4.5:1 (large text 3:1); control borders, focus indicators and state ≥ 3:1. wrench-400..700 are fills, never text on a light background (wrench-600 is 2.90:1, wrench-700 4.20:1); text-wrench-600 is banned. Focus uses --color-ring via focus-visible:outline-ring (wrench-400 inside .theme-inverse); never recolor focus with wrench-100..600. A new role goes into @theme static, .theme-inverse and .theme-default, with its pair in tests/Feature/DesignSystem/DesignTokensTest.php. Tailwind 4 prunes unused theme variables, which is why the roles live in @theme static.

## Motion: short, functional, off with reduced motion
Durations only from the tokens (duration-fast|base|slow; --duration-hero 500ms only in landing CSS), easing ease-smooth-out. Loops (animate-spin/pulse) and hover/active movement (hover:-translate-y-0.5, group-hover:scale, active:scale) always go behind motion-safe:, and only clickable elements lift. app.css keeps the global @media (prefers-reduced-motion: reduce) safety net and html uses motion-safe:scroll-smooth. In the portals and on credential screens motion is functional only: 1.5s check when copying (x-ui.copy-button), toast after an action, 150ms fade when a panel, tab or filtered list changes (x-ui.tab-panel, [data-provenance-filtered] cards, the admin maintenance results) and the opening of sheet, dropdown and dialog. No click-spark, liquid-metal, flip-text, text-stream, Lenis or gsap. DesignSystemGuardrailsTest and PortalMotionTest enforce it.
