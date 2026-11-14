# UI Guidelines

## Design Tokens

All tokens are set in `resources/css/app.css` as CSS custom properties and are
consumed by Tailwind 4 / Flux everywhere — in both light and dark mode.

### Accent (Brand Colour)

| Token                     | Light mode            | Dark mode            |
|---------------------------|-----------------------|----------------------|
| `--color-accent`          | #059669 (emerald-600) | #10b981 (emerald-500)|
| `--color-accent-content`  | same as accent        | same as accent       |
| `--color-accent-foreground`| #ffffff              | #ffffff              |

Use `bg-accent`, `text-accent`, `ring-accent` etc. via Tailwind utilities.
Flux buttons and focus rings pick this up automatically.

### Neutral Greys

Zinc scale: zinc-50 through zinc-950.
- Backgrounds: white / zinc-50 (light); zinc-900 / zinc-950 (dark)
- Borders: zinc-200 (light); zinc-700 (dark)
- Body text: zinc-700 (light); zinc-200 (dark)
- Muted text: zinc-500 (light); zinc-400 (dark)

### Typography

| Property | Value                                       |
|----------|---------------------------------------------|
| Font     | Instrument Sans (loaded via Bunny Fonts CDN)|
| Weights  | 400 (body), 500 (labels), 600 (headings)    |
| Scale    | text-sm body, text-base forms, text-2xl h1  |

### Spacing & Layout

| Property          | Value        |
|-------------------|--------------|
| Page max-width    | max-w-7xl    |
| Page padding      | p-4 / lg:p-8 |
| Card gap          | gap-4 / gap-6|
| Section gap       | space-y-8    |

### Cards

| Property  | Token / Value                    |
|-----------|----------------------------------|
| Radius    | rounded-xl                       |
| Shadow    | `--shadow-card` (subtle 1–3 px)  |
| Border    | border-zinc-200 / dark:border-zinc-700 |
| Padding   | p-5                              |

### Status Badges

Defined in `resources/views/components/status-badge.blade.php`.
Never convey status by colour alone — the label is always present.

| Status        | Colour      |
|---------------|-------------|
| draft         | zinc        |
| issued        | blue        |
| partially_paid| amber       |
| paid          | emerald     |
| void          | red         |
| overdue       | red         |
| sent          | blue        |
| accepted      | emerald     |
| rejected      | red         |
| converted     | purple      |
| expired       | zinc        |
| active        | emerald     |
| reversed      | red         |
| low_stock     | amber       |
| out_of_stock  | red         |
| in_stock      | emerald     |

## Shared Blade Components

| Component           | Props (key)                              | Notes                                |
|---------------------|------------------------------------------|--------------------------------------|
| `x-page-header`     | title, subtitle, breadcrumbs, slot:actions | Renders h1 + breadcrumb nav + actions|
| `x-stat-card`       | label, value, icon, trend, trendLabel, href, color | % change arrow; color = emerald/amber/red/blue/default |
| `x-card`            | title, subtitle, padding, slot:actions   | White rounded card                   |
| `x-data-table`      | loading, slot:head                       | Sticky head, hover rows, right-align numbers |
| `x-status-badge`    | status (string or enum)                  | Centralised colour map               |
| `x-empty-state`     | icon, title, description, slot:action    |                                      |
| `x-money`           | amount, currency (TZS), decimals (0)     | "TZS 1,250,000"                      |
| `x-date`            | value, format (j M Y)                    | Wraps in `<time datetime="...>">`    |
| `x-loading-skeleton`| lines, height                            | Animated placeholder                 |
| `x-chart`           | type (line/bar/doughnut), chartData, height, title | Alpine + Chart.js; re-renders on `chart-update` Livewire event |

## Chart.js

- Installed via npm: `chart.js ^4`.
- Bundled in `resources/js/app.js` via Vite (tree-shaken to used controllers only).
- Alpine component `appChart(type, initialData)` wired through `x-data="appChart(...)"`
  on the `<x-chart>` wrapper.
- Dispatches re-renders when Livewire updates chart data via
  `$this->dispatch('chart-update', type: 'line', data: [...])`.
- Automatically re-applies colours on dark/light mode toggle (MutationObserver on `<html>`).

## Accessibility

- All inputs have a `<label>` or `aria-label`.
- Status is never shown by colour alone — the badge text is always visible.
- Focus ring uses `ring-accent` with `ring-offset-2`.
- Keyboard focus is visible on all interactive elements (Flux default styles preserved).
- Colour contrast for accent text on white background: emerald-600 (#059669) on white = 4.54:1 ✓ AA.
