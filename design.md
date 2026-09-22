---
version: "1.0"
name: "Soft UI Evolution"
status: "normative"
scope: "MAI SHOES storefront (Phase A). Admin contract is DEFERRED — see section 8."
supersedes: "design.md alpha (Soft UI Evolution template, designmd.app)"
---

# MAI SHOES — Normative Design System

This is the single source of truth for the storefront visual system. It supersedes the
previous `design.md` alpha document and the Phase 07 brand tokens
(`docs/03-WORK-PACKAGES.md`, Phase 07) for every colour, shape, elevation and motion
decision on the customer storefront.

## 1. Why the alpha values were replaced

The alpha document shipped three hex values used as text, link, focus and CTA colours.
Measured against white they are unusable for that purpose:

| Alpha token | Alpha role | Measured on `#FFFFFF` | WCAG AA text minimum | Verdict |
| --- | --- | ---: | ---: | --- |
| `#87CEEB` Soft Blue | accent, links, focus states | **1.74 : 1** | 4.5 : 1 | Fail |
| `#FFB6C1` Soft Pink | "primary text color" | **1.65 : 1** | 4.5 : 1 | Fail |
| `#90EE90` Soft Green | supporting palette colour | **1.42 : 1** | 4.5 : 1 | Fail |

The soft, pastel, low-contrast *feel* is the intended direction, so the hue families are
retained rather than discarded. What changes is their **assignment**:

- Pastels become **tint surfaces** (`*-soft`), never text.
- A darker tonal partner of the same hue family becomes the **ink** for text and icons.
- A solid, white-text-safe partner becomes the **CTA** fill.

This is exactly the contract the Soft UI Evolution style describes for itself
("Improved contrast AA/AAA. Real contrast ratios meeting AA standards"). No alpha value
remains in use as a text, link, focus or CTA colour. Nothing in the storefront ships the
1.42–1.74 : 1 combinations.

## 2. Non-negotiable constraints

1. Semantic tokens are declared once in `resources/css/app.css` (`@theme` + `.dark`).
2. Components use semantic utilities only. Raw brand palette utilities are forbidden.
3. Every text pair meets **4.5 : 1**; every control boundary and focus indicator meets
   **3 : 1** (WCAG 1.4.11).
4. Colour is never the only carrier of meaning. Status always pairs colour with a label
   and an icon or accessible name.
5. `lang="ar" dir="rtl"` is authoritative; layout uses logical properties only.
6. `prefers-reduced-motion: reduce` disables all non-essential motion.
7. No emoji as icons. Icons are inline SVG.
8. No pure black. No `h-screen`; use `100dvh`.
9. Touch targets are at least 44 px tall.

## 3. Normative scales — light theme

Surfaces and text:

| Token | Value | Role |
| --- | --- | --- |
| `--color-page` | `#F6F9FB` | page background (soft wash) |
| `--color-surface` | `#FFFFFF` | cards, sheets, inputs on page |
| `--color-surface-soft` | `#F1F6F9` | inset / secondary surface |
| `--color-surface-muted` | `#E4EDF2` | pressed / muted surface |
| `--color-ink` | `#10222B` | primary text (off-black, not `#000`) |
| `--color-ink-muted` | `#4E6470` | secondary text, metadata |
| `--color-line` | `#D8E4EA` | 1 px decorative separator |
| `--color-line-strong` | `#6E8D9F` | 2 px control boundary (3 : 1) |
| `--color-ring` | `#1878A8` | focus ring |

Semantic roles. Each role is three values: tint surface, ink, solid CTA.

| Role | Soft (tint) | Ink | CTA (solid) | Meaning |
| --- | --- | --- | --- | --- |
| `primary` | `#E4F1F9` | `#0B4A66` | `#0B4A66` | the factory's own action |
| `success` | `#E5F6E8` | `#1F6B33` | `#1B5E2D` | finished well |
| `attention` | `#FBE7EB` | `#A0304A` | `#952A43` | waiting for a human |
| `progress` | `#EDE9FB` | `#5741A6` | `#4C3893` | underway, incomplete |
| `stopped` | `#EDF1F4` | `#47585F` | `#3D4C52` | terminated, no action |
| `danger` | `#FCE8E5` | `#A32B20` | `#92261C` | broken, destructive |
| `external` | `#E3F6EB` | `#0E7A4A` | `#0C6A41` | leaves the system (WhatsApp) |
| `data` | `#E3F4F4` | `#14666B` | `#125A5E` | data moving (import / export) |

Supporting tokens: `--color-primary-strong: #093D54`,
`--color-on-primary: #FFFFFF`, `--color-selected: #D7EAF6`,
and `*-strong` CTA values as listed above.

On-fill tokens for solid surfaces. A role whose fill is light in dark mode needs a dark
on-colour, so these flip with the theme:

| Token | Light | Dark | Fill it is measured against |
| --- | --- | --- | --- |
| `--color-on-primary` | `#FFFFFF` | `#0E1720` | `#0B4A66` / `#9DD0EA` |
| `--color-on-danger` | `#FFFFFF` | `#0E1720` | `#A32B20` / `#F5A79C` |
| `--color-on-external` | `#FFFFFF` | `#0E1720` | `#0E7A4A` / `#5FD3A3` |

Measured: `on-primary` 9.61 / 10.88, `on-danger` 7.18 / 9.37, `on-external` 5.38 / 9.75.

The previous dark `#25D366` WhatsApp fill is retired: it carried `text-white` at
**1.98 : 1**, which fails AA. The external role replaces it at 9.75 : 1.

The migration-era compatibility aliases (`--font-brand`, `--font-retro`,
`--color-crimson`, `--color-burgundy`, `--color-navy`, `--color-powder`,
`--color-powder-ink`, `--color-cream`, `--color-warning`, `--color-whatsapp`,
`--shadow-retro*`) have been removed. No component references them.

## 4. Normative scales — dark theme

| Token | Value |
| --- | --- |
| `--color-page` | `#0E1720` |
| `--color-surface` | `#16222C` |
| `--color-surface-soft` | `#1D2B36` |
| `--color-surface-muted` | `#25333F` |
| `--color-ink` | `#E9F1F6` |
| `--color-ink-muted` | `#A6B8C4` |
| `--color-line` | `#2E3F4C` |
| `--color-line-strong` | `#5E7889` |
| `--color-ring` | `#6BB8DC` |
| `--color-primary` | `#9DD0EA` |
| `--color-primary-soft` | `#12303F` |
| `--color-on-primary` | `#0E1720` |
| `--color-selected` | `#1B3A4C` |

| Role | Soft (tint) | Ink |
| --- | --- | --- |
| `success` | `#14301B` | `#A6E3A1` |
| `attention` | `#3A1A22` | `#F5A9B8` |
| `progress` | `#241B3D` | `#C4B5FD` |
| `stopped` | `#1E2B33` | `#B9C7CE` |
| `danger` | `#3A1613` | `#F5A79C` |
| `external` | `#0F2E22` | `#5FD3A3` |
| `data` | `#123033` | `#7FD3D6` |

## 5. Measured contrast evidence

All values below are computed with the WCAG relative-luminance formula, not estimated.

Light theme — ink on its own tint, ink on white, white on CTA (minimum 4.5):

| Role | ink / tint | ink / white | white / CTA |
| --- | ---: | ---: | ---: |
| `primary` | 8.35 | 9.61 | 9.61 |
| `success` | 5.82 | 6.54 | 7.82 |
| `attention` | 5.88 | 6.96 | 7.81 |
| `progress` | 6.51 | 7.74 | 9.15 |
| `stopped` | 6.54 | 7.42 | 8.92 |
| `danger` | 6.09 | 7.18 | 8.35 |
| `external` | 4.78 | 5.38 | 6.66 |
| `data` | 5.89 | 6.68 | 7.93 |

Light theme — surfaces and controls:

| Pair | Ratio | Minimum |
| --- | ---: | ---: |
| `ink` / `surface` | 16.33 | 4.5 |
| `ink` / `page` | 15.45 | 4.5 |
| `ink-muted` / `surface` | 6.21 | 4.5 |
| `ink-muted` / `surface-soft` | 5.71 | 4.5 |
| `selected` ink `#0B4A66` / `#D7EAF6` | 7.77 | 4.5 |
| `line-strong` / `surface-soft` (control boundary) | 3.23 | 3.0 |
| `line-strong` / `surface` | 3.52 | 3.0 |
| `ring` / `surface` | 4.89 | 3.0 |
| `ring` / `page` | 4.63 | 3.0 |

Dark theme — ink on tint (minimum 4.5):

| Role | Ratio |
| --- | ---: |
| `primary` | 8.32 |
| `success` | 9.62 |
| `attention` | 8.33 |
| `progress` | 8.76 |
| `stopped` | 8.37 |
| `danger` | 8.35 |
| `external` | 7.90 |
| `data` | 8.15 |

Dark theme — surfaces and controls:

| Pair | Ratio | Minimum |
| --- | ---: | ---: |
| `ink` / `page` | 15.81 | 4.5 |
| `ink` / `surface` | 14.14 | 4.5 |
| `ink-muted` / `surface` | 7.91 | 4.5 |
| `ink-muted` / `surface-soft` | 7.08 | 4.5 |
| `selected` ink `#9DD0EA` / `#1B3A4C` | 7.20 | 4.5 |
| `line-strong` / `surface-soft` (control boundary) | 3.12 | 3.0 |
| `line-strong` / `surface` | 3.48 | 3.0 |
| `ring` / `surface` | 7.32 | 3.0 |

## 6. Semantic role assignment

Roles describe feeling, not decoration. A role must not be reused because it "looks nice".

**Order lifecycle** (admin-facing contract; the storefront does not render order status):

| Status | Role |
| --- | --- |
| `new` | `attention` |
| `confirmed` | `primary` |
| `partially_delivered` | `progress` |
| `delivered` | `success` |
| `cancelled` | `stopped` |

`danger` is deliberately **not** used for `cancelled`. A legitimate business outcome must
not be rendered as a system error. `danger` is reserved for errors, validation failures
and destructive actions.

**Progress / completion level** — applied wherever delivered, remaining or outstanding
quantities are shown:

| Value | Role |
| --- | --- |
| delivered quantity = 0 | `stopped` |
| 0 < delivered < ordered | `progress` |
| delivered = ordered, remaining = 0 | `success` |
| remaining > 0, outstanding > 0 | `progress` |

**Operation type** — storefront:

| Operation | Role |
| --- | --- |
| add to order, checkout, submit | `primary` |
| remove line, clear cart, destructive confirm | `danger` |
| WhatsApp contact and price request handoff | `external` |
| price visibility = `request_price` (a human must supply it) | `attention` |
| no variants / nothing actionable | `stopped` |
| toast success | `success` |
| toast failure | `danger` |
| toast informational | `data` |

## 7. Style contract

**Shapes.** `--radius-control: 10px` (buttons, inputs, chips),
`--radius-card: 20px` (cards, panels, dialogs), `--radius-panel: 30px` (large surfaces).
Pills remain fully rounded.

**Elevation.** Soft, diffuse, low-opacity. No hard offset or "retro" borders.

| Token | Value |
| --- | --- |
| `--shadow-soft-sm` | `0 1px 2px rgb(16 34 43 / 0.05), 0 1px 3px rgb(16 34 43 / 0.04)` |
| `--shadow-soft` | `0 2px 12px rgb(16 34 43 / 0.06)` |
| `--shadow-soft-lg` | `0 8px 30px rgb(16 34 43 / 0.08)` |

Cards use a 1 px `--color-line` stroke plus `--shadow-soft`. Controls use a 2 px
`--color-line-strong` boundary. Elevation communicates hierarchy; it is not decoration.

**Borders.** Separators are 1 px `--color-line`. Controls are 2 px `--color-line-strong`
so the boundary reaches the 3 : 1 non-text minimum.

**Motion.** Only `transform` and `opacity` are animated.

| Case | Duration / easing |
| --- | --- |
| hover, colour and shadow | 200 ms ease-out |
| focus ring | immediate (never animated) |
| entry: fade + `translateY(16px → 0)` | 420 ms ease-out |
| list stagger | 80 ms between items |
| page transition | fade, 200 ms |

**Density, variance, motion dials.** 5 / 4 / 4. Balanced spacing, moderate variance,
subtle motion.

**Typography.** Arabic-first, so `IBM Plex Sans Arabic` remains the sans and UI face;
`Reem Kufi` remains the display face. The alpha document's Latin-only system-UI stack was
rejected because it cannot render Arabic consistently across platforms. `JetBrains Mono`
is used for codes and metadata (`product_code`, order numbers). The retro display faces
(`Lalezar`, `Archivo Black`) are removed.

Scale: body 16 px / 1.6, small 14 px, UI labels 14 px weight 500, H1 2.25 rem weight 700,
display `clamp(2.5rem, 5vw, 4rem)`. Numerals that represent quantities or codes are
`tabular-nums`.

**Components.**

- Primary button: 10 px radius, `primary` CTA fill, 200 ms hover with an 8 % darken and a
  soft lift, `-1px` translate on active, weight 600. No outer glow.
- Secondary button: outline, 1.5 px muted border, ink text, subtle background fill on hover.
- Card: 10–20 px radius, surface background, 1 px `--color-line` stroke, `--shadow-soft`.
- Input: label above the control, never a floating label. 2 px `--color-line-strong`
  boundary, focus ring 2 px `--color-ring` with 2 px offset. Errors render below the field
  in `danger` with `role="alert"`.
- Navigation: surface background; the active item uses a `primary` indicator and weight 500.
- Skeletons: shimmer matching component dimensions. No circular spinners for content.
- Empty state: icon composition plus descriptive text and an action.

**Do not.** Use emoji as icons. Use pure black. Exceed 80 % saturation. Use equal-width
three-column feature layouts (use asymmetric or zig-zag). Use `h-screen`. Ship a
colour-only signal. Hide the focus ring.

**Layout.** Mobile-first. All multi-column layouts collapse below 768 px and no viewport
may scroll horizontally. Containment is `max-width: 1280px` centred with 16–24 px side
padding. Feature and step grids are asymmetric (`lg:grid-cols-[1.3fr_1fr_1fr]`), never
three equal columns. Grids of content items (products, categories) may use 2/3/4 columns
because they are lists, not feature layouts. Full-height containers use
`min-h-[100dvh]`, never `h-screen`.

Z-index contract: base `0` / sticky nav `100` / overlay `200` / modal `300` / toast `500`.

## 8. DEFERRED — Admin (Phase B) colour contract

**Not implemented. Do not apply this section in Phase A.** Phase A is storefront-only and
must not modify `app/Filament/**` or `app/Providers/Filament/AdminPanelProvider.php`.

When Phase B is approved, the admin panel must register these Filament colour names so
that the semantic vocabulary is identical across both surfaces:

| Filament colour name | Scale source | Used for |
| --- | --- | --- |
| `primary` | `primary` role, deep navy `#0B4A66` | primary admin actions |
| `success` | `success` role | confirm, deliver all, complete |
| `attention` | `attention` role | `new` orders, needs attention |
| `progress` | `progress` role | partial delivery, outstanding quantity |
| `stopped` | `stopped` role | `cancelled`, inactive, archived |
| `danger` | `danger` role | destructive actions and errors only |
| `data` | `data` role | import and export actions |
| `external` | `external` role | WhatsApp |
| `info`, `warning`, `gray` | alias to `primary`, `attention`, and a slate-tinted gray | keeps Filament internals on-vocabulary |

Filament v4 resolves AA-compliant shades automatically for both badges
(`BadgeComponent` selects a text shade at 4.5 : 1 against the tint) and solid buttons
(`ButtonComponentColorMap` selects the lightest background that still yields 4.5 : 1 text),
so the scales above are sufficient without custom CSS.

Phase B must also add `OrderStatus::color()` and `OrderStatus::icon()` as the single source
of truth, replacing the four conflicting inline status maps currently present in the admin.

## 9. Superseded values

The following are no longer valid anywhere in the storefront:

- `#87CEEB`, `#FFB6C1`, `#90EE90` as text, link, focus or CTA colours.
- The Phase 07 retro brand tokens `#C91424` crimson, `#8F0808` burgundy, `#073F57` navy,
  `#75A9C7` powder and `#FFF0D3` cream as the storefront palette.
- Hard-offset "retro" shadows (`3px 3px 0`) and the `--shadow-retro*` tokens.
- `--radius-control: 12px` / `--radius-card: 16px` (replaced by 10 px / 20 px).

The `#1D4ED8` `theme-color` meta value is also superseded; browser chrome follows
`--color-page` in light mode and the dark page value in dark mode.

## 10. Verification

Contrast values in section 5 are reproducible with the standard WCAG relative-luminance
formula (`(L1 + 0.05) / (L2 + 0.05)` after sRGB linearisation). Any change to a token in
this document requires re-measuring the affected pairs before it is accepted.