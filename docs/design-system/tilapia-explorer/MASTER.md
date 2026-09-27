# Design System Master File

> **LOGIC:** When building a specific page, first check `design-system/pages/[page-name].md`.
> If that file exists, its rules **override** this Master file.
> If not, strictly follow the rules below.

---

**Project:** AquaDissect
**Updated:** 2026-09-27
**Category:** Accessible virtual pre-laboratory learning

---

## Global Rules

### Color Palette

| Role | Hex | CSS Variable |
|------|-----|--------------|
| Primary | `#0140C1` | `--color-primary` |
| On Primary | `#FFFFFF` | `--color-on-primary` |
| Primary deep | `#0E315B` | `--color-primary-deep` |
| Secondary | `#1594E3` | `--color-secondary` |
| Background | `#F5F9FF` | `--color-background` |
| Surface | `#FFFFFF` | `--color-surface` |
| Surface soft | `#EAF4FF` | `--color-surface-soft` |
| Foreground | `#0D1442` | `--color-foreground` |
| Muted text | `#5B6F92` | `--color-muted-text` |
| Border | `#C9D9EA` | `--color-border` |
| Destructive | `#B42318` | `--color-destructive` |
| Ring | `#0F40CB` | `--color-ring` |

**Color Notes:** Flat ocean blue palette. Do not use gradients. Use cyan only as a restrained supporting accent.

### Typography

- **Heading Font:** Figtree
- **Body Font:** Figtree
- **Weights:** 400, 500, 600, 700, 800
- **Mood:** calm, clear, scientific, supportive, approachable
- **Source:** Bundled through `@fontsource/figtree`; do not depend on a remote font request.

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
  background: #0140C1;
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
  color: #0140C1;
  border: 1px solid #0140C1;
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
  border-color: #0140C1;
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
