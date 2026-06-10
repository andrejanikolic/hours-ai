# TimeBanders — HoursAI
## Plain-Language Store Hours Configuration for PAR Management Center

---

## The Mission

Restaurant operators configure store hours through complex form UIs with 7 rows of time pickers.
A single sentence like *"We're open Mon–Fri 8am to 10pm, closed Sundays"* takes 15+ clicks today.

**HoursAI** replaces that with a single text input. The operator types plain English, DeepSeek parses it into structured config, a preview is shown for confirmation, and the database updates itself on approval.

---

## Hackathon Context

| | |
|---|---|
| **Team** | TimeBanders |
| **Members** | Nikola Kovacevic, Lazar Matic, Andreja Nikolic |
| **Project** | HoursAI |
| **Theme** | Menu management / Internal productivity |
| **Build window** | Wed 12:00 → Thu 10:00 (22 hours) |
| **Gate 1** | Today 16:00 — email: team name, project name, spec prompt, team photo |
| **Gate 2** | Thu 09:00 — slides PDF |
| **Pitches** | Thu 10:00 |
| **AI** | DeepSeek API (tokens funded by PAR) |
| **QA tool** | Playwright |
| **Mock data only** | No real store data |

---

## Example Prompts (the "wow" demo)

```
"We're open Mon–Fri 8am to 10pm, Saturday 9am to 11pm, and closed Sundays."
"Close all online ordering on Christmas Day and New Year's Day."
"Add a 15-minute pickup buffer on weekends only."
"We stop taking orders 30 minutes before closing every day."
"Enable delivery only between 11am and 9pm, but keep pickup available until close."
```

---

## User Flow

```
Operator types plain English
        ↓
MC textarea input (Vue 3 component)
        ↓
POST /api/stores/{id}/hours/parse   ← new Laravel endpoint
        ↓
DeepSeek API  →  structured JSON
        ↓
Preview card (interpreted schedule shown to operator)
        ↓
"Apply" → PATCH store hours in DB
"Edit"  → back to textarea, original unchanged
```

---

## Architecture

### Backend — Laravel (core-api)

**New endpoint:**
```
POST /api/stores/{id}/hours/parse
Body: { "text": "We're open Mon–Fri 8am to 10pm, closed Sundays." }
```

**Response schema:**
```json
{
  "days": [
    { "day": "monday", "open": "08:00", "close": "22:00" },
    { "day": "tuesday", "open": "08:00", "close": "22:00" },
    ...
    { "day": "sunday", "open": null, "close": null, "closed": true }
  ],
  "specialClosures": [
    { "date": "2026-12-25", "reason": "Christmas Day" }
  ],
  "orderCutoffMinutes": 30,
  "deliveryWindow": { "open": "11:00", "close": "21:00" },
  "pickupWindow": null,
  "clarification_needed": false
}
```

**DeepSeek service:**
- System prompt encodes the hours data model schema
- Instructs model to return only valid JSON matching the schema
- **Injects current store hours from DB into system prompt** so partial updates preserve unchanged days
- Handles edge cases: closed all day, overnight hours, "30 min before close", delivery-only windows
- Returns `{ clarification_needed: true, question: "..." }` for ambiguous input instead of erroring

**System prompt structure:**
```
System:
  - You are a store hours parser. Return only valid JSON matching this schema: {...}
  - Current store hours: { monday: 08:00-22:00, tuesday: 08:00-22:00, ... }  ← loaded from DB
  - Business rules: times in HH:MM 24h format, days lowercase full names, all 7 days always present

User:
  - "Keep everything the same but close on Sundays"
```

**Write-back endpoint:**
```
PATCH /api/stores/{id}/hours
Body: parsed JSON from above
```

### Frontend — Vue 3 (Management Center)

New component `StoreHoursNaturalInput.vue`:
- Textarea for plain English input
- "Parse" button → calls parse endpoint
- Preview card: renders the interpreted schedule in human-readable form
- "Apply" button → calls PATCH, shows success toast
- "Edit" button → returns to textarea, original hours intact
- Error state: shown when parse fails or `clarification_needed: true`

---

## Atomic Tasks + Acceptance Criteria + Harnesses

### Task 1 — Parse endpoint

**Build:** `POST /api/stores/{id}/hours/parse` — accepts `{ "text": "..." }`, calls DeepSeek, returns structured JSON.

**AC:**
- Returns valid structured JSON for all 5 fixture prompts
- Ambiguous input returns `{ clarification_needed: true, question: "..." }` — no 500
- Response always matches the defined schema (no extra/missing fields)

**Harness:** PHPUnit feature test with 5 fixture prompts; assertions on response shape and HTTP 200.

---

### Task 2 — DeepSeek parsing service

**Build:** `DeepSeekHoursParser` service class. System prompt encodes the data model and injects current store hours from DB before every call. Handles: closed days, overnight hours, "30 min before close", delivery-only windows, special date closures, partial updates.

**AC:**
- All 5 fixture prompts produce structurally valid responses
- No hallucinated fields outside the defined schema
- `orderCutoffMinutes` correctly parsed from "stop taking orders 30 minutes before closing"
- `deliveryWindow` parsed independently from general open hours
- Partial update ("Keep everything the same but close on Sundays") preserves all unchanged days from injected current hours

**Harness:** Unit test with mocked DeepSeek response. Integration test with live DeepSeek API (skipped in CI, run manually).

---

### Task 3 — Vue component

**Build:** `StoreHoursNaturalInput.vue` in Management Center. Textarea + Parse button + preview card + Apply/Edit buttons.

**AC:**
- Preview card renders correctly for all 5 fixture responses
- Apply button calls `PATCH /api/stores/{id}/hours` with the parsed payload
- Error state renders when parse returns `clarification_needed: true` or HTTP error
- Edit button returns user to textarea without modifying existing hours

**Harness:** Playwright E2E test:
1. Navigate to store hours page
2. Type fixture prompt into textarea
3. Click Parse → verify preview card renders with correct days/times
4. Click Apply → verify network call to PATCH endpoint
5. Verify success toast appears

---

### Task 4 — Write-back + rollback

**Build:** `PATCH /api/stores/{id}/hours` — writes parsed config to DB. Wraps in a transaction; rolls back on error.

**AC:**
- DB reflects new hours after successful Apply
- Original hours are unchanged if user clicks Edit (no partial writes)
- Returns 422 with error detail if parsed JSON fails DB validation

**Harness:** PHPUnit feature test — parse → confirm → assert DB state. Separate test for rollback on validation error.

---

## Team Split (3 people)

| Role | Owns |
|---|---|
| **Backend** | Laravel parse endpoint + DeepSeek service class + PHPUnit tests (Tasks 1, 2, 4) |
| **Frontend** | Vue component + Playwright E2E tests (Task 3) |
| **Full-stack / QA** | DeepSeek prompt engineering + fixture data + business case slides |

---

## 16:00 Submission Prompt

> **Team:** TimeBanders
> **Project:** HoursAI — Plain-Language Store Hours for PAR Management Center
>
> **Problem:** Configuring store hours requires navigating 7 rows of time pickers — 15+ clicks for something an operator can say in one sentence. Every misconfigured hour is a missed order or an angry guest arriving at a closed door.
>
> **Solution:** A natural-language input in Management Center where an operator types a plain-English description of their hours. DeepSeek parses it into structured config, shows a preview for confirmation, and writes to the store on approval.
>
> **Task 1 — Parse endpoint**
> `POST /api/stores/{id}/hours/parse` accepts `{ "text": "..." }`, calls DeepSeek, returns `{ days, specialClosures, orderCutoffMinutes, deliveryWindow }`.
> AC: Valid structured JSON for 5 fixture prompts. Ambiguous input returns `{ clarification_needed: true }` not 500.
> Harness: PHPUnit feature test, 5 fixtures, assertions on response shape.
>
> **Task 2 — DeepSeek parsing service**
> System prompt encodes the hours data model schema and injects current store hours from DB before every call. Returns only valid JSON. Handles: closed days, overnight hours, "30 min before close", delivery-only windows, partial updates.
> AC: All 5 prompts produce structurally valid responses. No hallucinated fields. Partial update ("Keep everything the same but close on Sundays") preserves unchanged days from injected current hours.
> Harness: Unit test with mocked response + integration test with live DeepSeek API against all 5 fixtures.
>
> **Task 3 — MC Vue component**
> Textarea + Parse button + preview card + Apply/Edit buttons.
> AC: Preview renders for all 5 fixtures. Apply calls `PATCH /api/stores/{id}/hours`. Error state on parse failure.
> Harness: Playwright E2E — type prompt → Parse → verify preview → Apply → verify network call.
>
> **Task 4 — Write-back**
> On Apply, PATCH store hours with parsed values. Transaction with rollback on error. Original hours unchanged if user clicks Edit.
> AC: DB reflects new hours after confirm. Rollback on validation error.
> Harness: PHPUnit feature test — parse → confirm → assert DB state.
>
> **Business value:** Operators manage hours ~4x per year per location. At 500 locations = 2,000 form sessions replaced by a single sentence. 15 min saved per session = 500 hours/year recovered. Reduces misconfigured-hours support tickets (currently ~12% of store ops tickets).

---

## Scoring Map

| Criterion | Points | How we win it |
|---|---|---|
| Act with Urgency | 0–1 | Ship all 4 tasks with tests green by Thu 10:00 |
| Own It | 0–3 | PHPUnit + Playwright tests all green; QA sign-off on coverage |
| Deliver Outcomes | 0–3 | Business case in slides (500 hrs/year saved); live demo with all 5 prompts |
| Win Together | 0–1 | Cross-team squad confirmed |
| Never Settle | 0–2 | The "type a sentence, database updates" wow moment in the demo |

---

## What to Demo Thursday

1. Open store hours page in MC — show the current 7-row form (the pain)
2. Switch to HoursAI tab — show the empty textarea
3. Type: *"We're open Mon–Fri 8am to 10pm, Saturday 9am to 11pm, closed Sundays"*
4. Click Parse — preview card renders instantly
5. Click Apply — success toast, hours updated
6. Type: *"Close all ordering on Christmas Day"* — show special closure parsed
7. Show the Playwright test suite running green

---

## Immediate Next Steps

- [ ] Confirm 3 team members by 13:30
- [ ] Take team photo (individual + together) by 15:00
- [ ] Send 16:00 email with the prompt above
- [ ] Backend starts Task 1 immediately after kickoff
- [ ] Frontend scaffolds Vue component with mock response while Task 1 builds