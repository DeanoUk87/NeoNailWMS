# WMS TASK 003A — BD-02 Revision: Expiry Management and Short-Dated Pricing

**Project:** MP Transport Warehouse Management System  
**Reference:** WMS-TASK-003A-REVISED-OPERATIONAL-BLUEPRINT.md — BD-02  
**Date:** 3 November 2026  
**Status:** Design revision — awaiting approval before development  
**Code changes:** NOT AUTHORISED  
**Scope:** This document revises BD-02 only. All other sections of Task 003A remain in force.

---

## Why BD-02 Was Wrong

The original BD-02 proposed a 90-day advisory threshold and a 30-day supervisor block as the primary expiry management mechanism. Paul has correctly identified that this is commercially insufficient.

MP Transport's operation requires:

- **Full-price stock** with a minimum of 12 months remaining shelf life.
- **Short-dated stock** managed through a structured, supervisor-authorised pricing programme as shelf life reduces.
- **Expired stock** that is physically blocked from being dispatched, regardless of any other system state.

These are three distinct states with different operational consequences. The original design conflated expiry warnings with commercial pricing decisions and set thresholds far too late for the actual operation.

---

## Revised Expiry and Pricing Framework

### Principle 1: Three Independent Dimensions

The system must track three separate properties for every batch, and must never conflate them:

| Dimension | What it controls | Who controls it |
|---|---|---|
| **Physical availability** | Can the stock be moved, picked, or dispatched? | System (hard rules) + supervisor |
| **Expiry classification** | How much shelf life remains? | System (calculated daily from expiry date) |
| **Commercial pricing class** | What price tier does this batch attract? | Administrator authorises; system does not change pricing automatically |

A batch may be physically available, fully in stock, and correctly located — but commercially classified as short-dated. The pricing class does not affect whether the stock exists or where it is. It affects what price it is sold at and whether it is listed on Shopify as a full-price item.

### Principle 2: No Automatic Pricing Changes

The system calculates shelf life daily and updates the expiry classification automatically. It does **not** automatically change prices in Shopify or anywhere else. Every pricing tier transition is presented to the administrator for review and requires explicit authorisation before any price change is applied. If the administrator does not act, the stock remains at its current price classification — it does not change silently.

### Principle 3: Expired Stock Is a Hard Stop

Stock past its expiry date is blocked from dispatch at the system level. This is not a warning. It is not overridable by an operator. It requires explicit supervisor action to quarantine or dispose of the stock. A picker who arrives at a bin and finds only expired stock receives a system alert; they cannot complete the pick against expired units without supervisor intervention.

---

## Section 1 — Daily Shelf Life Calculation

Each morning (or on demand), the system calculates the remaining shelf life for every batch in stock:

```
remaining_days = expiry_date - today

If expiry_date is null and no_expiry_confirmed = true:
  → Classification: NO_EXPIRY (exempt from shelf life programme)
  → FEFO does not apply; FIFO applies instead.

If manufacturer_batch_number is null (batch on hold):
  → Expiry classification cannot be determined.
  → Stock remains in HOLD status regardless of any assumed expiry.
```

The result is stored as a calculated field (not a permanent database column) and used to assign the batch's current expiry classification:

| Classification | Remaining shelf life | Meaning |
|---|---|---|
| `FULL_PRICE` | > 365 days (12 months) | Normal commercial stock — full price, unrestricted picking |
| `SHORT_12` | 274–365 days (9–12 months) | Entered short-dated programme — administrator review triggered |
| `SHORT_9` | 183–273 days (6–9 months) | Next discount tier — administrator review triggered |
| `SHORT_6` | 92–182 days (3–6 months) | Further discount tier — administrator review triggered |
| `SHORT_3` | 31–91 days (1–3 months) | Significant discount tier — administrator review triggered |
| `SHORT_1` | 1–30 days | Final tier — administrator review triggered; Shopify listing decision required |
| `EXPIRED` | 0 days or past today | Hard block — cannot be dispatched. Quarantine required. |

**The day thresholds above are provisional design defaults.** The exact day boundaries corresponding to the 12, 9, 6, 3 and 1 month milestones will vary slightly by month length. For operational clarity, thresholds should be configured in calendar days in the system settings — see BD-02A.

---

## Section 2 — Commercial Stock Classification Process

### 2.1 When a Batch Enters the Short-Dated Programme

Each morning, the system compares every batch's remaining shelf life to the classification thresholds. If a batch has crossed into a new tier since yesterday, the system:

1. Updates the batch's `expiry_classification` field.
2. Creates a `pricing_review_task` record for the administrator.
3. Sends a notification: "Batch [X] of [Product] has entered the [SHORT_12] tier. [N] units affected. Administrator review required."

**No price change occurs at this point. The batch simply has a new classification pending review.**

### 2.2 Administrator Review Workflow

The administrator reviews pricing tasks from a dedicated screen:

```
[SCREEN: Admin — Pricing Review Queue]
┌──────────────────────────────────────────────────────────────────┐
│  MP WMS  |  Pricing Review  |  3 actions pending                 │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Gel Polish Classic Red  |  Batch A2026-031                      │
│  Units in stock: 42      |  Expiry: 30/06/2027                   │
│  Remaining: 239 days     |  NEW TIER: SHORT_9                    │
│  Previous tier: SHORT_12 (approved 14 Oct 2026 by Paul)         │
│                                                                  │
│  Proposed action:                                                │
│  ● Apply discount tier SHORT_9  [Discount %: ____]              │
│  ○ Keep at SHORT_12 pricing (explain reason below)              │
│  ○ Quarantine — stock not suitable for sale at any price        │
│  ○ Defer review (snooze 7 days — reason required)              │
│                                                                  │
│  Notes: [_____________________________________________]          │
│                                                                  │
│  [Approve]  [Defer]  [Escalate to Craig]                        │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│  Gel Polish Ballet Pink  |  Batch E2026-012                      │
│  Units: 10  |  Expiry: 15/12/2027  |  Remaining: 407 days       │
│  ENTERING SHORT_12 tier for the first time                      │
│  ...                                                             │
└──────────────────────────────────────────────────────────────────┘
```

The administrator enters a discount percentage or other commercial decision. The WMS records the decision, who made it, and when. The WMS does not apply any price change to Shopify automatically — see Section 4.

### 2.3 Audit Record for Each Pricing Decision

Every pricing classification event creates an immutable `batch_pricing_events` record:

```
batch_pricing_events:
  id
  batch_id
  product_id
  units_affected (at time of decision)
  previous_classification
  new_classification
  transition_date
  days_remaining_at_transition
  administrator_decision (approved_tier | held | quarantine | deferred)
  discount_percentage_authorised (null if not a discount decision)
  decided_by (user_id)
  decided_at
  notes
  shopify_action_taken (null | manual_update | no_action)
  shopify_action_notes
```

This record cannot be updated. If the administrator changes their mind the following day, a new record is created referencing the previous one.

---

## Section 3 — FEFO Within Pricing Classes

### 3.1 Revised FEFO Rule

The original FEFO rule was: always pick the oldest expiry batch first.

This is revised to account for pricing classes:

**Default FEFO behaviour (unchanged):**
Within a pricing class, pick the batch with the earliest expiry date first.

**Cross-tier picking:**
By default, the WMS picks from the oldest classification tier first (SHORT_1 before SHORT_3 before SHORT_6 etc. before FULL_PRICE). This ensures the most short-dated stock is cleared first.

**However:** An order placed at full price should not be fulfilled from SHORT_12 stock without commercial consideration. The correct approach depends on whether the customer is buying at a tier price or full price.

This creates a dependency on order management (Phase 5): the pick list must know what price the customer paid and select a batch at that tier or older.

**Design rule:**

| Customer paid | Pick from | Note |
|---|---|---|
| Full price | FULL_PRICE batches only (or older tier with supervisor override) | Supervisor override allows downgrade to short-dated with consent |
| SHORT_12 price | SHORT_12 or older tiers | Cannot pick FULL_PRICE stock for a SHORT_12 order |
| SHORT_9 or older | SHORT_9 or older | Same principle |
| No tier specified (manual order) | Oldest tier available | FEFO as before |

**This rule cannot be implemented until order management exists (Phase 5).** Until then, FEFO proceeds as before (oldest expiry first) with the administrator managing customer communication manually.

### 3.2 FEFO Override

A FEFO override (picking a newer batch before an older one) requires supervisor approval with a recorded reason. Common legitimate reasons:

- Customer requested a specific batch (e.g. for a product shoot requiring a particular label run).
- Older batch has a known quality concern and is under investigation.
- Older batch is in a location that is temporarily inaccessible.

The override is recorded against the pick line, not the batch. It does not change the batch's expiry classification.

---

## Section 4 — Shopify Integration Considerations

### 4.1 The Problem: One SKU, Multiple Prices

Shopify's standard product model allows one price per variant. When two batches of Gel Polish Classic Red exist — Batch A (FULL_PRICE) and Batch B (SHORT_9) — they are both the same Shopify variant (`gid://shopify/ProductVariant/123`). Shopify has one price for that variant.

This is a fundamental commercial problem that requires a business decision before any technical solution can be designed.

### 4.2 Options for Shopify Short-Dated Sales

**Option A: Separate Shopify product listing for short-dated stock**

The short-dated batch is listed as a separate Shopify product (or variant) with a different title, e.g. "Gel Polish Classic Red — SHORT DATED — exp. Jun 2027". The full-price variant remains at full price. MP Transport manages inventory levels on both listings manually or through a sync process.

- Pro: Clean separation. Customers know what they are buying. Full-price customers never accidentally receive short-dated stock.
- Con: Requires creating and managing separate Shopify listings. Manual process unless automated.

**Option B: Single listing, WMS manages batch allocation by order type**

One Shopify listing. WMS fulfils short-dated orders from short-dated batches and full-price orders from full-price batches, based on the order tag or price paid.

- Pro: Simpler Shopify catalogue.
- Con: Requires Shopify order tagging or discount code tracking. WMS must know the price the customer paid to select the right batch. Complex to implement correctly.

**Option C: Short-dated stock sold through a different channel**

Short-dated stock is not sold through the main Shopify store at all. It is sold via B2B, clearance, trade buyers or disposal. WMS manages it as a separate stock category. No Shopify integration for short-dated lines.

- Pro: No Shopify complexity.
- Con: Requires an alternative sales channel to be managed.

**Option D: Shopify draft orders / manual pricing**

Short-dated stock is sold through Shopify manual/draft orders at a custom price, not listed on the main storefront.

- Pro: Avoids storefront complexity.
- Con: Requires manual order creation per customer.

**The WMS system design must accommodate whichever option Paul and Craig choose.** The WMS will:
- Track which batches are in which pricing tier.
- Record administrator-authorised discount percentages.
- Present this information to the person managing Shopify.
- Not automatically change Shopify prices or create Shopify listings.

### 4.3 Inventory Sync Implications

Currently (Phase 2), the system has no live Shopify inventory sync. When sync is built (Phase 5), it must be designed to:

- Only push FULL_PRICE AVAILABLE stock to the main Shopify variant's inventory level.
- Not include SHORT_12 or lower batches in the main listing's inventory, unless a separate Shopify variant has been created for short-dated stock.
- Treat EXPIRED stock as zero inventory on all Shopify listings — regardless of physical quantity.

### 4.4 Expired Stock and Shopify

**The system must set Shopify inventory to zero for any batch that reaches EXPIRED status, regardless of physical quantity.**

This prevents a customer ordering a product that will fail at dispatch verification. The mechanism:

1. Each morning, the system identifies batches that crossed into EXPIRED since the previous run.
2. For each expired batch, the system calculates how many units are no longer available.
3. A `shopify_inventory_update_task` is created for the administrator.
4. The administrator reviews and manually applies the inventory change in Shopify (or approves an automated update if that feature is built).
5. **No expired unit is ever dispatched.** The picking system blocks dispatch of expired batches at the pick stage, regardless of whether Shopify has been updated.

---

## Section 5 — Hard Block on Expired Stock Dispatch

This is enforced at two points, independently:

### 5.1 At Pick List Generation

When a pick list is generated for an order:

- The system calculates remaining shelf life for all candidate batches.
- Batches with `expiry_date <= today` are excluded from the pick list.
- If no non-expired stock is available: the order line is flagged as `UNFULFILLABLE — NO VALID STOCK`. Supervisor is notified. Order is held.

### 5.2 At Physical Dispatch Confirmation

When an operator confirms dispatch:

- The system re-checks the expiry date of every batch in the packed order.
- If any batch has expired between pick and dispatch (unusual but possible for very near-expiry stock): dispatch is blocked.
- Operator sees: "DISPATCH BLOCKED — Batch [X] of [Product] has expired. Supervisor authorisation required."
- Supervisor can authorise disposal of the expired units and replacement pick from valid stock, or cancel the dispatch.

**There is no operator-level override for expired stock dispatch. It requires explicit supervisor action and creates an audit record.**

---

## Section 6 — Physical Handling of Short-Dated Stock

Short-dated stock is not quarantined — it is still physically available, correctly located, and eligible for picking within its tier. However, it must be distinguishable from full-price stock in the warehouse.

**Proposed physical controls:**

| Classification | Physical label change | Picking restriction |
|---|---|---|
| SHORT_12 and below | Amber label applied to carton or shelf edge indicating tier and expiry | Picked for appropriate-tier orders only |
| SHORT_1 | Red label — "FINAL CLEARANCE" | Supervisor reviews before any pick |
| EXPIRED | Blocked physically — remove to Q zone | Cannot be picked under any circumstances |

**Label update process:** When a batch enters a new tier, the WMS generates a label update task for the warehouse. An operator visits the relevant bins and applies updated labels. The task is marked complete when the operator confirms the labels have been replaced.

This is a manual physical process. The WMS can prompt it but cannot verify it remotely. This is an operational discipline matter.

---

## Section 7 — Revised Data Structures for Expiry Classification

> Design only. No code changes authorised.

The following additions to the proposed data structures in Task 003A Section 8.1 are required:

```
batches (additions to existing proposal):
  + expiry_classification (FULL_PRICE|SHORT_12|SHORT_9|SHORT_6|
                           SHORT_3|SHORT_1|EXPIRED|NO_EXPIRY|UNKNOWN)
    — calculated daily, stored for query performance
  + last_classification_update (datetime)
  + last_classification_check (datetime)

pricing_review_tasks (new table):
  id, batch_id, product_id, triggered_at, previous_classification,
  new_classification, days_remaining_at_trigger,
  status (PENDING|APPROVED|DEFERRED|CANCELLED),
  assigned_to (user_id — admin role required),
  due_by (triggered_at + configurable review window, default 2 days),
  completed_at

batch_pricing_events (new table — immutable):
  id, batch_id, pricing_review_task_id, previous_classification,
  new_classification, transition_date, days_remaining_at_transition,
  units_in_stock_at_time,
  administrator_decision (approved_tier|held|quarantine|deferred),
  discount_percentage_authorised (nullable),
  decided_by, decided_at, notes,
  shopify_action_taken (none|manual|automated),
  shopify_action_notes

expiry_policy_config (system settings — one row per client):
  id, fulfilment_client_id,
  threshold_short_12_days (default 365),
  threshold_short_9_days (default 274),
  threshold_short_6_days (default 183),
  threshold_short_3_days (default 92),
  threshold_short_1_days (default 31),
  review_window_days (days admin has to act before escalation, default 2),
  near_expired_alert_days (additional advisory alert, default 14),
  applies_to_product_categories (JSON array of category IDs — empty means all)
```

---

## Section 8 — Outstanding Business Decisions (BD-02A and beyond)

### BD-02A: Expiry threshold day values

**Revised from BD-02.** The original proposal of 90-day and 30-day thresholds is replaced by the 12/9/6/3/1 month programme described above.

**Decision required:** Confirm the exact day boundaries for each tier. The values below are provisional:

| Tier | Provisional day boundary |
|---|---|
| FULL_PRICE | > 365 days |
| SHORT_12 | 274–365 days |
| SHORT_9 | 183–273 days |
| SHORT_6 | 92–182 days |
| SHORT_3 | 31–91 days |
| SHORT_1 | 1–30 days |

Some months have 28–31 days, so "12 months" can mean 365 or 366 days. Paul should confirm whether calendar month calculations (same day next year) or fixed day counts are preferred. Fixed day counts are simpler and more consistent. Calendar month calculations are more natural for communication with customers and buyers.

---

### BD-02B: Discount percentages per tier

**This is explicitly left as a commercial decision.** No discount percentages are hardcoded. The WMS provides a field for the administrator to enter the percentage authorised at each tier transition. The percentages are not preset.

**Decision required:** Paul/Craig to agree the commercial discount structure at each tier. This can be agreed internally and configured in the WMS without it being visible to operators or customers. It may also vary by product category.

---

### BD-02C: Which products are subject to the shelf-life programme?

Gel and cosmetic products clearly require shelf-life management. Accessories (nail files, buffers, tools) may not have expiry dates.

**Decision required:** Confirm which product categories are enrolled in the shelf-life pricing programme. Products outside the programme are classified as `NO_EXPIRY` and are not shown in the pricing review queue.

---

### BD-02D: Shopify approach for short-dated stock

**Decision required:** Choose from Options A–D in Section 4.2. This decision must be made before any Shopify inventory sync is designed (Phase 5). The decision affects whether a separate Shopify variant or product is needed for each short-dated batch.

| Option | Shopify complexity | WMS complexity | Customer transparency |
|---|---|---|---|
| A: Separate listing per tier | Medium | Low | High |
| B: Single listing, WMS allocates by order type | Low | High | Low |
| C: Alternative sales channel | None | Low | High (different channel) |
| D: Draft orders | None | None | Medium |

---

### BD-02E: Administrator review window

When a batch enters a new tier, how long does the administrator have to review before the system escalates or sends a reminder?

**Recommended default:** 2 working days. After 2 days without action, the system sends a reminder and escalates to a second administrator (if configured).

**Decision required:** Confirm the review window. Also confirm who receives the escalation.

---

### BD-02F: FEFO and pricing tier interaction for orders

As described in Section 3.1, once order management is built (Phase 5), the pick list must know what price the customer paid to select the correct tier of stock.

**Decision required:** When a customer orders at full price, can short-dated stock ever be substituted? Under what circumstances? Who authorises this?

This cannot be implemented until Phase 5 but the commercial policy must be agreed before the pick logic is designed.

---

## Summary of Changes to Task 003A

This document revises the following:

| Section in 003A | Revision |
|---|---|
| BD-02 | Replaced entirely with BD-02A through BD-02F |
| §4 (stock states) | FULL_PRICE, SHORT_12–SHORT_1, and EXPIRED are sub-classifications of physical stock states, not replacements for them |
| §8 (data structures) | `batches` table extended; `pricing_review_tasks` and `batch_pricing_events` tables added |
| §5 (FEFO) | FEFO applies within-tier; cross-tier rules depend on order pricing — deferred to Phase 5 |

All other sections of Task 003A remain unchanged.

---

## What Requires Paul and Craig's Decision Before Development

| ID | Decision | Urgency |
|---|---|---|
| BD-02A | Exact day boundaries for each expiry tier | HIGH — needed before receiving module is built |
| BD-02B | Discount percentages per tier | MEDIUM — needed before pricing review workflow is built |
| BD-02C | Which product categories are enrolled in the programme | HIGH — affects batch setup for first receiving session |
| BD-02D | Shopify approach for short-dated stock | HIGH — affects Phase 5 Shopify sync design |
| BD-02E | Administrator review window duration | MEDIUM — needed before pricing review workflow is built |
| BD-02F | FEFO override policy for cross-tier orders | MEDIUM — needed before Phase 5 pick list design |

---

*Design revision only. No application code, database structure or production settings have been changed.*  
*All screen concepts are design proposals. None are implemented.*  
*Produced 3 November 2026 — MP Transport WMS Project*
