# HoursAI Frontend — Implementation Plan

Stack: Vue 3 (`<script setup lang="ts">`) · Vite · Vue Router · plain scoped CSS · `fetch`
Design reference (visual only, no code reuse): `menu-app/frontend/apps/management-center-app` — PAR theme (`src/assets/themes/par/theme-settings.scss`).

> The sibling MC app is Vue 2 + Options API + Vuetify + `@menu/admin-ui`. We **do not** import it. We replicate its **look** (color tokens, shadows, type scale, card/tab/input patterns) with our own Vue 3 components.

## Guiding principle — efficiency over fidelity

**We are not reusing components from the sibling project.** The only thing borrowed is the **visual idea** — how things look (colors, spacing, card/tab/button shape, the general PAR feel). All code is **written fresh from scratch** in this repo, in the most efficient way possible, with as few moving parts as we can get away with.

Rules of thumb for every phase:
- **Pixel-perfect ≠ goal.** "Same family of look" is enough. If a feature in the sibling app needs 5 components to render, we do it in 1 if we can.
- **No design-system abstraction tax.** Shared primitives in Phase 2 stay tiny (≤80 lines each). If a component needs 4 props, we don't add a 5th "for later".
- **No premature generalization.** Don't build a "generic table"; build the one we need. Same for forms, modals (we use inline strips, no modals), state libs (none).
- **No extra deps beyond `vue-router`.** No UI kit, no axios, no date library, no icons package — emoji or inline SVG is fine.
- **Skip steps that don't visibly help the demo.** Loading skeletons can be a single gray bar; toasts can be a plain `<div>` that disappears after 3s.
- **Copy tokens, not patterns.** From `theme-settings.scss` we copy color/shadow values into `style.css`. We do **not** copy the SCSS folder structure or utility class system (`u-mb--16`, `o-app__content`, etc.) — write plain CSS in the component that needs it.

## Conventions for the whole plan

- File paths are relative to `frontend/src/`.
- Every component is `<script setup lang="ts">`, scoped CSS, no global state libs.
- All HTTP calls go through composables (no `fetch` in components).
- Design tokens live as CSS custom properties on `:root` in `style.css` (mirrored from `theme-settings.scss`).
- API base URL: `http://localhost:8080/api` — `frontend/.env.local` should be `VITE_API_BASE_URL=http://localhost:8080/api`.
- Definition of done for each phase = visible in browser + manual smoke test against running backend.

---

## Actual API contract (source: `backend/routes/api.php` + controllers, commit `455dfc3`)

`API.md` is the *intent*. The backend code currently differs from it in several places — when there's a conflict, **the code wins** until backend updates. This section is the canonical contract we build against.

### Response shape

- **No `{ "data": ... }` envelope.** Controllers return models/arrays directly. `useApi.ts` returns the raw JSON body. No unwrapping.

### Brands — `apiResource('brands')`
- `GET /brands` → `Brand[]` (each includes `servingTimes`).
- `POST /brands` body: `{ name, slug?, timezone?, active? }` → `Brand` (201).
- `GET /brands/{id}` → `Brand` with `venues`, `menus`, `servingTimes`.
- `PUT|PATCH /brands/{id}` body: any subset of `{ name, slug, timezone, active }`.
- `DELETE /brands/{id}` → 204.

### Venues — **nested under brand**
- `GET /brands/{brand}/venues` → `Venue[]` (each with `servingTimes`).
- `POST /brands/{brand}/venues` body: `{ name, slug?, address?, city?, country?, timezone?, phone?, active? }`.
- `GET /brands/{brand}/venues/{venue}` → `Venue` with `servingTimes` + `orderTypes`.
- `PUT|PATCH /brands/{brand}/venues/{venue}` body: any subset of above.
- `DELETE /brands/{brand}/venues/{venue}` → 204.

### Menus — **nested under brand**
- `GET /brands/{brand}/menus` → `Menu[]` (each with `servingTimes`, ordered by `position`).
- `POST /brands/{brand}/menus` body: `{ name, internal_name?, description?, active?, position? }`.
- `GET /brands/{brand}/menus/{menu}` → `Menu` with `servingTimes`.
- `PUT|PATCH /brands/{brand}/menus/{menu}` body: any subset.
- `DELETE /brands/{brand}/menus/{menu}` → 204.

### Order types — **scoped under venue path, NO active toggle endpoint**
- `GET /order-types` → global list of all 4 order types (we hardcode this client-side anyway).
- `GET /brands/{brand}/venues/{venue}/order-types` → array of `{ id, name, slug, active, venue_order_type_id, serving_times[] }`. The `venue_order_type_id` is the pivot row id (`venue_order_types.id`) — this is what `parent_id` references for `parent_type='order_type'`.
- `POST /brands/{brand}/venues/{venue}/order-types` body: `{ order_type_id }` → `{ venue_order_type_id }` (201). 409 if already attached.
- `DELETE /brands/{brand}/venues/{venue}/order-types/{orderTypeId}` → 204. **URL uses `order_type.id`, NOT `venue_order_type_id`.**
- **There is no PATCH endpoint to toggle `active`** on an attached order type. UI drops the active toggle until backend ships it (see Phase 8).

### Serving times — **flat routes, parent in body/query**

Single shared controller. Parent is passed as `parent_type` + `parent_id` in body (writes) or query (read).

- `GET /serving-times?parent_type=brand&parent_id=1` → `ServingTime[]`.
- `POST /serving-times` body: `{ parent_type, parent_id, type, days?, date?, date_to?, time_from?, time_to?, working }` → `ServingTime` (201).
- `PUT /serving-times/{id}` body: any subset of the same fields (no parent change). Not used by our UI but exists.
- `DELETE /serving-times/{id}` → 204. **No parent in URL** — just the row id.
- `POST /serving-times/parse` body: `{ parent_type, parent_id, prompt }` → `{ preview: { serving_times: [...], clarification_needed: false }, clarification_needed: false }`.
  - **Field name is `prompt`, not `text`.**
  - Parsed items live at `body.preview.serving_times` (nested!).
  - `clarification_needed` is currently always `false` in practice — parser either returns rows or throws. UI keeps the defensive branch but expects to never hit it for the hackathon.
- `PUT /serving-times/replace` body: `{ parent_type, parent_id, serving_times: [...] }` → updated `ServingTime[]`. Transactional delete-then-insert.

### `ServingTime` for `parent_type='order_type'`

- `parent_id` is the `venue_order_types.id` (pivot id), **not** `order_types.id`. Get it from the `venueOrderTypes` list response.

### Errors

- 422 still uses `{ message, errors: { field: [msg] } }` shape (Laravel default).
- DeepSeek parse failure → currently 500 from default Laravel handler (parser throws `RuntimeException`). UI shows a generic "Couldn't parse — try rephrasing" banner.
- 404 / 409 use `{ message }`.

### Health

- `GET /api/health` → `{ status: "ok" }`. Useful for the verify step.

---

## Phase 0 — Foundation & cleanup

**Goal:** kill the Vite default theme, install Router, set up design tokens, define the app shell.

Tasks:
- [ ] Remove dark-mode default styles from `style.css`; replace with PAR base + reset.
- [ ] Add design tokens to `style.css` (copy palette/shadows/transitions from `theme-settings.scss`):
  - Colors: `--primary-accent-100..05`, `--grayscale-100..05`, `--status-*`, `--transparent-*`.
  - Shadows: `--shadow-sm`, `--shadow-card`, `--shadow-modal`, `--focus-ring`.
  - Type: `--font-weight-normal/semibold/bold`, base font Inter, line-height 1.5.
  - Radii: `--radius-sm: 4px`, `--radius-md: 8px`, `--radius-lg: 12px`.
  - Spacing scale: keep utility classes minimal — write component CSS directly.
- [ ] `npm install vue-router@4`.
- [ ] Create `router/index.ts` with the 5 routes from UI.md (`/` redirect, `/brands`, `/brands/:brandId`, `/brands/:brandId/venues/:venueId`, `/brands/:brandId/menus/:menuId`). Empty page stubs OK.
- [ ] Update `main.ts` to install the router.
- [ ] Replace `App.vue` with an app shell: top bar (logo placeholder + "HoursAI" wordmark + breadcrumb area) + `<main>` containing `<RouterView />`. Background `--grayscale-05`, content card on `--white`.
- [ ] Delete the placeholder `components/StoreHoursNaturalInput.vue` (will be replaced by `HoursAIPrompt.vue` in Phase 7).
- [ ] **Fix env + docker port:** set `frontend/.env.local` → `VITE_API_BASE_URL=http://localhost:8080/api`. The running stack was started before the port change — run `docker compose down && docker compose up -d` so nginx publishes 8080 (per latest `docker-compose.yml`). Verify with `curl http://localhost:8080/api/health` → `{"status":"ok"}`.

Exit criteria: `npm run dev` shows the new shell at `/brands` with light theme. No console errors. `curl http://localhost:8080/api/brands` returns JSON from the backend.

---

## Phase 1 — Types, API client, composables

**Goal:** one place to talk to the backend. Endpoints follow the "Actual API contract" section above, not UI.md's older snippets.

Tasks:
- [ ] `types/index.ts` — interfaces aligned to backend models. Notable differences from UI.md:
  - `Brand`: `{ id, name, slug, timezone, active, created_at?, servingTimes? }`.
  - `Venue`: `{ id, brand_id, name, slug?, address?, city?, country?, timezone?, phone?, active, servingTimes? }`.
  - `Menu`: `{ id, brand_id, name, internal_name?, description?, active, position?, servingTimes? }`.
  - `VenueOrderType`: `{ id, name, slug, active, venue_order_type_id, serving_times: ServingTime[] }` — `id` is the global order_type id, `venue_order_type_id` is the pivot id used as `parent_id` for serving times.
  - `ServingTime`: keep as in UI.md. `days` is `DayName[] | null`.
  - `ParseResponse`: `{ preview: { serving_times: ServingTimeInput[], clarification_needed: boolean }, clarification_needed: boolean }` — wrap is real, code must read `body.preview.serving_times`.
- [ ] `composables/useApi.ts` — thin `fetch` wrapper:
  - Reads `VITE_API_BASE_URL`.
  - Methods: `get<T>(path, query?)`, `post<T>(path, body)`, `patch<T>(path, body)`, `put<T>(path, body)`, `del(path)`.
  - Returns the raw JSON body — **no `data` unwrapping** (backend doesn't envelope).
  - Throws `ApiError` with `status`, `message`, `errors` (Laravel 422 field map). 500s become `ApiError(500, 'Server error')`.
- [ ] `composables/useBrands.ts` — `list()`, `get(id)`, `create({name, slug?, timezone?, active?})`, `update(id, patch)`, `remove(id)`. Paths: `/brands`, `/brands/{id}`.
- [ ] `composables/useVenues.ts` — `list(brandId)`, `get(brandId, venueId)`, `create(brandId, body)`, `update(brandId, venueId, patch)`, `remove(brandId, venueId)`. **All paths include `brandId`** because routes are nested: `/brands/{brand}/venues/{venue}`.
- [ ] `composables/useMenus.ts` — `list(brandId)`, `get(brandId, menuId)`, `create(brandId, body)`, `update(brandId, menuId, patch)`, `remove(brandId, menuId)`. Nested: `/brands/{brand}/menus/{menu}`.
- [ ] `composables/useVenueOrderTypes.ts`:
  - Hardcode global list (4 entries from API.md).
  - `list(brandId, venueId)` → `GET /brands/{brand}/venues/{venue}/order-types`.
  - `attach(brandId, venueId, orderTypeId)` → `POST .../order-types` body `{ order_type_id }` → response `{ venue_order_type_id }`. Caller then re-fetches list.
  - `detach(brandId, venueId, orderTypeId)` → `DELETE .../order-types/{orderTypeId}` (URL uses global `order_type.id`).
  - **No `setActive`** — endpoint doesn't exist. Document in Phase 8.
- [ ] `composables/useServingTimes.ts` — single signature for all parent types. No `baseUrlOverride` needed (routes are flat now):
  - `list(parentType, parentId)` → `GET /serving-times?parent_type=…&parent_id=…`.
  - `create(parentType, parentId, slot)` → `POST /serving-times` (parent fields merged into body).
  - `remove(servingTimeId)` → `DELETE /serving-times/{id}` (id only — no parent).
  - `parse(parentType, parentId, prompt)` → `POST /serving-times/parse` body `{ parent_type, parent_id, prompt }`. Returns the **inner** `preview.serving_times` array + `clarification_needed` flag, unwrapped at the composable layer so components don't see the `preview` nesting.
  - `replace(parentType, parentId, items)` → `PUT /serving-times/replace` body `{ parent_type, parent_id, serving_times }`.
  - For `parent_type='order_type'`, the caller passes `parentId = venue_order_type_id` (the pivot id, available on each `VenueOrderType`).
- [ ] `composables/useToast.ts` — minimal pub/sub for transient success/error banners (single global instance).

Exit criteria: composables compile and the API client can hit `/api/health` + `/api/brands` against the running backend (`docker compose up`).

---

## Phase 2 — Shared UI primitives

**Goal:** the design system in 7 small components.

Tasks:
- [ ] `components/shared/AppButton.vue`
  - Variants: `primary` (filled `--primary-accent-100` + white text), `secondary` (white + 1px `--primary-accent-15` border + dark text), `ghost` (no border, dark text), `danger` (`--status-error`).
  - Sizes: `md` (default, 36px), `sm` (28px).
  - Props: `loading` (spinner), `disabled`, `icon` slot.
  - Focus ring: `--focus-ring`.
- [ ] `components/shared/AppInput.vue`
  - Props: `modelValue`, `label`, `placeholder`, `error`, `disabled`, `type` (text/date/time).
  - Light border `--grayscale-20`, focus border `--primary-accent-100`, error border `--status-error`.
- [ ] `components/shared/AppTextarea.vue` — same look as input, min-height 96px, vertical resize.
- [ ] `components/shared/AppToggle.vue` — switch (open/closed, active/inactive). Track + thumb, on-color `--status-success`, off-color `--grayscale-20`.
- [ ] `components/shared/TabBar.vue`
  - Props: `tabs: { id, label, badge? }[]`, `modelValue` (active id).
  - Underline-style tabs (PAR look): 2px bottom border on active using `--primary-accent-100`, inactive `--grayscale-50`.
  - Optional badge dot/icon next to label (used for "Serving Times ●").
- [ ] `components/shared/InlineCreateForm.vue`
  - Slot-based form (renders fields from parent) + built-in Save/Cancel + Enter/Escape handlers + loading state.
- [ ] `components/shared/ConfirmDelete.vue` — small inline confirmation strip ("Delete this brand? [Cancel] [Delete]"), shown in place of the row's Delete button on click.
- [ ] `components/shared/StatusDot.vue` — colored dot + label ("Open"/"Closed"/"Active"/"Inactive").
- [ ] `components/shared/PageHeader.vue` — title (h1), optional subtitle, optional right-side actions slot, optional back link (uses Router).

Exit criteria: a `/dev` route (temporary) renders all primitives in their variants. Remove after Phase 3.

---

## Phase 3 — Brands list page

**Goal:** first real page wired to the API.

Tasks:
- [ ] `pages/BrandsListPage.vue`
  - Mount: `useBrands().listBrands()` → loading skeleton → grid of `BrandCard`.
  - `BrandCard.vue` (local component): name, "Edit" button (router-link to `/brands/:id`), delete with `ConfirmDelete`.
  - "Add Brand" button toggles `InlineCreateForm` with a single name field. On save → POST → push to list → close form.
  - Empty state: "No brands yet — add your first brand to get started."
- [ ] Wire `/` to redirect to `/brands` (already done in Phase 0).

Exit criteria: can list/create/delete brands against live API. Navigation to `/brands/:id` works (target page is still a stub).

---

## Phase 4 — Brand detail page

**Goal:** the tabbed entity page pattern that venue/menu pages will copy.

Tasks:
- [ ] `pages/BrandDetailPage.vue`
  - Reads `:brandId` from route, fetches brand.
  - Header: brand name + back link to `/brands`.
  - `TabBar` with tabs: Info | Venues | Menus | Serving Times.
  - Lazy-fetch per tab activation (don't fire all GETs on mount).
- [ ] `components/brand/BrandInfoTab.vue` — name input (+ optional timezone/active if quick) + Save → `useBrands().update(brandId, patch)` (PATCH `/brands/{id}`).
- [ ] `components/brand/VenuesTab.vue`
  - `useVenues().listVenues(brandId)` on activation.
  - Row per venue: name + address preview, click → `/brands/:brandId/venues/:venueId`.
  - "Add Venue" → `InlineCreateForm` with name/address/timezone (timezone = simple text input for hackathon; suggest "America/New_York").
- [ ] `components/brand/MenusTab.vue`
  - `useMenus().listMenus(brandId)`.
  - Row per menu: name + description + active dot.
  - "Add Menu" → `InlineCreateForm` with name + description (defaults active=true).
- [ ] Serving Times tab content: placeholder until Phase 6 (`<p>Serving Times panel — coming in Phase 6</p>`).

Exit criteria: all 3 non-ST tabs functional. Venue/Menu rows navigate even though their pages are still stubs.

---

## Phase 5 — Venue & Menu detail pages

**Goal:** mirror the Brand detail structure for the other two entities.

Tasks:
- [ ] `pages/VenueDetailPage.vue` — TabBar: Info | Order Types | Serving Times. Reads both `:brandId` and `:venueId` from route. Loads via `useVenues().get(brandId, venueId)`.
- [ ] `components/venue/VenueInfoTab.vue` — name, address, timezone inputs + Save → `useVenues().update(brandId, venueId, patch)` (PATCH `/brands/{brand}/venues/{venue}`).
- [ ] Order Types tab: placeholder until Phase 8.
- [ ] `pages/MenuDetailPage.vue` — TabBar: Info | Serving Times. Reads both `:brandId` and `:menuId`.
- [ ] `components/menu/MenuInfoTab.vue` — name, description, active toggle + Save → `useMenus().update(brandId, menuId, patch)` (PATCH `/brands/{brand}/menus/{menu}`).

Exit criteria: all 4 detail pages render, all Info tabs save successfully.

---

## Phase 6 — ServingTimesPanel + Manual tab

**Goal:** the core CRUD panel, used everywhere.

Tasks:
- [ ] `components/serving-times/ServingTimesPanel.vue`
  - Props: `parentType: ParentType`, `parentId: number`. **No `baseUrlOverride`** — routes are flat, the composable handles everything via `parent_type`/`parent_id` in body/query.
  - Internal sub-tab state: `'manual' | 'hoursai'`, default `'manual'`.
  - Sub-`TabBar` with Manual | HoursAI ✦.
  - Renders either `<ManualServingTimes>` or `<HoursAIPrompt>` and forwards `parentType` + `parentId`.
- [ ] `components/serving-times/ManualServingTimes.vue`
  - On mount: `useServingTimes().list(parentType, parentId)`.
  - Render rows: type badge ("WEEKDAY" or "SPECIAL" pill), days chip group OR date range, time range OR em-dash if closed, `StatusDot`, Delete.
  - Sort rows: weekday first (Mon→Sun order by day index), then specials by date ascending.
  - Delete → `useServingTimes().remove(servingTimeId)` (no parent in URL, just the row id).
  - "Add Slot" button toggles `<AddServingTimeForm>`.
- [ ] `components/serving-times/AddServingTimeForm.vue`
  - Type radio group (Weekday / Special date).
  - Weekday → multi-select chip strip Mon..Sun (re-implement PAR `AppWeekDayChips` look: round pills with selected state in `--primary-accent-100`).
  - Special → `date` input + optional `date_to` input.
  - `time_from` + `time_to` inputs (HTML `<input type="time">`).
  - Working toggle (default ON). When OFF, time inputs disabled and not submitted.
  - On save → `useServingTimes().create(parentType, parentId, slot)` → append to list → close form. Composable injects `parent_type`/`parent_id` into the POST body.
  - Inline validation: `time_to > time_from` is **client-side only** (backend doesn't enforce). Other rules per backend's 422 messages.
- [ ] `components/serving-times/DayChips.vue` (small subcomponent, also used in display rows).
- [ ] Wire `ServingTimesPanel` into BrandDetailPage, VenueDetailPage, MenuDetailPage Serving Times tabs with `parentType` = `'brand'`/`'venue'`/`'menu'` and `parentId` from the route.

Exit criteria: full CRUD against any of brand/venue/menu serving times works end-to-end.

---

## Phase 7 — HoursAI tab (the wow moment)

**Goal:** prompt → preview → apply.

Backend contract reminders for this phase:
- Request field name is **`prompt`**, not `text`.
- Response shape is **`{ preview: { serving_times: [...], clarification_needed: false }, clarification_needed: false }`**. The composable already unwraps to `{ serving_times, clarification_needed }` — components consume that.
- `clarification_needed` is **currently always `false`** (parser either returns rows or throws → 500). UI keeps the defensive branch but realistically never hits it for the hackathon. Failures show a generic red banner.

Tasks:
- [ ] `components/serving-times/HoursAIPrompt.vue`
  - `<AppTextarea v-model="promptText">` with cycling placeholder (4 examples from UI.md, rotate every 4s while empty/unfocused).
  - "Parse" button — `loading` while request in flight; disabled if `promptText.trim().length === 0`.
  - On success → call `useServingTimes().parse(parentType, parentId, promptText)`:
    - If `clarification_needed: true` → amber box (`--status-warning-15` bg, `--status-activating` accent) with the question (if backend later adds `clarification_question`). User edits prompt and re-parses.
    - Else → `<ServingTimesPreview :items="result.serving_times" />` + Apply + Edit buttons.
  - On parse error (5xx / network / invalid JSON from DeepSeek) → red banner "Couldn't parse — try rephrasing or simplifying."
- [ ] `components/serving-times/ServingTimesPreview.vue`
  - Read-only table (Type | Days/Date | From | To | Status). Same row formatting as Manual list for visual continuity.
- [ ] Apply flow:
  - Confirm prompt: "This will replace all existing serving times. Apply?" → inline confirm (re-use `ConfirmDelete` pattern with custom label).
  - On confirm → `useServingTimes().replace(parentType, parentId, items)`. On 200: green banner "Serving times updated", emit `update` so the parent panel switches back to Manual sub-tab and re-fetches the list.
  - On 422: red banner with first error from `ApiError.errors`.
- [ ] Edit button: clears preview, returns to textarea with the prompt intact, **no API call** — original DB state untouched.

Exit criteria: paste each of the 4 example prompts from UI.md, hit Parse → Apply → see the result reflected in Manual tab.

---

## Phase 8 — Order Types (venue page)

**Goal:** attach/detach + per-order-type serving times.

Backend contract reminders for this phase:
- List endpoint: `GET /brands/{brand}/venues/{venue}/order-types` returns array of `{ id, name, slug, active, venue_order_type_id, serving_times[] }`. Only **attached** types are returned — we merge with the hardcoded global list of 4 to render the unattached ones too.
- `parent_id` for `parent_type='order_type'` is **`venue_order_type_id` (pivot id)**, NOT `order_type.id`. Critical for serving times inside an attached order type.
- Attach response is `{ venue_order_type_id }` — re-fetch the list after attach to get the full record (+ empty `serving_times`).
- Detach URL uses `order_type.id`: `DELETE .../order-types/{orderTypeId}`.
- **There is no PATCH endpoint for the `active` flag** on a venue order type. UI does not show an active toggle for v1. If backend ships one later, add it.

Tasks:
- [ ] `components/order-types/OrderTypesPanel.vue`
  - Hardcode the global list: `[{ id:1, name:'Pickup', slug:'pickup' }, { id:2, name:'Delivery', slug:'delivery' }, { id:3, name:'Dine In', slug:'dine-in' }, { id:4, name:'Drive Thru', slug:'drive-thru' }]`.
  - On mount: `useVenueOrderTypes().list(brandId, venueId)` → map of `orderTypeId → VenueOrderType` (with `venue_order_type_id` and `serving_times`).
  - Render all 4 `OrderTypeCard.vue`. Pass `attached: VenueOrderType | null` so the card knows whether to render the expandable serving times section.
- [ ] `components/order-types/OrderTypeCard.vue`
  - Card with name + slug + small emoji/SVG icon.
  - Top-right control: "Attach" button when not attached, "Attached ✓" pill with "Detach" link when attached.
  - Attach → `attach(brandId, venueId, orderTypeId)` → re-fetch list → card now shows attached state with empty serving times.
  - Detach → inline confirm ("Detach Delivery? Its serving times will be removed.") → `detach(brandId, venueId, orderTypeId)` → re-fetch.
  - When attached: "▸ Serving Times" expand chevron. When expanded, render `<ServingTimesPanel parentType="order_type" :parentId="attached.venue_order_type_id" />`. **Pass the pivot id, not the order_type id.**
  - No active toggle (endpoint doesn't exist). Optional: display "Active" badge tied to `attached.active` (read-only) for visual completeness.
- [ ] Wire `OrderTypesPanel` into `VenueDetailPage` Order Types tab. Pass both `brandId` and `venueId`.

Exit criteria: attach Pickup to a venue, expand it, add 2 serving slots via Manual, try the HoursAI flow inside it. Detach and re-attach — confirm serving times are gone after detach.

---

## Phase 9 — Polish, edge cases, demo prep

**Goal:** the build is presentable.

Tasks:
- [ ] Loading skeletons for each list (gray bars at appropriate heights — no spinners-only).
- [ ] Empty states for every list view ("No serving times yet — add a slot or use HoursAI to describe them in plain English.").
- [ ] Global toast/banner stack in `App.vue` rendered from `useToast`.
- [ ] Error boundaries: every API call funnels its `ApiError.message` into either inline display (form-level 422) or toast (network/500).
- [ ] Keyboard nav: Tab through all interactive elements, focus rings visible (`--focus-ring`), Escape closes inline forms.
- [ ] Page titles via `document.title` per route (router meta + small composable).
- [ ] Layout: fixed top bar (60px, `--white` + `--shadow-sm`), content max-width 1280px, side padding 32px (matches `.o-app__content` from PAR).
- [ ] Smoke test all 4 example prompts from `UI.md` and the 5 from `README.md`.
- [ ] Verify `VITE_API_BASE_URL=http://localhost:8080/api` and the running stack publishes nginx on 8080 (`docker compose ps` shows `0.0.0.0:8080->80/tcp`).

Exit criteria: clean run-through of demo script in `README.md` step 1–7.

---

## Phase 10 — Stretch (only if time after Phase 9)

- [ ] Replace HTML `<input type="time">` with a custom 12h dropdown (hours + minutes in 30-min steps, AM/PM) to better mirror PAR `AppTimesAndMinutesSelects`.
- [ ] Sidebar nav with `Brands` icon (single item is fine for hackathon — visual match to PAR sidebar).
- [ ] Light Playwright smoke test: load `/brands`, create a brand, navigate to its Serving Times, run a parse against a stubbed backend.
- [ ] Subtle ✦ sparkle animation on the HoursAI sub-tab when the textarea is empty (matches the "wow" theme).

---

## Final file tree (target)

```
frontend/src/
├── App.vue
├── main.ts
├── style.css                                ← design tokens + reset
├── router/
│   └── index.ts
├── types/
│   └── index.ts
├── composables/
│   ├── useApi.ts
│   ├── useBrands.ts
│   ├── useVenues.ts
│   ├── useMenus.ts
│   ├── useVenueOrderTypes.ts
│   ├── useServingTimes.ts
│   └── useToast.ts
├── pages/
│   ├── BrandsListPage.vue
│   ├── BrandDetailPage.vue
│   ├── VenueDetailPage.vue
│   └── MenuDetailPage.vue
└── components/
    ├── shared/
    │   ├── AppButton.vue
    │   ├── AppInput.vue
    │   ├── AppTextarea.vue
    │   ├── AppToggle.vue
    │   ├── TabBar.vue
    │   ├── InlineCreateForm.vue
    │   ├── ConfirmDelete.vue
    │   ├── StatusDot.vue
    │   └── PageHeader.vue
    ├── brand/
    │   ├── BrandInfoTab.vue
    │   ├── VenuesTab.vue
    │   └── MenusTab.vue
    ├── venue/
    │   └── VenueInfoTab.vue
    ├── menu/
    │   └── MenuInfoTab.vue
    ├── serving-times/
    │   ├── ServingTimesPanel.vue
    │   ├── ManualServingTimes.vue
    │   ├── AddServingTimeForm.vue
    │   ├── DayChips.vue
    │   ├── HoursAIPrompt.vue
    │   └── ServingTimesPreview.vue
    └── order-types/
        ├── OrderTypesPanel.vue
        └── OrderTypeCard.vue
```

---

## Risks & open questions

| # | Risk / question | Mitigation |
|---|---|---|
| 1 | API.md text disagrees with backend code in several places (envelope, nesting, parse fields, order-type active toggle). | This plan treats the **code** as truth (see "Actual API contract" section). Flag any backend-side change to that team — they should update API.md to match. |
| 2 | Port 8080 vs 8000 — running stack was built before the port fix. | Run `docker compose down && docker compose up -d` once at the start of Phase 0 so nginx publishes 8080. Set `VITE_API_BASE_URL` accordingly. |
| 3 | `parent_id` for `parent_type='order_type'` must be the pivot id (`venue_order_type_id`), not `order_type.id`. Easy to mistake. | Type-safe wrapper: `VenueOrderType.venue_order_type_id` is the only field passed to `<ServingTimesPanel>` from `OrderTypeCard`. Add a code comment + assert in the composable in dev. |
| 4 | No backend endpoint to toggle `active` on an attached order type. | UI skips the toggle. Show `active` read-only for now; add toggle if backend adds the route. |
| 5 | Attach returns only `{ venue_order_type_id }` — UI needs full state. | `OrderTypeCard` triggers a list refetch after attach. Cheap because there are at most 4 rows. |
| 6 | DeepSeek parser currently never returns `clarification_needed: true` — it either succeeds or throws → 500. | Keep clarification UI as dead code for safety; surface 500s as a generic "Couldn't parse" banner. |
| 7 | Parse response is nested under `preview.serving_times`. | Unwrap once in `useServingTimes.parse()` so components consume a flat `{ serving_times, clarification_needed }` shape. |
| 8 | Replace API destroys data — confirm before Apply. | Inline confirm in `HoursAIPrompt` (Phase 7). |
| 9 | DeepSeek latency feels slow. | Loading state on Parse button + disable textarea while pending. Add a 30s client timeout matching server timeout. |
| 10 | 422 error shape — `{ message, errors: { field: [msg] } }`. | `useApi.ts` surfaces as `ApiError.errors`. Forms read the first message per field. |
| 11 | Timezone field on venue — free-text vs select. | Hackathon: free-text with a placeholder. Stretch (Phase 10): IANA list dropdown. |
| 12 | Order type icons — design has emoji 🛵 in mock. | Inline emoji is fine; centralize the icon map in `OrderTypeCard.vue`. |

---

## Order of work (single-developer sequence)

1. Phase 0 → 1 → 2 — foundation must be done first.
2. Phase 3 → 4 → 5 — pages in dependency order (Brands first, then children).
3. Phase 6 → 7 — Manual tab before HoursAI (HoursAI's Apply needs the Manual list to refresh).
4. Phase 8 — Order types last among features (depends on `ServingTimesPanel` + venue page).
5. Phase 9 — polish loop.
6. Phase 10 — only if Phase 9 is clean.
