# GDIPS UI system

The dashboard front-end is a small, dependency-free layer on top of the
existing PHP pages. No build step, no framework - just organized CSS and one
JavaScript file.

## Stylesheets (`incl/ui/`)

Load order matters (later files override earlier ones):

| File              | Purpose                                                        |
|-------------------|----------------------------------------------------------------|
| `tokens.css`      | Design tokens: colors, type scale, spacing, radii, motif       |
| `base.css`        | Reset, typography, focus states, accessibility, reduced motion |
| `layout.css`      | App shell: sidebar, topbar, mobile drawer, footer, grid areas  |
| `components.css`  | Buttons, forms, cards, chips, tables, toasts, audio player     |
| `pages.css`       | Page compositions: home, browse, clans, profile, auth, project |

**Rules for contributors**

- Consume design tokens (`var(--merah)`, `var(--sp-4)`, …); don't hard-code
  colors or pixel spacing.
- One accent (red) + one supporting accent (gold). If you need a new color,
  add a token first and justify it.
- Reuse existing classes before adding new ones. The `gd-*` classes are the
  design system; the legacy classes (`.form`, `.btn-primary`, `.profile`, …)
  are deliberately restyled to match so older admin pages stay coherent.
- Two visual layers live side by side on purpose:
  1. `gd-*` primitives - used by reworked pages.
  2. legacy classes - styled via `components.css` so untouched pages inherit
     the identity without rewrites.

## Behavior (`incl/gdips.js`)

- `a(page, ...)` - the SPA navigation used by `onclick="a('...')"`. It swaps
  `#htmlpage` (content) and `#navbarepta` (sidebar/topbar), re-executes the
  page's last inline script and `#bottomrowscript`, and keeps history in sync.
- `gdBoot()` - rebinds the audio player and drawer after each navigation.
- Toasts, song actions (`likeSong`, `deleteSong`, …), `cron()`, the floating
  audio `player`, and media-key handling.

**Contracts you must not break**

- Element ids: `#htmlpage`, `#navbarepta`, `#isSubdirectory`,
  `#dashboard-error-text`, `#bottomrowscript`, `#audioPlayer` family,
  `#icon{songID}`, `#btn{songID}`, `#copy{...}`.
- Form ordering in `generateBottomRow()` - `a(..., getdata)` indexes forms
  from the end of the document.
- `form[name=searchform]` - used by list pages' search (`a(..., 69)`).
- Localized strings for JS arrive via `window.GDIPS.i18n` (printed by
  `dashboardLib::printNavbar()`).

## Identity notes

- The kawung-inspired geometry motif (`--kawung-svg` in `tokens.css`) is the
  single Indonesian visual reference; it appears at 4–5% opacity as texture,
  never as decoration pasted on components.
- Display type is Chakra Petch (headings/brand/numbers); body is Inter.
- Dark warm-neutral surfaces, red primary accent, gold for stars/featured -
  the GD difficulty colors are reserved for difficulty chips only.
- `prefers-reduced-motion` disables all animation (see `base.css`).
