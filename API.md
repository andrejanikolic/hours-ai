# HoursAI — API Specification

Base URL: `http://localhost:8080/api`

All requests/responses use JSON. No authentication for the hackathon scope.

---

## Domain Hierarchy

```
Brand
  └── Venue (one brand, many venues)
        └── OrderType  (pickup, delivery, dine-in, drive-thru)
  └── Menu  (one brand, many menus)

ServingTimes — polymorphic, attachable to: Brand, Venue, Menu, OrderType
```

Every entity supports:
1. **Manual serving times** — CRUD on `/serving-times`
2. **AI parse** — `POST /serving-times/parse` → preview JSON → client calls PATCH to apply

---

## Shared: ServingTime Schema

```json
{
  "id": 1,
  "parent_type": "brand | venue | menu | order_type",
  "parent_id": 1,
  "type": "weekday | special",
  "days": ["monday", "tuesday", "wednesday", "thursday", "friday", "saturday", "sunday"],
  "date": "YYYY-MM-DD",
  "date_to": "YYYY-MM-DD | null",
  "time_from": "HH:MM",
  "time_to": "HH:MM",
  "working": true
}
```

**Type rules:**
- `weekday` — use `days` array + `time_from` + `time_to` + `working`. `date` and `date_to` are null.
- `special` — use `date` (required) + `date_to` (optional range end) + `time_from` + `time_to` + `working`. `days` is null.

**`working: false`** marks the period as closed (overrides default open hours).

---

## Shared: AI Parse Endpoint Pattern

Every entity has this endpoint:

```
POST /{entity}/{id}/serving-times/parse
Body: { "text": "plain English description" }
```

Returns a **preview** — the same shape as a `GET /serving-times` response.
The client shows this preview for confirmation, then calls `PUT /{entity}/{id}/serving-times/replace` to apply.

**Parse response:**
```json
{
  "serving_times": [
    {
      "type": "weekday",
      "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
      "time_from": "08:00",
      "time_to": "22:00",
      "working": true
    },
    {
      "type": "weekday",
      "days": ["saturday", "sunday"],
      "time_from": "09:00",
      "time_to": "23:00",
      "working": true
    }
  ],
  "clarification_needed": false,
  "clarification_question": null
}
```

**Apply parsed result:**
```
PUT /{entity}/{id}/serving-times/replace
Body: { "serving_times": [...] }   ← same array from parse response
```
Replaces all existing serving times for the entity in a transaction.

---

## Brands

### List brands
```
GET /brands
Response 200:
{
  "data": [
    { "id": 1, "name": "Demo Burger", "slug": "demo-burger", "created_at": "..." }
  ]
}
```

### Create brand
```
POST /brands
Body: { "name": "Demo Burger" }
Response 201: { "data": { "id": 1, "name": "Demo Burger", "slug": "demo-burger" } }
```

### Get brand
```
GET /brands/{id}
Response 200: { "data": { "id": 1, "name": "Demo Burger", "slug": "demo-burger" } }
Response 404: { "message": "Brand not found" }
```

### Update brand
```
PATCH /brands/{id}
Body: { "name": "New Name" }
Response 200: { "data": { ... updated brand ... } }
```

### Delete brand
```
DELETE /brands/{id}
Response 204
```

### Brand serving times
```
GET  /brands/{id}/serving-times
POST /brands/{id}/serving-times
DELETE /brands/{id}/serving-times/{servingTimeId}
POST /brands/{id}/serving-times/parse
PUT  /brands/{id}/serving-times/replace
```

---

## Venues

### List venues for brand
```
GET /brands/{brandId}/venues
Response 200:
{
  "data": [
    { "id": 1, "brand_id": 1, "name": "Downtown", "address": "123 Main St", "timezone": "America/New_York" }
  ]
}
```

### Create venue
```
POST /brands/{brandId}/venues
Body: {
  "name": "Downtown",
  "address": "123 Main St",
  "timezone": "America/New_York"
}
Response 201: { "data": { "id": 1, ... } }
```

### Get venue
```
GET /venues/{id}
Response 200: { "data": { "id": 1, "brand_id": 1, "name": "Downtown", "address": "...", "timezone": "..." } }
```

### Update venue
```
PATCH /venues/{id}
Body: { "name": "...", "address": "...", "timezone": "..." }
Response 200: { "data": { ... } }
```

### Delete venue
```
DELETE /venues/{id}
Response 204
```

### Venue serving times
```
GET  /venues/{id}/serving-times
POST /venues/{id}/serving-times
DELETE /venues/{id}/serving-times/{servingTimeId}
POST /venues/{id}/serving-times/parse
PUT  /venues/{id}/serving-times/replace
```

---

## Menus

### List menus for brand
```
GET /brands/{brandId}/menus
Response 200:
{
  "data": [
    { "id": 1, "brand_id": 1, "name": "Breakfast", "description": "Morning items", "active": true }
  ]
}
```

### Create menu
```
POST /brands/{brandId}/menus
Body: { "name": "Breakfast", "description": "Morning items" }
Response 201: { "data": { "id": 1, ... } }
```

### Get menu
```
GET /menus/{id}
Response 200: { "data": { "id": 1, "brand_id": 1, "name": "Breakfast", "description": "...", "active": true } }
```

### Update menu
```
PATCH /menus/{id}
Body: { "name": "...", "description": "...", "active": true|false }
Response 200: { "data": { ... } }
```

### Delete menu
```
DELETE /menus/{id}
Response 204
```

### Menu serving times
```
GET  /menus/{id}/serving-times
POST /menus/{id}/serving-times
DELETE /menus/{id}/serving-times/{servingTimeId}
POST /menus/{id}/serving-times/parse
PUT  /menus/{id}/serving-times/replace
```

---

## Order Types

Order types are predefined and attached to venues. The global list is seeded.

**Global order type values:**

| id | name | slug |
|---|---|---|
| 1 | Pickup | pickup |
| 2 | Delivery | delivery |
| 3 | Dine In | dine-in |
| 4 | Drive Thru | drive-thru |

### List order types attached to venue
```
GET /venues/{venueId}/order-types
Response 200:
{
  "data": [
    { "id": 1, "name": "Pickup", "slug": "pickup", "active": true }
  ]
}
```

### Attach order type to venue
```
POST /venues/{venueId}/order-types
Body: { "order_type_id": 1 }
Response 201: { "data": { "id": 1, "name": "Pickup", "slug": "pickup", "active": true } }
Response 409: { "message": "Order type already attached" }
```

### Toggle active
```
PATCH /venues/{venueId}/order-types/{orderTypeId}
Body: { "active": false }
Response 200: { "data": { ... } }
```

### Detach order type from venue
```
DELETE /venues/{venueId}/order-types/{orderTypeId}
Response 204
```

### Order type serving times (scoped to venue)
```
GET  /venues/{venueId}/order-types/{orderTypeId}/serving-times
POST /venues/{venueId}/order-types/{orderTypeId}/serving-times
DELETE /venues/{venueId}/order-types/{orderTypeId}/serving-times/{servingTimeId}
POST /venues/{venueId}/order-types/{orderTypeId}/serving-times/parse
PUT  /venues/{venueId}/order-types/{orderTypeId}/serving-times/replace
```

---

## ServingTimes — Shared Endpoints Detail

### GET serving-times
Returns all serving times for the entity.

```
GET /venues/1/serving-times
Response 200:
{
  "data": [
    {
      "id": 1,
      "parent_type": "venue",
      "parent_id": 1,
      "type": "weekday",
      "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
      "date": null,
      "date_to": null,
      "time_from": "08:00",
      "time_to": "22:00",
      "working": true
    },
    {
      "id": 2,
      "parent_type": "venue",
      "parent_id": 1,
      "type": "special",
      "days": null,
      "date": "2026-12-25",
      "date_to": null,
      "time_from": null,
      "time_to": null,
      "working": false
    }
  ]
}
```

### POST serving-times (add one slot)
```
POST /venues/1/serving-times
Body (weekday):
{
  "type": "weekday",
  "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
  "time_from": "08:00",
  "time_to": "22:00",
  "working": true
}

Body (special date):
{
  "type": "special",
  "date": "2026-12-25",
  "date_to": null,
  "time_from": null,
  "time_to": null,
  "working": false
}

Response 201: { "data": { ... serving time ... } }
Response 422: { "message": "Validation error", "errors": { ... } }
```

### DELETE serving-times/{id}
```
DELETE /venues/1/serving-times/1
Response 204
```

### POST serving-times/parse (AI)
```
POST /venues/1/serving-times/parse
Body: { "text": "We're open Mon–Fri 8am to 10pm, closed weekends." }
Response 200:
{
  "serving_times": [
    {
      "type": "weekday",
      "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
      "time_from": "08:00",
      "time_to": "22:00",
      "working": true
    },
    {
      "type": "weekday",
      "days": ["saturday", "sunday"],
      "time_from": null,
      "time_to": null,
      "working": false
    }
  ],
  "clarification_needed": false,
  "clarification_question": null
}

Response 200 (ambiguous):
{
  "serving_times": [],
  "clarification_needed": true,
  "clarification_question": "Did you mean closed all day on weekends, or just after 6pm?"
}
```

### PUT serving-times/replace (apply parsed result)
```
PUT /venues/1/serving-times/replace
Body:
{
  "serving_times": [
    {
      "type": "weekday",
      "days": ["monday", "tuesday", "wednesday", "thursday", "friday"],
      "time_from": "08:00",
      "time_to": "22:00",
      "working": true
    }
  ]
}
Response 200: { "data": [ ... all new serving times ... ] }
Response 422: { "message": "Validation error", "errors": { ... } }
```

---

## Database Schema (reference)

```sql
brands
  id, name, slug, created_at, updated_at

venues
  id, brand_id, name, address, timezone, created_at, updated_at

menus
  id, brand_id, name, description, active, created_at, updated_at

order_types
  id, name, slug

venue_order_types
  id, venue_id, order_type_id, active, created_at, updated_at

serving_times
  id, parent_type (brand|venue|menu|order_type), parent_id
  type (weekday|special)
  days (JSON array of day strings, nullable)
  date (date, nullable), date_to (date, nullable)
  time_from (time, nullable), time_to (time, nullable)
  working (boolean)
  created_at, updated_at
```

---

## Validation Rules Summary

| Field | Rule |
|---|---|
| `type` | required, in: weekday, special |
| `days` | required if type=weekday, array of: monday–sunday |
| `date` | required if type=special, format: Y-m-d |
| `date_to` | optional, format: Y-m-d, after_or_equal: date |
| `time_from` | required if working=true, format: H:i |
| `time_to` | required if working=true, format: H:i, after: time_from |
| `working` | required, boolean |
| `text` (parse) | required, string, max: 1000 |

---

## Error Responses

```json
404: { "message": "Resource not found" }
409: { "message": "Conflict description" }
422: { "message": "The given data was invalid.", "errors": { "field": ["message"] } }
500: { "message": "Internal server error" }
```
