# HoursAI — Frontend (UI) Specification

For: Lazar
Stack: Vue 3 + TypeScript, Vite, `fetch` for API calls
API base: `http://localhost:8080/api` (via `VITE_API_BASE_URL` env var)

---

## Tech Stack

- Vue 3 `<script setup lang="ts">` only — no Options API
- No UI framework — plain CSS (scoped per component)
- Vue Router for navigation
- No state management needed — local `ref/reactive` per page is enough
- `fetch` for all API calls — no axios

---

## Routes

```
/                           → redirect to /brands
/brands                     → BrandsListPage
/brands/:brandId            → BrandDetailPage  (tabs: Info | Venues | Menus | Serving Times)
/brands/:brandId/venues/:venueId  → VenueDetailPage  (tabs: Info | Order Types | Serving Times)
/brands/:brandId/menus/:menuId    → MenuDetailPage  (tabs: Info | Serving Times)
```

---

## Pages

### 1. BrandsListPage `/brands`

**What it shows:**
- Page title: "Brands"
- List of brand cards (name + edit link)
- "Add Brand" button → inline form (name input + Save/Cancel)

**API calls:**
- `GET /brands` on mount
- `POST /brands` on save
- `DELETE /brands/{id}` on delete (with confirmation)

**Component:**
```
BrandsListPage
  BrandCard (repeated)
  InlineCreateForm
```

---

### 2. BrandDetailPage `/brands/:brandId`

**Tabs:**
- **Info** — name field, Save button
- **Venues** — list of venues + "Add Venue" inline form
- **Menus** — list of menus + "Add Menu" inline form
- **Serving Times** — `ServingTimesPanel` (see below)

**API calls:**
- `GET /brands/:id` on mount
- `PATCH /brands/:id` on save
- `GET /brands/:brandId/venues` when Venues tab active
- `GET /brands/:brandId/menus` when Menus tab active
- `GET /brands/:id/serving-times` when Serving Times tab active

**Component:**
```
BrandDetailPage
  TabBar (Info | Venues | Menus | Serving Times)
  BrandInfoTab
  VenuesTab
    VenueRow (repeated, links to /brands/:brandId/venues/:venueId)
    InlineCreateForm
  MenusTab
    MenuRow (repeated, links to /brands/:brandId/menus/:menuId)
    InlineCreateForm
  ServingTimesPanel (parentType="brand" :parentId="brandId")
```

---

### 3. VenueDetailPage `/brands/:brandId/venues/:venueId`

**Tabs:**
- **Info** — name, address, timezone — Save button
- **Order Types** — `OrderTypesPanel` (see below)
- **Serving Times** — `ServingTimesPanel`

**API calls:**
- `GET /venues/:id` on mount
- `PATCH /venues/:id` on save
- `GET /venues/:id/order-types` when Order Types tab active
- `GET /venues/:id/serving-times` when Serving Times tab active

**Component:**
```
VenueDetailPage
  TabBar (Info | Order Types | Serving Times)
  VenueInfoTab
  OrderTypesPanel (:venueId)
  ServingTimesPanel (parentType="venue" :parentId="venueId")
```

---

### 4. MenuDetailPage `/brands/:brandId/menus/:menuId`

**Tabs:**
- **Info** — name, description, active toggle — Save button
- **Serving Times** — `ServingTimesPanel`

**API calls:**
- `GET /menus/:id` on mount
- `PATCH /menus/:id` on save
- `GET /menus/:id/serving-times` when Serving Times tab active

**Component:**
```
MenuDetailPage
  TabBar (Info | Serving Times)
  MenuInfoTab
  ServingTimesPanel (parentType="menu" :parentId="menuId")
```

---

## Shared Components

---

### `ServingTimesPanel`

The core component. Used on every entity detail page.

**Props:**
```ts
parentType: 'brand' | 'venue' | 'menu' | 'order_type'
parentId: number
```

**What it shows:**

Two sub-tabs inside the panel:
1. **Manual** — full list of existing serving time slots + ability to add/delete manually
2. **HoursAI** — plain English prompt → parse → preview → apply

```
ServingTimesPanel
  SubTabBar (Manual | HoursAI)
  ManualServingTimes (when Manual tab active)
  HoursAIPrompt (when HoursAI tab active)
```

---

### `ManualServingTimes`

**What it shows:**
- List of existing serving time rows
- Each row shows: type badge, days or date, time range, working status, Delete button
- "Add Slot" button → `AddServingTimeForm` appears inline

**AddServingTimeForm fields:**
- Type radio: `Weekday` / `Special date`
- If Weekday: multi-select checkboxes for days (Mon–Sun)
- If Special date: date picker for `date`, optional `date_to`
- Time from (HH:MM input)
- Time to (HH:MM input)
- Working toggle (open / closed)
- Save / Cancel buttons

**API calls:**
- `GET /{parent}/serving-times` on mount
- `POST /{parent}/serving-times` on save
- `DELETE /{parent}/serving-times/{id}` on delete

**Example serving time row:**
```
[WEEKDAY]  Mon Tue Wed Thu Fri   08:00 – 22:00   ● Open    [Delete]
[WEEKDAY]  Sat Sun              —                ● Closed  [Delete]
[SPECIAL]  2026-12-25           —                ● Closed  [Delete]
```

---

### `HoursAIPrompt`

**What it shows:**
1. Textarea for plain English input
2. "Parse" button
3. If `clarification_needed: true` → show clarification question in amber box
4. If valid parse → show `ServingTimesPreview` (table of parsed results)
5. Below preview: "Apply" button + "Edit" button

**Apply flow:**
- Calls `PUT /{parent}/serving-times/replace` with the parsed `serving_times` array
- On success: shows green success banner, reloads Manual tab data
- On error: shows error message

**API calls:**
- `POST /{parent}/serving-times/parse`
- `PUT /{parent}/serving-times/replace`

**Example prompts shown as placeholder hints (cycle through on focus):**
```
"We're open Mon–Fri 8am to 10pm, closed weekends."
"Breakfast hours 6am–11am on weekdays, 7am–1pm on weekends."
"Closed Christmas Day and New Year's Day."
"Stop taking orders 30 minutes before close every day."
```

---

### `ServingTimesPreview`

Displays parsed serving times before applying. Read-only.

**Props:**
```ts
servingTimes: ServingTime[]
```

**What it renders:**

Table with columns: Type | Days / Date | From | To | Status

```
Weekday   Mon Tue Wed Thu Fri   08:00   22:00   Open
Weekday   Sat Sun              —       —       Closed
Special   2026-12-25           —       —       Closed
```

---

### `OrderTypesPanel`

Used inside VenueDetailPage Order Types tab.

**Props:**
```ts
venueId: number
```

**What it shows:**
- List of 4 global order types (Pickup, Delivery, Dine In, Drive Thru)
- Each shown as a card:
  - Name + slug
  - Toggle: attached / not attached
  - If attached: Active/Inactive toggle
  - If attached: "Serving Times" expand button → shows `ServingTimesPanel` inline

**API calls:**
- `GET /venues/:id/order-types` on mount
- `POST /venues/:id/order-types` to attach
- `DELETE /venues/:id/order-types/:id` to detach
- `PATCH /venues/:id/order-types/:id` to toggle active

**OrderTypeCard layout:**
```
┌─────────────────────────────────────────────────────┐
│  Pickup                                  [Attached ✓]│
│  Active: [toggle ON]                                 │
│  [▼ Serving Times]                                   │
│  ┌───────────────────────────────────────────────┐   │
│  │ ServingTimesPanel (parentType=order_type)     │   │
│  └───────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

---

## Component File Structure

```
frontend/src/
├── pages/
│   ├── BrandsListPage.vue
│   ├── BrandDetailPage.vue
│   ├── VenueDetailPage.vue
│   └── MenuDetailPage.vue
├── components/
│   ├── serving-times/
│   │   ├── ServingTimesPanel.vue       ← main container, owns sub-tabs
│   │   ├── ManualServingTimes.vue      ← list + add form
│   │   ├── AddServingTimeForm.vue      ← inline add form
│   │   ├── ServingTimesPreview.vue     ← read-only preview table
│   │   └── HoursAIPrompt.vue          ← textarea → parse → apply
│   ├── order-types/
│   │   ├── OrderTypesPanel.vue
│   │   └── OrderTypeCard.vue
│   └── shared/
│       ├── TabBar.vue
│       ├── InlineCreateForm.vue
│       └── ConfirmDelete.vue
├── composables/
│   └── useServingTimes.ts              ← fetch/create/delete/parse/replace logic
├── types/
│   └── index.ts                        ← TypeScript interfaces
└── router/
    └── index.ts
```

---

## TypeScript Interfaces

```ts
// types/index.ts

export interface Brand {
  id: number
  name: string
  slug: string
  created_at: string
}

export interface Venue {
  id: number
  brand_id: number
  name: string
  address: string
  timezone: string
}

export interface Menu {
  id: number
  brand_id: number
  name: string
  description: string
  active: boolean
}

export interface OrderType {
  id: number
  name: string
  slug: string
}

export interface VenueOrderType extends OrderType {
  active: boolean
}

export type ServingTimeType = 'weekday' | 'special'
export type DayName = 'monday' | 'tuesday' | 'wednesday' | 'thursday' | 'friday' | 'saturday' | 'sunday'
export type ParentType = 'brand' | 'venue' | 'menu' | 'order_type'

export interface ServingTime {
  id?: number
  parent_type: ParentType
  parent_id: number
  type: ServingTimeType
  days: DayName[] | null
  date: string | null
  date_to: string | null
  time_from: string | null
  time_to: string | null
  working: boolean
}

export interface ParseResult {
  serving_times: Omit<ServingTime, 'id' | 'parent_type' | 'parent_id'>[]
  clarification_needed: boolean
  clarification_question: string | null
}
```

---

## `useServingTimes` Composable

```ts
// composables/useServingTimes.ts

import type { ServingTime, ParseResult, ParentType } from '../types'

export function useServingTimes(parentType: ParentType, parentId: number) {
  const baseUrl = `${import.meta.env.VITE_API_BASE_URL}/${parentType}s/${parentId}/serving-times`

  async function list(): Promise<ServingTime[]> { ... }
  async function create(slot: Partial<ServingTime>): Promise<ServingTime> { ... }
  async function remove(id: number): Promise<void> { ... }
  async function parse(text: string): Promise<ParseResult> { ... }
  async function replace(servingTimes: Partial<ServingTime>[]): Promise<ServingTime[]> { ... }

  return { list, create, remove, parse, replace }
}
```

> **Note for order_type:** the URL pattern is different: `/venues/{venueId}/order-types/{orderTypeId}/serving-times`.
> Pass the full base URL as a prop to `ServingTimesPanel` in this case, or override inside `useServingTimes`.

---

## Visual Layout

### BrandDetailPage — Serving Times tab

```
┌─────────────────────────────────────────────────────┐
│  Demo Burger                                         │
│  [Info]  [Venues]  [Menus]  [Serving Times ●]        │
├─────────────────────────────────────────────────────┤
│                                                      │
│  [Manual]  [HoursAI ✦]                               │
│                                                      │
│  ──── Manual ────                                    │
│  Weekday  Mon–Fri   08:00–22:00  ● Open   [Delete]  │
│  Weekday  Sat–Sun   09:00–23:00  ● Open   [Delete]  │
│                                                      │
│  [+ Add Slot]                                        │
│                                                      │
└─────────────────────────────────────────────────────┘
```

### HoursAI tab active

```
┌─────────────────────────────────────────────────────┐
│  [Manual]  [HoursAI ✦]                               │
│                                                      │
│  Describe your serving times in plain English:       │
│  ┌───────────────────────────────────────────────┐   │
│  │ We're open Mon–Fri 8am–10pm, closed weekends  │   │
│  └───────────────────────────────────────────────┘   │
│  [Parse]                                             │
│                                                      │
│  ── Preview ──────────────────────────────────────   │
│  Weekday  Mon–Fri   08:00  22:00  ● Open             │
│  Weekday  Sat–Sun   —      —      ● Closed           │
│                                                      │
│  [Apply]  [Edit]                                     │
│                                                      │
└─────────────────────────────────────────────────────┘
```

### OrderTypeCard with serving times expanded

```
┌─────────────────────────────────────────────────────┐
│  🛵 Delivery                        [Attached ✓]     │
│  Active: ● ON                                        │
│  [▼ Serving Times]                                   │
│  ┌───────────────────────────────────────────────┐   │
│  │  [Manual]  [HoursAI ✦]                        │   │
│  │  Weekday  Mon–Fri  11:00–21:00  ● Open        │   │
│  │  [+ Add Slot]                                 │   │
│  └───────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────┘
```

---

## Key Rules for Lazar

1. Every entity detail page has a **Serving Times tab** — always use `ServingTimesPanel` with the correct `parentType` and `parentId`.
2. `ServingTimesPanel` always has two sub-tabs: **Manual** and **HoursAI**. Never build them separately.
3. `HoursAI` tab calls `/parse` → shows preview → on Apply calls `/replace`. It does NOT call `/serving-times` POST directly.
4. All forms are **inline** (no modals, no routing). Show form below the list, hide on save/cancel.
5. Order types are always a fixed list of 4 — fetch the venue's attached ones and show all 4 with attach/detach toggle.
6. For order type serving times, the `ServingTimesPanel` URL is `/venues/{venueId}/order-types/{orderTypeId}/serving-times`.
7. Use `useServingTimes` composable for all serving time operations — don't call `fetch` directly in components.
