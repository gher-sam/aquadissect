# Design System Master File

> **LOGIC:** When building a specific page, first check `design-system/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** AquaDissect
**Updated:** 2026-09-29
**Category:** Accessible virtual pre-laboratory learning

---

## Global Rules

### Color Palette

| Role | Hex | CSS Variable |
|------|-----|--------------|
| Primary | `#3E60D5` | `--color-primary` |
| On Primary | `#FFFFFF` | `--color-on-primary` |
| Primary deep | `#283D85` | `--color-primary-deep` |
| Secondary | `#1594E3` | `--color-secondary` |
| Background | `#F2F2F7` | `--color-background` |
| Surface | `#FFFFFF` | `--color-surface` |
| Surface soft | `#F5F6FA` | `--color-surface-soft` |
| Foreground | `#343A40` | `--color-foreground` |
| Muted text | `#596579` | `--color-muted-text` |
| Border | `#DEE2E6` | `--color-border` |
| Destructive | `#B42318` | `--color-destructive` |
| Ring | `#0F40CB` | `--color-ring` |

**Color Notes:** Flat ocean blue palette. Do not use gradients. Use cyan only as a restrained supporting accent.

### Typography

- **Heading Font:** Figtree
- **Body Font:** Figtree
- **Weights:** 400, 500, 600, 700, 800
- **Mood:** calm, clear, scientific, supportive, approachable
- **Source:** Bundled through `@fontsource/figtree`; do not depend on a remote font request.

#### Type roles

| Role | Size / line height | Weight | Usage |
|------|--------------------|--------|-------|
| Section label | `12px / 18px` | `700` | Short uppercase navigation and section cues |
| Supporting text | `14px / 21px` | `400-600` | Metadata, table cells, captions, and helper text |
| Controls | `15px / 22px` | `500-600` | Navigation, buttons, and compact interactive rows |
| Body | `16px / 25px` | `400` | Instructions, explanations, and learning content |
| Card title | `18px / 26px` | `600` | Card and panel headings |
| Section title | `20-24px / 1.3` | `600` | Major content sections |
| Page title | `26-32px / 1.2` | `700` | One primary heading per page; scales down on narrow phones |
| Metric | `24px / 32px` | `700` | Progress totals and summary values |

Use uppercase only for short labels. Keep paragraph text at 16px for learners
and other non-technical users; reserve 14px for supporting information. Use
tabular numerals for scores and progress values. The implementation source of
truth is `resources/css/typography.css`, loaded after page-specific styles.

**CSS Import:**
```css
font-family: 'Figtree', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
```

### Spacing Variables

| Token | Value | Usage |
|-------|-------|-------|
| `--space-xs` | `4px` / `0.25rem` | Tight gaps |
| `--space-sm` | `8px` / `0.5rem` | Icon gaps, inline spacing |
| `--space-md` | `16px` / `1rem` | Standard padding |
| `--space-lg` | `24px` / `1.5rem` | Section padding |
| `--space-xl` | `32px` / `2rem` | Large gaps |
| `--space-2xl` | `48px` / `3rem` | Section margins |
| `--space-3xl` | `64px` / `4rem` | Hero padding |

### Shadow Depths

| Level | Value | Usage |
|-------|-------|-------|
| `--shadow-sm` | `0 1px 2px rgba(0,0,0,0.05)` | Subtle lift |
| `--shadow-md` | `0 4px 6px rgba(0,0,0,0.1)` | Cards, buttons |
| `--shadow-lg` | `0 10px 15px rgba(0,0,0,0.1)` | Modals, dropdowns |
| `--shadow-xl` | `0 20px 25px rgba(0,0,0,0.15)` | Hero images, featured cards |

---

## Component Specs

### Buttons

```css
/* Primary Button */
.btn-primary {
  background: #3E60D5;
  color: white;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}

.btn-primary:hover {
  opacity: 0.9;
  transform: translateY(-1px);
}

/* Secondary Button */
.btn-secondary {
  background: #FFFFFF;
  color: #3E60D5;
  border: 1px solid #3E60D5;
  padding: 12px 24px;
  border-radius: 8px;
  font-weight: 600;
  transition: all 200ms ease;
  cursor: pointer;
}
```

### Cards

```css
.card {
  background: #FFFFFF;
  border: 1px solid #C9D9EA;
  border-radius: 12px;
  padding: 24px;
  box-shadow: var(--shadow-md);
  transition: all 200ms ease;
  cursor: pointer;
}

.card:hover {
  box-shadow: var(--shadow-lg);
  transform: translateY(-2px);
}
```

### Inputs

```css
.input {
  padding: 12px 16px;
  border: 1px solid #E2E8F0;
  border-radius: 8px;
  font-size: 16px;
  transition: border-color 200ms ease;
}

.input:focus {
  border-color: #3E60D5;
  outline: none;
  box-shadow: 0 0 0 3px rgb(15 64 203 / 20%);
}
```

### Modals

```css
.modal-overlay {
  background: rgba(0, 0, 0, 0.5);
  backdrop-filter: blur(4px);
}

.modal {
  background: white;
  border-radius: 16px;
  padding: 32px;
  box-shadow: var(--shadow-xl);
  max-width: 500px;
  width: 90%;
}
```

---

## Style Guidelines

**Style:** Accessible editorial marine learning

**Keywords:** Blue, clear hierarchy, generous spacing, plain language, calm motion, scientific illustration, visible progress

**Best For:** Non-technical students, instructors, and administrators using a virtual laboratory learning system

**Key Effects:** Rounded cards, restrained shadows, 150-250ms transitions, clear focus rings, and reduced-motion fallbacks

### Page Pattern

### Responsive sign-in presentation

- On desktop, place the existing tilapia illustration in a pale blue panel beside the white sign-in form.
- On tablets and phones, show the form first and the compact specimen card below it. Keep the form first in document order.
- Use 16px input text and primary controls at least 52px tall. Preserve visible keyboard focus and field labels.
- Keep student activation and instructor access separate, with the existing account workflows and permissions.
- Use preparation-oriented copy; do not imply validated learning effectiveness.

### Shared page presentation

- People & sections uses separate section and enrolled-student tables with search, sortable headings, page size, pagination, and explicit empty states.
- Keep section actions in expandable detail rows and account-creation forms in disclosures. Preserve table semantics and contained horizontal scrolling on phones.

- Frame page introductions in a white panel with a restrained blue top border, a clear heading, and nearby guidance.
- Keep the dashboard's Workspace guide action available to every signed-in role.
- Use light card shadows; informational cards must not move on hover.
- Give data tables blue-tinted column headings, alternating rows, comfortable cell spacing, and a contained horizontal scroll area.
- Keep form controls consistent, file-selection buttons readable, and disabled actions visibly distinct.
- Stack dashboard introductions on narrower screens and use full-width guide actions on phones.
- Apply the shared presentation to authentication, dashboards, species lessons, safety, classes, roster, activities, downloads, content management, media prompts, account roles, help, and lesson panels. Retain each page's existing permissions and workflow.

**Pattern Name:** Immersive/Interactive Experience

- **Learning Strategy:** Show one clear next action, explain locked states, retain progress, and give recovery guidance near errors.
- **CTA Placement:** Put the primary next action beside the current status or progress summary.
- **Section Order:** 1. Page purpose and status, 2. Current or required action, 3. Supporting detail, 4. Advanced controls in disclosures.

---

## Anti-Patterns (Do NOT Use)

- ❌ Inconsistent styling
- ❌ Poor contrast ratios
- ❌ Gradient fills
- ❌ Green as the primary product color
- ❌ Technical labels when plain language is available

### Additional Forbidden Patterns

- ❌ **Emojis as icons** — Use SVG icons (Heroicons, Lucide, Simple Icons)
- ❌ **Missing cursor:pointer** — All clickable elements must have cursor:pointer
- ❌ **Layout-shifting hovers** — Avoid scale transforms that shift layout
- ❌ **Low contrast text** — Maintain 4.5:1 minimum contrast ratio
- ❌ **Instant state changes** — Always use transitions (150-300ms)
- ❌ **Invisible focus states** — Focus states must be visible for a11y

---

## Pre-Delivery Checklist

Before delivering any UI code, verify:

- [ ] No emojis used as icons (use SVG instead)
- [ ] All icons from consistent icon set (Heroicons/Lucide)
- [ ] `cursor-pointer` on all clickable elements
- [ ] Hover states with smooth transitions (150-300ms)
- [ ] Light mode: text contrast 4.5:1 minimum
- [ ] Focus states visible for keyboard navigation
- [ ] `prefers-reduced-motion` respected
- [ ] Responsive: 375px, 768px, 1024px, 1440px
- [ ] No content hidden behind fixed navbars
- [ ] No horizontal scroll on mobile
- [ ] Figtree is the rendered font
- [ ] Blue motif uses flat fills only; no gradients
- [ ] Errors remain visible long enough to understand and retry


## Workspace redesign - approved September 27, 2026

Attex dashboard and data-table patterns inform the workspace. AquaDissect retains its own components, Figtree, branding, protected lesson artwork, and faculty-review rules. `resources/css/workspace.css` controls the shared light workspace.

- Primary #3E60D5; sidebar #212529; page #F2F2F7; surface white; text #343A40; secondary text #596579; borders #DEE2E6.
- Clear 26-32px responsive page headings, 4-6px panel corners, restrained shadows, 44px interaction targets, visible keyboard focus.
- Desktop sidebar 240px, collapsible to 76px; mobile drawer at 780px. Top bar includes navigation/help search, inbox, and account controls.
- Individual Blade pages and named URLs support browser history, reload, and bookmarks. Protected routes enforce active roles on the server.
- Staff tables support search, sorting, pagination, and expandable workflows. Lessons/offline materials keep visual cards. Tables scroll inside labeled regions on narrow screens.
- Calendar displays Philippine time and starts in list view on phones. Reports separate unattributed historical results and do not imply effectiveness.
- `/offline` is a neutral cached document. Authenticated HTML is not cached as the shared shell. Offline queues are account-bound; older unowned queues remain retained without automatic attribution.
- Labels, inline errors, disclosures, and dialog focus remain required. Initial page loads do not automatically focus headings.

References: https://themes.coderthemes.com/attex/layouts/index.html and https://themes.coderthemes.com/attex/layouts/tables-datatable.html.

## Bootstrap integration - September 27, 2026

Bootstrap 5.3.8 and Popper 2.11.8 are installed locally and bundled through Vite;
no CDN is required. `resources/css/bootstrap.scss` supplies themed containers,
grid, forms, buttons, tables, dropdowns, breadcrumbs, pagination, spinners, and
utilities. Its CSS layer gives existing application styles precedence. Reboot
and global typography resets are intentionally omitted to preserve anatomy and
legacy page rendering. Only the Dropdown JavaScript plugin is loaded, powering
the shared Account menu with arrow-key navigation, Escape, and outside dismissal.

Use Bootstrap classes for new standard controls; retain existing application
handlers and native dialogs unless a component is explicitly migrated. This is
a shared framework integration, not a conversion of every existing component.
Primary color remains #3E60D5 and font remains Figtree. Bootstrap guidance:
https://getbootstrap.com/docs/5.3/getting-started/vite/.

The sidebar follows Attex's compact navigation proportions: a fixed 70px logo
area, independently scrolling menu, subdued section labels, 44px navigation
targets, subtle active backgrounds, and a fixed account footer. Desktop uses
240px expanded and 76px collapsed widths; the mobile drawer uses 260px. The
navigation toggle reports the current expanded state after viewport changes.
