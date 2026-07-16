# UI Design Rules

> **Rule of Thumb:** Every surface must feel absolutely stunning and premium — mimicking the tactile, liquid fluidity of Apple iOS and top-tier SaaS dashboards. This is the acceptance bar for any new view, module, or feature.
>
> **This is the single source of truth for UI conventions in this repo.** There used to be a second, near-duplicate copy at `_artifact/ui_conventions.md` — it drifted out of sync (wrong font, stale component patterns) because two files were trying to describe the same system. That file is now just a pointer back here. If you update the design system, update **this file only**.

---

## 0. Reuse the component library — do not hand-roll

Before writing markup for a dropdown, modal, stat tile, empty state, or skeleton loader, check `resources/views/components/he-*.blade.php` first. Five components exist specifically so nobody has to re-copy ~20–60 lines of Alpine/CSS per page ever again:

| Component | Use for | Backed by |
|---|---|---|
| `<x-he-select>` | Any dropdown that used to be a raw `<select>` | `.he-select-*` classes in `_premium.scss` |
| `<x-he-modal>` | Any teleported overlay modal | `.custom-overlay-*` classes |
| `<x-he-stat-tile>` | Icon + value + label stat card (dashboards, summary rows) | `.bento-card` |
| `<x-he-empty-state>` | Any list/table with zero rows | `.empty-state` |
| `<x-he-skeleton>` | Placeholder for async/heavy content while it loads | `.skeleton` |

Full usage for each is in section 6 below. **If a page's need doesn't fit one of these** (e.g. a select needs type-ahead search over hundreds of options, or a dropdown's value must drive live client-side filtering via an external Alpine scope), it's fine to hand-write Alpine for that specific case — that's a real, different requirement, not "reinventing the wheel." Just don't hand-roll a *plain* dropdown/modal/stat-card/empty-state/skeleton when the component already does the job.

---

## 1. Liquid Motion Standard & iOS Smoothness

**Nothing toggles on/off instantly.** State changes are never abrupt. An element must never pop, snap, or hard-swap between shown/hidden, active/inactive, or one value and the next.

- **Easing:** Everything eases (`var(--ease-out-expo)` = `cubic-bezier(0.16, 1, 0.3, 1)`, or `var(--ease-spring)` for a bouncier feel) between states — fade, scale, slide, or height-reveal. Treat an instant display flip as a bug.
- **Page Entrances & Staggers:** Every page's root wraps in `.page-enter`. Lists and grids that cascade in wrap their container in `.stagger` — every direct child automatically fades up with an incrementing delay (`.stagger > *:nth-child(1..5)`, capped after that). Use it the same way on every list in the app; don't invent a new per-page keyframe (`cascadeIn`, `fade-up`, etc. have all existed as one-off duplicates in the past — don't add another).
- **Manual stagger fallback:** For items that aren't direct siblings of one `.stagger` wrapper, apply `.stagger-1` through `.stagger-5` directly to each element instead.
- **Micro-Motion (The "Floating" Feel):** Hovering over cards, avatars, or tiles applies a subtle lift and a glowing drop shadow. `.bento-card:hover`, `.card-premium:hover`, and `.glass-tile:hover` all do this already — reuse them rather than writing new hover CSS.
- **Click/Press State:** Any clickable element that should visibly "push" gets the `.tactile-btn` class (`transform: scale(0.97)` on `:active`). This is centralized in `_premium.scss` — don't redefine it per page.
- **Navigation Fluidity:** Use the "Hybrid Active-Expansion" pattern for sidebars. A sleek, animated indicator (like a blue pill) slides vertically to the active link instead of instantly snapping.

---

## 2. Ultra-Premium Aesthetics

### 2.1 Glassmorphism & Translucency

- **Floating Headers & Sticky Surfaces:** Topbars and sticky elements use `.he-topbar` (translucent background + `backdrop-filter: blur(16px)`), defined once in `_premium.scss`.
- **Grounded Layouts:** Backgrounds are very light (`--he-bg-canvas: #f8fafc`) while cards and floating tiles are pure white (`--he-bg-surface: #ffffff`) with extremely soft borders.

### 2.2 Color Palette (the one canonical set — defined once, in `_premium.scss`'s `:root`)

Move away from flat, generic colors. Vibrant, high-contrast neon accents for gradients, glows, and hero elements.

```css
:root {
	/* Primary & Accents (Neon Purple/Indigo Vibe) */
	--he-primary: #4f46e5; /* Vibrant Indigo */
	--he-primary-hover: #4338ca;
	--he-primary-soft: rgba(79, 70, 229, 0.1);

	--he-accent: #9333ea; /* Neon Purple */
	--he-accent-hover: #7e22ce;
	--he-accent-soft: rgba(147, 51, 234, 0.1);

	/* Gradients */
	--he-gradient-mesh: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%);
	--he-gradient-pop: linear-gradient(135deg, #4f46e5, #9333ea);

	/* Semantic */
	--he-success: #10b981;
	--he-success-soft: #d1fae5;
	--he-warning: #f59e0b;
	--he-warning-soft: #fef3c7;
	--he-danger: #ef4444;
	--he-danger-soft: #fee2e2;
	--he-info: #0ea5e9;
	--he-info-soft: #e0f2fe;

	/* Surface & Background */
	--he-bg-canvas: #f8fafc;
	--he-bg-surface: #ffffff;
	--he-bg-surface-raised: #f1f5f9;

	/* Text */
	--he-text-main: #0f172a;
	--he-text-muted: #64748b;
	--he-text-inverse: #ffffff;

	/* Shadows, Radii, Easing */
	--he-shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
	--he-shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
	--he-shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
	--he-shadow-float: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
	--he-radius-sm: 6px;
	--he-radius-md: 10px;
	--he-radius-lg: 16px;
	--he-radius-full: 9999px;
	--ease-spring: cubic-bezier(0.175, 0.885, 0.32, 1.275);
	--ease-out-expo: cubic-bezier(0.16, 1, 0.3, 1);
}
```

**Never redeclare these values anywhere else** — not in a page's inline `<style>`, not in a second `:root` block. `_premium.scss` used to have its *own* competing `:root` with different colors (navy/teal instead of indigo/purple), silently overridden by an inline `<style>` block in the layout — that's exactly the kind of split-source-of-truth bug that made the app feel inconsistent even on pages that were "using the premium system." Fixed as of 2026-07: `_premium.scss` is now the only place these are declared, and the layout's inline `<style>` block was deleted entirely.

### 2.3 Typography (Premium & Legible)

Use **Plus Jakarta Sans** as the primary font for that crisp, premium, high-end SaaS look. (This superseded an earlier Inter/DM Sans plan — if you see either mentioned anywhere else, that reference is stale.)

- **Headings:** Bold (`fw-bold`), tightly letter-spaced (`letter-spacing: -0.5px`) for large text.
- **Microcopy / Labels:** Small, muted, heavily letter-spaced, uppercase for categories (e.g. "TOTAL STUDENTS").
- **Numbers & Currency:** Tabular/bold styling so monetary values are easily scannable. Always format money through `hostelease_money()`.
- **Type scale** — defined once in `_premium.scss`, use these instead of arbitrary `font-size` values:

```css
--he-text-xs: 0.7rem;    /* microcopy, uppercase tracked labels */
--he-text-sm: 0.875rem;  /* body text inside cards/tables */
--he-text-base: 1rem;    /* default */
--he-text-lg: 1.125rem;  /* emphasized numbers */
--he-text-xl: 1.5rem;    /* bento-card values, big stats */
```

---

## 3. Predefined UI Elements (The "HostelEase Arsenal")

Whenever building a new page or widget, reach for these established premium patterns before inventing a new one. All are defined once in `resources/scss/_premium.scss`.

1.  **The "Hero Mesh" Card:** Deep navy/purple background (`var(--he-gradient-mesh)`), abstract radial gradient overlays, glowing white text. For the single most important metric on a page (e.g. Total Income, Active Subscription).
2.  **`.bento-card` (via `<x-he-stat-tile>`):** The one canonical stat-tile — icon badge, big value, muted label. Works standalone or inside a `.bento` grid (fixed row-height, `.c2`/`.r2` span modifiers, `canvas`/`.bento-list` helpers for charts mixed with stats). Add `variant="hero"` for the gradient-filled hero variant.
3.  **`.glass-tile`:** Hover-lift white card utility for richer/custom card layouts that don't fit the fixed icon+value+label shape of `.bento-card` — e.g. a staff profile card, or a table's wrapper card. Pairs with `.tile-icon-wrapper` for a glowing circular icon badge.
4.  **`.stat-card-glass` + `.stat-card-{visitors,complaints-open,complaints-prog,complaints-res}`:** Colored gradient hero-style stat tiles for when a number's *color itself* carries meaning (e.g. red = open complaints, green = resolved). Distinct from `.bento-card` — don't force these into the white bento shape.
5.  **Timeline Feed (`.timeline-item`):** A vertical line connecting floating circular icons, with soft colored badges on the right side. For activity logs, alerts, schedules.
6.  **`.wizard-steps` / `.wizard-step`:** Numbered step indicator with connector lines, active/done states.
7.  **`.he-tabs` / `.he-tab`:** Underline-indicator tab navigation (see Finance Board, Front Desk, Staff Board for reference).
8.  **`.due-card`:** Mobile-friendly alternative to a table row for a single charge/due.
9.  **Liquid Radial Progress (Apple Watch Rings):** Custom SVG circles with `stroke-dashoffset` CSS animations and bright neon gradient strokes. NEVER use standard Chart.js doughnuts.
10. **Glowing Area Charts:** Chart.js line chart with high `tension`, a bright primary border, and a canvas `createLinearGradient` fill. Disable X-axis gridlines.
11. **Pill Search Bars:** Fully rounded corners, transparent by default, solid white with a glowing primary border on focus.
12. **`.fab`:** 56px floating circular action button, mobile-only (`display:none` above 768px).

---

## 4. Dropdowns — use `<x-he-select>`, don't hand-copy the markup

There are two visual variants, both implemented by the same component (`resources/views/components/he-select.blade.php`):

1. **Inline Status Dropdown** (`variant="status"`) — a pill-shaped button colored by the current status (`bg-{color}-subtle text-{color}`). Opens a floating list where the active option has a checkmark.
2. **Category / Filter Dropdown** (`variant="filter"`, the default) — a floating pill with a soft-colored circular icon, an uppercase micro-label, and the selected value. Opens a `list-group`-style menu.

By default, picking an option **submits the closest `<form>`** (matches the pre-existing filter-bar convention across the app — GET-filter bars reload with the new value). Pass `:submit="false"` when the select is just one field in a larger form that has its own submit button (e.g. inside a create/edit modal).

**When *not* to use it:** if a dropdown's value needs to drive live client-side filtering bound to an external Alpine `x-data` scope (e.g. Finance Board's status filter, which uses `x-model="status"` on the page's own component), or if it needs type-ahead search over a long list (student pickers use a richer `searchableSelect` Alpine helper for this), leave it as a raw `<select>`/custom Alpine block — retrofitting those into `<x-he-select>` would change working behavior, not just appearance. A handful of these exist intentionally across the app; they are not an oversight.

### 4.1 Every dropdown/popover must use `@click.outside.capture`, not plain `.outside`

Any hand-rolled dropdown, popover, or inline picker (`x-show="open" @click.outside="open = false"` on the panel/wrapper) must use `@click.outside.capture`. Plain `.outside` is a bubble-phase listener on `document`; a lot of our modals wrap their `<form>` in `@click.stop` so clicking inside doesn't close the modal — and that `stopPropagation()` swallows the click before it ever reaches `document`, so the dropdown silently never closes when you click another field in the same modal. It also means two dropdowns in the same modal can end up open at once, since dropdown A never hears about the click that opened dropdown B.

`.capture` fixes both: it runs during the capture phase, before the click reaches its target at all, so `stopPropagation()` called later during bubbling can't block it — and because capture goes top-down, dropdown A's outside-close fires *before* dropdown B's own `@click="open = true"` handler runs, so opening a new one correctly closes any other that's open, automatically, with no extra event bus needed.

```blade
<div x-show="open" @click.outside.capture="open = false" ...>
```

`<x-he-select>` already does this (see its docblock). Apply the same modifier to every new hand-rolled dropdown, and fix old ones opportunistically when you're already touching that file.

### 4.2 Dropdown stacking checklist — verify the menu actually paints on top, EVERY time

This bug has recurred on every page that placed a dropdown above a list (W2 Property Board,
W5 Front Desk — twice in one phase). The menu's own `z-index: 1050` is **not enough**, because
z-index only competes *inside the nearest ancestor stacking context* — and this codebase creates
stacking contexts constantly: entrance animations (`.page-enter`, `.stagger-*`, any inline
`animation: fadeUp`), `opacity-*` utilities, `backdrop-filter` glass, `transform`/`will-change`.
If any ancestor of the dropdown forms a context that its *sibling* list rows don't share, the rows
paint over the open menu and it looks like the dropdown "renders behind the UI".

**Whenever you add, move, or restyle a dropdown/popover (including `<x-he-select>` and
`<x-he-picker>`), you MUST verify with the menu OPEN that it paints above everything below it —
in the browser, not by reading the CSS.** Then apply whichever fixes apply:

1. **Row above a list** → give the dropdown-owning row an explicit raised stacking order:
   `position: relative; z-index: 30;` (page-local class, e.g. `.fd-filter-row` on Front Desk).
   This lifts the whole row — deterministic regardless of which ancestor forms a context.
2. **Dropdown inside a card in a repeated list** (e.g. per-row status menus) → raise the *open*
   card above its siblings: `:style="\`position: relative; z-index: ${open ? 50 : 1}\`"`
   (Front Desk complaint cards are the reference).
3. **Ancestor with `overflow: hidden`** clips instead of stacking — `.panel-card` clips by
   design. A card that hosts a dropdown must override it locally (`overflow: visible`) *if* it
   doesn't use `.panel-head` (whose corner treatment is why the clip exists).
4. Entrance animations must end on `transform: none` (not `translateY(0)`) — a retained non-none
   transform makes the context (and containing block) permanent. `fadeUp` in `_premium.scss` is
   already correct; don't regress it, and don't write new keyframes that end on an identity
   transform. This still doesn't remove contexts *during* the animation, which is why fixes 1–2
   are required anyway.

> **§4.2 is about paint order; §4.7 is about placement.** They are the same family and a dropdown
> owes you both checks: it must paint *on top of* its neighbours **and** open into space that
> already exists. Verify both with the menu open, in the browser.

### 4.3 A filter must never reload the whole page — refresh only the list it affects

**Rule: no filter, search, sort, or tab control may trigger a full page navigation.**
`onchange="this.form.submit()"` is banned in new work and gets fixed on sight in old work.

Changing a filter is a request to re-render *some rows*. A full reload answers it by destroying
and rebuilding the entire document — so everything not encoded in the HTML is lost with it:

| Lost on full reload | Consequence |
|---|---|
| Scroll position | You're thrown back to the top of a long list |
| Every Alpine scope | Open dropdowns close, typed search text clears, the active tab resets |
| Focus | Keyboard/screen-reader users lose their place |
| Entrance animations | `.page-enter` / `.stagger` replay, so the page visibly re-lurches |

Plus a blank gap while the document is swapped.

**Use the `data-fragment` helper** (`initFragmentForms()` in `resources/js/app.js`). The form stays
a plain GET form — this is progressive enhancement, so it still works with JS off:

```blade
<form method="GET" action="{{ route('...') }}" data-fragment="#visitor-list"> ... </form>

<div id="visitor-list"> {{-- server-rendered rows --}} </div>
```

It intercepts submit, fetches the same URL, lifts the target out of the response, swaps only that
element's `innerHTML`, and `pushState`s the URL so the view stays shareable and Back works.
`data-fragment` accepts several comma-separated selectors when one filter drives more than one
region (e.g. a list plus its count badge).

**Pagination and anchors participate too** (added W6.1, Finance is the reference):

- Wrap a paginated list in an element with `id` + `data-fragment-container` — Laravel's
  `.page-link` anchors inside it are intercepted and swap that container in place, so paging
  never reloads. `->withQueryString()` on the paginator is mandatory or page 2 drops the filter.
  Use distinct page params (`paginate(15, ['*'], 'inv_page')`) when two lists share a page.
- `<a data-fragment="#a, #b">` gives any anchor form-like swap semantics — the Finance "clear
  filters" chip uses this. Modified clicks (ctrl/cmd/shift/middle) pass through untouched so
  open-in-new-tab still works.
- **Server-side search needs a debounce** on the input (`@input.debounce.450ms=
  "$el.form.requestSubmit()"`) — every keystroke is a round-trip; the AbortController makes
  out-of-order responses safe but not free.

**Find every element the filter's own query string drives, not just the list.** A filter often
also renders a small readout of its *own current state* — a "16 Jul" chip next to a date picker, an
active-filter count, a "clear" button that only appears once something is set. If that readout sits
outside the list target, a fragment swap that only names the list will silently stop updating it —
the list refreshes correctly, but the chip/badge/clear-button freezes at whatever it showed on the
last full load (found on Front Desk's mobile date chip: the "16 Jul" label and its clear ✕ live in
the filter bar, not `#visitor-list`, so they never updated once the list alone was the swap target).
Audit the whole filter row, not just the results container, and add every such readout to
`data-fragment` too — wrapped in an **always-present** element if the readout itself appears/
disappears (e.g. the clear button only renders `@if(request('date'))`): the swap only updates an
element it finds on *both* sides, so a container that doesn't exist yet in the current DOM can't
receive one that exists in the fetched DOM. Wrap the conditional pieces in one persistent
`<span id="...">`, `display: contents` so it doesn't add a layout box, and target that id.

Non-obvious things it already handles — don't re-solve these per page:

- **`requestSubmit()`, never `submit()`.** The DOM's `form.submit()` does not fire a `submit`
  event, so it silently bypasses this (and `data-confirm`, and native validation) and hard-navigates.
  `<x-he-select>` already calls `requestSubmit()`; any hand-written `onchange` must too.
- **Out-of-order responses.** Two quick filter clicks can resolve backwards and paint the wrong
  list. Each target group keeps one `AbortController` and cancels the superseded request.
- **No loading signal.** A partial refresh has no tab spinner or white flash, so a slow filter
  looks frozen. Targets get `.is-fragment-loading` (dim + input-blocked) during the swap.
- **Failure.** On a network/HTTP error it falls back to the full navigation the user would have
  had anyway, rather than stranding them on a stale list.
- **Alpine re-init.** Alpine observes DOM mutations and initialises swapped-in nodes itself. Keep
  the target *inside* the page's existing `x-data` root so new rows resolve parent scope (that's
  how Front Desk's client-side search keeps working on freshly-swapped rows).

**What this does and does not save.** Be accurate about the win: the helper requests the *same
full page* and extracts the target from the response, so **the server still renders the whole
page** — the saving is on the client (state, scroll, focus, no flash, no animation replay), not on
the server. That's the part users actually feel, and it's why this is worth doing everywhere. If a
page ever gets heavy enough that the server-side render is the bottleneck, the upgrade is small and
local: have the controller return just the partial when `$request->ajax()`, and the same
`data-fragment` markup keeps working unchanged (the JS already sends `X-Requested-With`).

**When a full reload is still correct:** anything that legitimately changes the whole page —
switching branch/tenant, login/logout, or a mutation that invalidates the chrome (sidebar counts,
subscription state). Filtering a list is never one of those.

---

### 4.4 The attention ring — one gesture for "deal with this control"

**A form must never fail silently, and must never let the browser do the telling.** Two situations,
**one** motion, so the product teaches it once (`.he-ring` in `_premium.scss`, driven by
`window.heRing(elements, tone)` / `ringElements()` in `app.js`):

| Tone | Meaning | Fired by |
|---|---|---|
| `.he-ring--primary` | **Dependency** — "answer this first and the thing you just reached for will work". Nothing is wrong; the order is backwards. | By hand, from the dependent control's click handler. |
| `.he-ring--danger` | **Mandatory field left empty** at submit. | Automatically, for any `<form data-ring-required>`. |

**Every dynamic form with a dependency owes the user a ring.** Whenever control B is disabled,
empty, or inert *because* control A hasn't been answered — a picker that can't filter until a type
is chosen, an amount that can't resolve without a plan, a submit that can't run without a selection
— reaching for B must ring A. The alternative is a control that does nothing when clicked, which
users read as broken software.

Rules, learned the hard way:

- **Never `:disabled` the dependent control.** A disabled button fires no click, so there is
  nothing to hang the ring on, and the user is told nothing. Make it *look* locked
  (`.is-locked`) and *act* as a pointer to what's missing.
- **Ring only — never animate `transform`.** An animation that ends on a retained transform leaves
  a permanent stacking context, and the next dropdown opens behind its neighbours (§4.2 all over
  again). `box-shadow` paints outside layout and costs nothing.
- **Re-applying the class does not restart a CSS animation.** Ring, then ring again 200 ms later,
  and nothing visibly happens — the second click looks broken. `ringElements()` removes the class,
  waits a frame, and re-adds it. Use the helper; don't hand-roll a boolean flag.
- **Stagger a group** (`--he-ring-delay`, 90 ms steps) so several rings read as one sweep.
- **`prefers-reduced-motion`** keeps the border (the meaning) and drops the pulse (the motion).

**Mandatory fields: `<form data-ring-required>` and nothing else.** No `novalidate`, no per-field
wiring. Why it hooks `invalid` rather than `submit`: when constraint validation fails **the browser
never fires a submit event at all**, so a submit listener would sit there doing nothing. `invalid`
fires once per failing control just before the bubble, `preventDefault()` suppresses the bubble,
and one capture-phase listener covers every form on the page — including the ones Alpine teleports
into `<body>` long after boot. All empty fields ring together, staggered; the first gets focus. The
native bubble points at one field at a time, is unstyleable, and vanishes on the next click.

---

### 4.5 Filter bars are ONE row — the search flexes, the filter collapses

A search box and its filters belong on the **same row at every width**. Two traps:

- **`.he-search` sets `width: 100%`.** As a flex item that resolves `flex-basis` to 100%, so the
  search claims the whole line and every filter beside it wraps to a second row. Use
  **`.he-search--inline`** (`flex: 1 1 240px; width: auto; min-width: 0`) whenever the search shares
  a row. A flex item must size from `flex`, not `width`.
- **A filter pill with a text value cannot share a 375px row with a search box.** Pass
  **`icon-only-mobile`** to `<x-he-select>`: below `md` the trigger collapses to a square icon.
  Give every option its own `icon` — the trigger's icon then *is* the readout, which is the whole
  reason the text can go. The menu still spells every option out, so nothing is guessed, and
  `.is-filtered` borders the trigger when a value is active (an icon alone can't say "a filter is
  on").

**The clear X belongs inside the search field** (`.he-search--clearable` + `.he-search__clear`), not
floating beside it as another chip, and it clears **the search only** — every other filter clears
via its own "All …" option. It's client state (`x-model` + `x-show`), never server-rendered:
`.he-search` must stay *outside* the swapped fragment, because swapping the input would drop typing
focus mid-keystroke.

---

### 4.6 Pagination is owned by the design system

`Paginator::defaultView('vendor.pagination.premium')` is set in `AppServiceProvider` — **every**
`->links()` in the app renders `resources/views/vendor/pagination/premium.blade.php`
(`.he-pager`). Do not hand-roll a pager and do not reach back for Bootstrap's.

- **Keep `class="page-link"` on the anchors.** The fragment router keys off exactly that class to
  paginate without a reload (§4.3). Rename it and every paginated list silently reverts to full
  reloads.
- **Keep the hrefs real URLs** — that's what makes ctrl/middle-click open a page in a new tab, and
  what the no-JS fallback follows.
- `defaultSimpleView` deliberately still points at Bootstrap: the premium view needs
  `$elements`/`total()`, which `simplePaginate` doesn't provide.

---

### 4.7 A dropdown opens into the space that EXISTS — it never makes the page grow

**A menu must never cause a scrollbar.** Not vertical, not horizontal. An absolutely-positioned
menu that runs past the viewport edge extends the *document*, and the browser answers with a
scrollbar: vertically that's merely ugly, horizontally it drags the entire layout sideways and can
leave the menu itself off-screen and unreachable. The menu is a transient overlay. It does not get
to decide the size of the page.

So placement is **measured at open time, never hardcoded**:

- No room on the **right** → open to the **left** (right-align to the trigger).
- No room **below** → open **above**.
- Fits **neither** way → pin inside the viewport, cap `max-width`/`max-height`, and let the **menu**
  scroll — never the page.
- These are examples, not the whole list. The rule is the principle: *fit the space you're in.*

`window.hePlaceMenu(trigger, menu)` (`placeMenu()` in `app.js`) implements it and is wired into
`<x-he-select>` already. **Any hand-rolled dropdown, popover, or panel must call it too** — see
`.he-picker` in `admin/finance/index.blade.php`.

Non-negotiables when wiring it up:

- **Call it in `$nextTick` after opening.** A menu with `display: none` measures 0×0, so a call
  before it's visible silently places it wrong.
- **Reset before measuring.** `placeMenu` clears `top/bottom/left/right/max*` first: measuring a
  menu still carrying the *last* open's overrides compounds the decisions and the menu walks off
  down the page.
- **It re-places on scroll and resize** while open, and unsubscribes itself once hidden. Don't add
  your own listeners.
- **This replaces media-query placement.** A `@media (max-width: …) { right: 0 }` guess is not a
  measurement — it's right by luck at one width and wrong at every other.

Mobile is not exempt. It's where this hurts most: the viewport is small, the trigger is often hard
against an edge, and a horizontal scrollbar on a phone is a broken page.

---

### 4.8 Fit or drop — a label never wraps and never half-clips

A button whose text can't fit has three options; only one is acceptable:

1. Wrap to two lines — breaks the row's height and the layout around it. ✗
2. Clip mid-glyph — "₹ Collect ₹1,2…" reads as a bug. ✗
3. **Drop the optional part** — "₹ Collect ₹1,25,000.00" → "Collect". ✓

Mark it up as `<button data-fit-label>` with the droppable part in a child the CSS hides under
`.is-fit-short`. `initFitLabels()` (`app.js`) measures each one with a `ResizeObserver` and toggles
the class.

- The element **must** be `white-space: nowrap; overflow: hidden` — `scrollWidth > clientWidth`
  only means "doesn't fit" while wrapping is forbidden and overflow is allowed.
- **Measured, not guessed.** A media query cannot know how long an amount is; ₹1,000 and ₹1,25,000
  don't need the same room, and the same breakpoint is right for one and wrong for the other.
- Fragment swaps replace whole lists, so the new buttons need re-scanning: the swap dispatches
  **`he:fragment-swapped`** on `document` (§4.3) and `initFitLabels` listens. Any other plain-JS
  enhancement that decorates list rows should listen to it too — Alpine re-initialises swapped
  nodes by itself, but nothing else does.

---

### 4.9 In-content layout measures its CONTAINER, not the viewport

**With a fixed sidebar, viewport media queries lie.** A 1043px window is a ~770px content column,
so `col-md-4`, `d-lg-grid`, and every `@media (min-width: 992px)` rule laid out for space that
doesn't exist — that is exactly how the owner got wrapped-mid-digit stat values and crushed rows
*on a desktop*. Any layout inside the content column that changes shape with available width uses
**container queries**:

- **`.he-adaptive`** (`_premium.scss`) declares the measuring container — put it on the list/grid
  wrapper (the fragment container is usually the right element).
- **`.he-cq-wide` / `.he-cq-card`** are the two renderings of a list row: the wide layout is always
  a grid, the card is the bespoke phone layout; they flip at the design-wide **640px container**
  tier. Never gate these two layouts with `d-lg-*` again — that was the bug.
- Page row-grids add their own upper tier (e.g. `@container (min-width: 880px)` for one-line rows).

**Warning that earns its place in §4.2:** `container-type` applies layout containment, which
**creates a stacking context**. Never wrap a dropdown-OWNING region (filter bars) in a container —
only the content the dropdown floats above (lists, tile grids).

Viewport media queries remain correct for things that genuinely follow the *window*: the sidebar
itself, the FAB, the page title scale, modal sizing.

### 4.10 Nothing crushes — floors, nowrap figures, reflow

Three rules, one principle: **when space runs out, the layout changes shape; it never mangles its
content.** A name shrunk to "N…", a phone number wrapped one character per line, "₹104,500.0⏎0" —
every one of these is a layout that chose crushing over reflowing, and every one is a bug.

1. **Every truncating text zone gets a floor** (`minmax(220px, 1fr)`, never `minmax(0, …)` on a
   column that carries words) — and every text line that may truncate carries `text-truncate`
   explicitly. A missing nowrap is how a phone number becomes a vertical strip of digits.
2. **Figures never wrap.** Money/marks/counts are `white-space: nowrap` + tabular numerals. Where
   a figure must fit variable space, its size is FLUID — `clamp(min, Ncqi, max)` against its own
   container — so the number shrinks as a whole. (Pair with §4.8: where shrinking isn't enough,
   drop the optional part.)
3. **When floors don't fit, REFLOW — rows can expand.** A one-line row becomes a two-line row
   (identity on top, data below, actions anchored right across both) via a container tier, then
   the phone card below that. Stats become compact rows instead of stacked screen-filling cards
   (`.he-stats`). More lines is a design decision; crushed content never is.

Canonical implementations to reuse, not re-invent: **`.he-stats`** (adaptive stat tiles — see §6),
**`.he-page-head`** (title grows on phones; ONE desktop action, `d-none d-md-inline-flex`, and the
FAB below md — a header button that wraps under the title is a bug), and the **three-tier row**
(wide grid ≥880 container / two-line reflow ≥640 / phone card below — Finance `_invoices` is the
reference).

---

## 5. Standard Overlay Modals — use `<x-he-modal>`

Whenever implementing a modal/dialog in this codebase, use `<x-he-modal>` (`resources/views/components/he-modal.blade.php`) rather than hand-copying the markup below. It renders exactly this structure:

### 5.1 Modal Visual Design Spec

1. **Backdrop**: Smooth dark blur (`rgba(15, 23, 42, 0.6)` with `backdrop-filter: blur(8px)`).
2. **Modal container**: Rounded corners (`var(--he-radius-lg)`), heavy soft shadow (`var(--he-shadow-float)`), limited max-height (`85vh`).
3. **Form Flexbox**: `display: flex; flex-direction: column; overflow: hidden;` so header/footer stay locked while the body scrolls.
4. **Body**: Soft background (`#fafafa`), `overflow-y: auto`.

### 5.2 Using `<x-he-modal>`

Must be placed inside a parent element that already has an Alpine `x-data` scope declaring the boolean named by the `open` prop:

```blade
<div x-data="{ addModalOpen: false }">
    <button type="button" @click="addModalOpen = true">Add</button>

    <x-he-modal open="addModalOpen" title="Add Something" icon="plus"
        :action="route('admin.things.store')">
        <div class="mb-3">
            <label class="form-label fw-bold small text-uppercase">Name</label>
            <input type="text" name="name" class="form-control bg-light" required>
        </div>
    </x-he-modal>
</div>
```

Default footer is Cancel + Save; override with a named `footer` slot when you need different actions. This matches the existing convention (see `admin/expenses/index.blade.php`'s `expenseModalOpen` for a page that predates the component but follows the identical scoping pattern) — the trigger button elsewhere on the page just flips the same boolean.

### 5.3 Raw markup (only if you have a reason not to use the component)

```css
.custom-overlay-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1rem; }
.custom-overlay-modal { width: 100%; max-width: 550px; background: var(--he-bg-surface); border-radius: var(--he-radius-lg); box-shadow: var(--he-shadow-float); display: flex; flex-direction: column; max-height: 85vh; transform: scale(0.95); opacity: 0; transition: all 0.3s var(--ease-out-expo); overflow: hidden; }
.custom-overlay-modal.is-open { transform: scale(1); opacity: 1; }
```

Full class list (`.custom-overlay-header/body/footer`) is in `_premium.scss`. Always wrap in `<template x-teleport="body">` to escape parent stacking contexts.

---

## 6. Component Library Reference

### `<x-he-select>`

```blade
{{-- Filter pill, submits the closest form on pick (default) --}}
<x-he-select name="category" icon="tags" label="Category"
    :selected="request('category', '')"
    :options="['' => 'All Categories'] + config('hostelease.expense_categories')" />

{{-- Status pill, no auto-submit (e.g. paired with its own form action) --}}
<x-he-select name="status" variant="status" :submit="false"
    :selected="$complaint->status"
    :options="['open' => ['label' => 'Open', 'color' => 'warning'], 'resolved' => ['label' => 'Resolved', 'color' => 'success']]" />

{{-- One field inside a larger form (modal) — does not auto-submit --}}
<x-he-select name="type" icon="tags" :submit="false" :selected="'fee'"
    :options="['fee' => 'Hostel Fee', 'rent' => 'Monthly Rent']" />

{{-- Sharing a filter row with a search box (§4.5): collapses to a square icon
     below md. Each option's own `icon` becomes the trigger's icon, so the icon
     IS the readout once the text is gone — give every option one. --}}
<x-he-select name="status" icon="filter" icon-only-mobile :selected="$status ?? ''"
    :options="[
        '' => ['label' => 'All Statuses', 'icon' => 'filter'],
        'paid' => ['label' => 'Paid', 'icon' => 'circle-check'],
        'pending' => ['label' => 'Pending', 'icon' => 'clock'],
    ]" />
```

### `<x-he-modal>`

See section 5.2 above.

### `<x-he-stat-tile>`

```blade
<x-he-stat-tile icon="users" label="Total Students" :value="$stats['students']" color="primary" />
<x-he-stat-tile icon="indian-rupee-sign" label="This Month" :value="hostelease_money($income)" variant="hero" />
<x-he-stat-tile icon="bed" label="Occupancy" :value="$pct.'%'" variant="c2" color="success">
    <canvas id="occupancyChart"></canvas>
</x-he-stat-tile>
```

### `<x-he-empty-state>`

```blade
<x-he-empty-state icon="money-bill-trend-up" title="No expenses logged"
    subtitle="Expenses for the selected period will appear here." />
```

### `<x-he-skeleton>`

```blade
<x-he-skeleton rows="3" height="52px" />   {{-- stacked bars --}}
<x-he-skeleton height="240px" />           {{-- one block, e.g. a chart --}}
```

### `.he-stats` (CSS, `_premium.scss`)

Adaptive stat tiles (§4.9/§4.10): side-by-side cards when the container is ≥700px, ONE compact
row-panel when it isn't — which is what a phone, a narrow window, and a sidebar-squeezed desktop
all actually are. Values are nowrap + container-fluid (`clamp(…cqi…)`): they resize, never wrap.
`--he-stats-cols` sets the wide column count; `.he-stat--hero` is the gradient accent slot.

```blade
<div class="he-stats">
    <div class="he-stats__grid" style="--he-stats-cols: 3;">
        <div class="he-stat he-stat--hero">
            <div class="he-stat__head">
                <div class="he-stat__icon" style="background: rgba(255,255,255,0.15); color: #fbbf24;"><i class="fa-solid fa-triangle-exclamation"></i></div>
                <div class="he-stat__label">Total Outstanding</div>
            </div>
            <div class="he-stat__value">{{ hostelease_money($outstanding) }}</div>
        </div>
        <div class="he-stat">
            <div class="he-stat__head">
                <div class="he-stat__icon" style="background: var(--he-success-soft); color: var(--he-success);"><i class="fa-solid fa-sack-dollar"></i></div>
                <div class="he-stat__label">Total Collected</div>
            </div>
            <div class="he-stat__value text-success">{{ hostelease_money($collected) }}</div>
        </div>
    </div>
</div>
```

### `.he-page-head` (CSS, `_premium.scss`)

Title + subtitle left, ONE primary action right — desktop only (`d-none d-md-inline-flex`); phones
get the FAB. `flex-wrap: nowrap` by design: the action never drops under the heading, the subtitle
wraps instead. The title GROWS on phones (2.2rem ≤576px) — the approved mobile scale.

```blade
<div class="he-page-head mb-4">
    <div>
        <h1 class="he-page-title">Finance Board</h1>
        <p class="he-page-sub">Manage invoices, dues, and transactions in one place.</p>
    </div>
    <button type="button" class="btn btn-premium rounded-pill px-4 fw-bold shadow-sm d-none d-md-inline-flex align-items-center" @click="openModal()">
        <i class="fa-solid fa-plus me-1"></i> New Invoice
    </button>
</div>
```

### `.he-icon-btn` / `.he-act-row` (CSS, `_premium.scss`)

The squircle icon button for row actions and the action-row split. Promoted from the Finance
Board's page-local styles when Expenses needed them (W6.2) — don't hand-roll new row buttons.

```blade
<div class="he-act-row">                       {{-- primary action(s) left --}}
    <button class="btn btn-success rounded-pill">Collect</button>
    <div class="he-act-right">                 {{-- secondary/destructive pushed right --}}
        <button class="he-icon-btn" title="Edit" aria-label="Edit"><i class="fa-solid fa-pen"></i></button>
        <button class="he-icon-btn is-danger" title="Delete" aria-label="Delete"><i class="fa-solid fa-trash"></i></button>
    </div>
</div>
```

`he-icon-btn--lg` is the 44px phone variant (minimum touch target); `is-danger` / `is-whatsapp`
recolour the hover. Icon-only buttons **must** carry `title` + `aria-label` — dropping the visible
label must never drop the accessible name.

**Important for anyone editing these component files:** never write a literal `<x-he-*>` self-reference tag inside that component's own Blade comment block, and never nest a `{{-- --}}` comment inside another one. Both cause Blade to compile a live, self-invoking component call from what looks like a comment, which infinitely recurses and crashes with a memory-exhaustion error. Keep component docblocks to plain prose; put runnable examples here in this markdown file instead.

---

## 7. Additional Implementation Rules

1.  **Never Use Vanilla Alerts:** If a user performs an action, trigger a sleek Toast notification (SweetAlert2, already wired in `resources/js/app.js`) that slides in from the top right.
2.  **Data Tables:** Full-width, clean borders (`border-bottom` only on rows), uppercase muted headers. Status columns use soft-pill badges (`bg-success-subtle text-success`).
3.  **Empty States:** Use `<x-he-empty-state>` — never a blank box or raw text.
4.  **Skeleton Loaders:** Use `<x-he-skeleton>` for async/heavy content. Never a spinning wheel on a blank white page.

---

# Git Commits & Amendments Rules

- **Do Not Autocommit**: NEVER run `git commit` automatically without asking the user first.
- **Commit or Amend Choice**: When changes are ready, explicitly ask the user if they want to create a new commit or amend the last commit. Minor fixes should generally be amended to the last commit to avoid cluttering git history with minor/wip messages.
