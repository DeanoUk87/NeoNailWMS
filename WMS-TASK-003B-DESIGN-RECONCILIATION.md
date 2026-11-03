# WMS TASK 003B — Design Reconciliation

**Project:** MP Transport Warehouse Management System  
**Supersedes BD-02 as written in:** WMS-TASK-003A-BD02-EXPIRY-PRICING-REVISION.md  
**Reconciles contradictions in:** WMS-TASK-003A-REVISED-OPERATIONAL-BLUEPRINT.md  
**Date:** 3 November 2026  
**Status:** Design reconciliation — awaiting approval before development  
**Code changes:** NOT AUTHORISED  
**STOP point:** Paul and Craig must review and approve this document before Phase 3 development begins.

---

## Purpose of This Document

This document does four things:

1. **Replaces BD-02** with the simplified expiry and shelf-life monitoring framework Paul has approved in principle.
2. **Corrects four errors** introduced in the BD-02 revision document (fixed-day approximations for months, incorrect classification example, unnecessary discount workflow, premature Shopify exclusion of short-dated stock).
3. **Resolves three internal contradictions** identified across Task 003A: the stock-ledger `available` status timing, the bin-capacity assumption conflict, and the shortage-approval percentage disagreement.
4. **Consolidates all outstanding business decisions** in one place for Paul and Craig to review.

After this document is approved, the design series (003, 003A, 003B) forms a coherent and authorised specification for Phase 3 development.

---

## Part 1 — BD-02 Simplified: Expiry Monitoring and Shelf-Life Classification

### 1.1 What Changes from the BD-02 Revision

| Point Paul raised | Change made |
|---|---|
| Use calendar-month thresholds, not fixed day approximations | Calendar month calculation defined — see §1.3 |
| Incorrect example: 407 days shown as SHORT_12 | Corrected — 407 days is FULL_PRICE (> 12 calendar months) |
| Management review queue only — no discount percentage entry | `pricing_review_tasks` and `batch_pricing_events` tables removed; replaced with a simple `shelf_life_notifications` log |
| No automatic Shopify price changes | Unchanged — confirmed |
| Do not automatically exclude short-dated stock from Shopify | Previous text that pushed only FULL_PRICE to Shopify is removed — see §1.7 |
| Full-price orders must not receive short-dated stock | Confirmed as a future picking rule — deferred to Phase 5 |
| Expired stock hard-blocked at allocation, picking and dispatch | Confirmed at three independent enforcement points — see §1.8 |
| Do not invent expiry dates; preserve manufacturer precision | Confirmed — see §1.4 |

---

### 1.2 Three Independent Dimensions (retained from BD-02 revision)

The system tracks three separate properties per batch. They do not override each other.

| Dimension | What it controls | Who controls it |
|---|---|---|
| **Physical availability** | Can this stock be moved, picked or dispatched? | System hard rules + supervisor |
| **Shelf-life classification** | How much shelf life remains? | System calculates daily |
| **Commercial selling arrangement** | At what price and through which channel is this batch sold? | Management decision — outside the WMS at this stage |

A batch that is physically available and correctly located may be classified as short-dated. Its shelf-life classification does not affect its physical location or its stock quantity. It affects what the system presents to management for review, and — once order management is built — whether it may be allocated against a full-price order.

---

### 1.3 Calendar-Month Threshold Calculation

**Correction applied:** The BD-02 revision used fixed day approximations (e.g. 365 days for 12 months, 274 for 9 months). This is replaced by exact calendar-month calculation.

**Definition:** Remaining shelf life in calendar months is calculated as the number of complete calendar months between today and the expiry date, using the same day-of-month rule used in everyday date arithmetic.

Examples (assuming today is 3 November 2026):

| Expiry date | Remaining calendar months | Classification |
|---|---|---|
| 2 November 2027 | 11 months, 29 days | SHORT_12 (< 12 months remaining) |
| 3 November 2027 | 12 months exactly | FULL_PRICE (≥ 12 months remaining) |
| 4 November 2027 | 12 months, 1 day | FULL_PRICE |
| 15 December 2027 | 13 months, 12 days | FULL_PRICE |
| 30 June 2027 | 7 months, 27 days | SHORT_9 (< 9 months remaining) |
| 3 August 2027 | 9 months exactly | FULL_PRICE (≥ 9 months remaining) |
| 15 December 2026 | 1 month, 12 days | SHORT_3 (< 3 months remaining) |
| 3 November 2026 | 0 months, 0 days | EXPIRED |

**Corrected classification table:**

| Classification | Remaining shelf life | Physical availability | System action |
|---|---|---|---|
| `FULL_PRICE` | ≥ 12 calendar months | Fully available | No notification |
| `SHORT_12` | ≥ 9 and < 12 calendar months | Available | Notification raised for management review |
| `SHORT_9` | ≥ 6 and < 9 calendar months | Available | Notification raised |
| `SHORT_6` | ≥ 3 and < 6 calendar months | Available | Notification raised |
| `SHORT_3` | ≥ 1 and < 3 calendar months | Available | Notification raised |
| `SHORT_1` | ≥ 0 days and < 1 calendar month | Available | Notification raised — management attention required |
| `EXPIRED` | Past expiry date | **Hard blocked** | Quarantine task raised; Shopify excluded |
| `NO_EXPIRY` | No expiry date (confirmed) | Available | FIFO applies; no shelf-life notifications |
| `EXPIRY_UNKNOWN` | Batch on hold — no manufacturer batch number | Blocked until resolved | Supervisor hold task |

**Corrected example from the BD-02 revision:**

The BD-02 revision screen example showed:
> Gel Polish Ballet Pink | Batch E2026-012 | Remaining: 407 days | ENTERING SHORT_12 tier

**This was wrong.** 407 days from 3 November 2026 is approximately 14 November 2027 — more than 12 calendar months ahead. The batch would be classified as **FULL_PRICE**, not SHORT_12.

The corrected example for a batch genuinely entering SHORT_12 would be a batch expiring on, say, 30 September 2027 — 10 months and 27 days from today — which is less than 12 calendar months and therefore SHORT_12.

---

### 1.4 Manufacturer Expiry Information — Precision and Integrity

**Correction applied:** The WMS must preserve manufacturer-provided expiry information exactly as supplied. It must not invent, approximate or adjust dates.

**Rules:**

- If the manufacturer provides a full date (day/month/year): record exactly that date.
- If the manufacturer provides month and year only (common on cosmetics: "EXP 06/2027"): record as the last day of that month — 30/06/2027. This is the most conservative interpretation and is operationally correct. The operator selects "month/year only" on the batch screen; the system stores 30/06/2027 and displays "EXP 06/2027 (last day of month assumed)" so the precision is transparent.
- If the manufacturer provides year only: record as 31/12 of that year with a "year only" flag. Visible to management.
- If no expiry date is present: the operator either confirms "no expiry applicable" (for accessories — requires product category to be configured as exempt by admin) or places the batch on hold as `EXPIRY_UNKNOWN`.

**The system must never**:
- Assume an expiry date from previous deliveries of the same SKU.
- Copy an expiry date from one batch to another.
- Generate a calculated expiry date based on product type.
- Accept an expiry date in the past without immediately classifying as EXPIRED and raising a quarantine task.

---

### 1.5 Daily Shelf-Life Classification Process

Each morning (configurable time, default 06:00), the system:

1. For every batch with a known expiry date and status not already EXPIRED or QUARANTINE:
   - Calculates remaining shelf life in calendar months.
   - Determines the current classification tier.
   - Compares to the previous classification stored on the batch record.

2. If the classification has changed since yesterday (a threshold has been crossed):
   - Updates `batches.expiry_classification`.
   - Creates a `shelf_life_notifications` record (see §1.6).
   - Sends a notification to admin-role users.

3. If the classification is now EXPIRED:
   - Updates `batches.expiry_classification = EXPIRED`.
   - Sets `stock_entries.status = QUARANTINE` for all stock entries linked to this batch.
   - Raises a `quarantine_records` entry for each affected stock entry.
   - Creates a management notification: "Batch [X] of [Product] has expired. [N] units quarantined. Action required."

---

### 1.6 Management Review Queue — Simplified

**Correction applied:** The BD-02 revision included a `pricing_review_tasks` table with discount percentage fields, approve/defer/escalate workflow, and an `administrator_decision` field with options like "approved_tier" and "held". This is premature and unnecessary at this stage.

**Replacement:** A simple `shelf_life_notifications` log and a read-only management queue screen.

**What the simplified system does:**

- Records each threshold crossing with the batch details, quantity affected, and the new classification.
- Presents these to management in a review queue.
- Allows management to mark a notification as "reviewed" with free-text notes.
- Does nothing else automatically.

**What it does not do:**

- Does not enter or store discount percentages.
- Does not apply pricing changes anywhere.
- Does not defer, approve or escalate notifications — marking as "reviewed" is sufficient.
- Does not update Shopify.
- Does not restrict picking based on the notification status.

**Data structure (replacing `pricing_review_tasks` and `batch_pricing_events`):**

```
shelf_life_notifications
  id
  fulfilment_client_id
  batch_id
  product_id
  transition_date          (date the threshold was crossed)
  previous_classification  (e.g. FULL_PRICE)
  new_classification       (e.g. SHORT_12)
  months_remaining         (decimal, e.g. 11.9)
  units_in_stock           (quantity at time of notification)
  bin_locations            (JSON — list of location codes where this batch sits)
  expiry_date              (from batch record)
  reviewed                 (boolean, default false)
  reviewed_by              (user_id, nullable)
  reviewed_at              (datetime, nullable)
  review_notes             (text, nullable)
  created_at
```

This table is append-only. Notifications are never deleted. If a batch crosses from SHORT_12 to SHORT_9, a second notification is created. Management can see the full history.

**Management review queue screen:**

```
[SCREEN: Management — Shelf Life Review]
┌──────────────────────────────────────────────────────────────────┐
│  MP WMS  |  Shelf Life Notifications  |  4 unreviewed            │
├──────────────────────────────────────────────────────────────────┤
│  Filter: ● Unreviewed  ○ All  ○ Expiring < 3 months             │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Gel Polish Classic Red  |  Batch A2026-031                      │
│  Expiry: 30 Jun 2027  |  Remaining: 7 months 27 days            │
│  Classification: SHORT_9  (was FULL_PRICE — crossed 9-month mark)│
│  Units in stock: 42                                              │
│  Locations: A-02-C-04 (20), A-02-C-05 (22)                      │
│  Crossed on: 3 Nov 2026                                          │
│                                                                  │
│  Notes: [_____________________________________________]          │
│  [Mark as reviewed]                                              │
│                                                                  │
├──────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Gel Polish Ballet Pink  |  Batch E2026-099                      │
│  Expiry: 30 Sep 2027  |  Remaining: 10 months 27 days           │
│  Classification: SHORT_12  (crossed 12-month mark)              │
│  Units in stock: 10                                              │
│  Locations: A-02-C-06 (10)                                       │
│  Crossed on: 3 Nov 2026                                          │
│                                                                  │
│  Notes: [_____________________________________________]          │
│  [Mark as reviewed]                                              │
│                                                                  │
└──────────────────────────────────────────────────────────────────┘
```

Note: the example now shows a batch expiring 30 Sep 2027 (10 months, 27 days from today) correctly classified as SHORT_12. The 407-day / Ballet Pink example from the BD-02 revision has been removed.

---

### 1.7 Short-Dated Stock and Shopify

**Correction applied:** The BD-02 revision stated that the Shopify inventory sync should "only push FULL_PRICE AVAILABLE stock to the main Shopify variant's inventory level" and should "not include SHORT_12 or lower batches." This is removed.

**Corrected rule:** The WMS does not automatically exclude any short-dated stock from Shopify inventory until the commercial selling arrangements for short-dated stock have been agreed by management (see BD-02D, preserved below as an outstanding decision).

Until that decision is made:
- Shopify inventory figures are managed manually by MP Transport.
- The WMS provides the correct quantities by classification in the management review queue.
- The WMS does not push any inventory figures to Shopify automatically in the current phase (Phase 2/3).
- When Shopify inventory sync is built (Phase 5), the handling of short-dated stock will be designed in accordance with the decision made on BD-02D at that time.

**What the WMS does do automatically regarding Shopify and expiry:**

- **Expired stock only:** When a batch crosses into EXPIRED status, the WMS generates a management notification that includes the affected Shopify variant GID (if mapped) and the quantity that must be removed from Shopify availability. Management acts on this manually. The WMS does not push the change automatically at this stage.
- The picking and dispatch hard blocks (§1.8) ensure expired stock cannot be despatched regardless of what Shopify shows.

---

### 1.8 Expired Stock — Hard Block at Three Points (unchanged in principle, clarified)

Expired stock is blocked at three independent enforcement points. No operator-level override exists at any of them.

**Point 1 — Allocation (when an order line is being assigned to stock):**
Any batch with `expiry_date <= today` is excluded from allocation candidates. If no non-expired stock is available for an order line, the line is marked `UNFULFILLABLE — NO VALID STOCK` and the supervisor is notified.

**Point 2 — Pick list generation:**
When a pick list is printed or displayed to an operator, the system re-checks expiry for every batch on the list. Any batch that expired since allocation is removed from the list and the order line is held for supervisor review.

**Point 3 — Dispatch confirmation:**
When the operator confirms a packed order for dispatch, the system re-checks expiry for every batch in the packed shipment. If any batch has expired between picking and dispatch confirmation, dispatch is blocked:

```
DISPATCH BLOCKED
────────────────
Batch A2026-031 of Gel Polish Classic Red
expired on 30 Jun 2027.

This order cannot be despatched.

A supervisor must review this order.
[Contact supervisor]
```

The supervisor must either:
- Cancel the dispatch and arrange a replacement pick from valid stock.
- Quarantine the expired units and arrange a replacement.

There is no way to override this block at operator level.

**Expired stock and Shopify inventory:** Management is notified when any batch expires. The Shopify inventory figures for affected variants should be reviewed and adjusted manually by management. The dispatch hard block ensures no expired unit leaves the warehouse even if the Shopify inventory figure has not yet been corrected.

---

## Part 2 — Contradiction Resolution

### Contradiction 1 — Stock Ledger: `available` Status Before Putaway

**Where it appears:**

Task 003 (original), §6.2:
> `APPROVED — AWAITING PUTAWAY (stock_entries created, status = available, but location_id = null)`

This directly contradicts the correction made in Task 003A, §4 (C-02):
> "Stock must not be made available for picking or Shopify inventory synchronisation until it has passed the necessary verification and is physically located in an eligible storage position."

Additionally, Task 003A §8.1 defines `stock_entries.location_id` as `nullable` — which is technically correct (a stock entry exists before putaway confirms a location) but the description made it ambiguous whether a stock entry with `location_id = null` could have `status = AVAILABLE`.

**Resolution:**

The rule is clear and unambiguous:

**A `stock_entry` may only have `status = AVAILABLE` if and only if `location_id` is non-null AND the referenced location is in an eligible storage zone (not I, Q, D, or R zone).**

This is enforced as a database check constraint and as an application-layer validation. The system will not permit a status transition to AVAILABLE without a confirmed, eligible location.

**Corrected stock entry lifecycle:**

```
stock_entry created (status = APPROVED_PUTAWAY, location_id = null)
    ↓
Operator scans bin during putaway
    ↓
stock_entry updated (status = IN_LOCATION, location_id = <bin id>)
    ↓
[AUTO] System checks: is the bin in an eligible zone?
    If yes → stock_entry updated (status = AVAILABLE)
    If no  → status remains IN_LOCATION with a flag for supervisor review
    ↓
AVAILABLE — eligible for allocation, picking, Shopify sync
```

A stock entry with `location_id = null` may only have status `APPROVED_PUTAWAY` or `RECEIVED_UNVERIFIED` or `PENDING_SUPERVISOR`. It may not be AVAILABLE, RESERVED, IN_LOCATION or DISPATCHED.

**Database constraint (proposed):**
```sql
CONSTRAINT chk_available_requires_location
  CHECK (
    status != 'AVAILABLE'
    OR (location_id IS NOT NULL)
  )
```

---

### Contradiction 2 — Bin Capacity: Optional vs Required in Allocation Logic

**Where it appears:**

Task 003A §6.2 Step 5 (bin allocation decision order):
> "If bin capacity is configured: only recommend bins with sufficient remaining capacity for the quantity being put away."

Task 003A BD-03:
> "Provisional assumption: Supported but optional per bin. Bins without a configured capacity are unlimited."

These two statements are technically compatible but operationally ambiguous. They imply the system can recommend a bin for which it has no capacity information — which means the system can recommend a bin that is already physically full. The operator then discovers this physically and has no way to tell the system the bin is full before the capacity limit is ever configured.

**Resolution:**

Bin capacity remains optional per bin. The ambiguity is resolved by defining the system's behaviour explicitly when capacity is not configured:

**When a bin has no configured capacity:**
- The system recommends it as a candidate based on steps 1–4 and 6–7 of the allocation logic.
- The recommendation screen displays: "Capacity: not configured" rather than showing a remaining capacity figure.
- The operator places stock in the bin and confirms. The system records the quantity placed.
- The system accumulates quantity placed in the bin across all putaway transactions. This running total is visible on the bin detail screen even without a configured maximum.
- If the operator determines the bin is physically full before all the quantity is placed, they use the partial putaway flow to stop at that bin and continue to another.

**When a bin has a configured capacity:**
- The system calculates remaining capacity = configured maximum − current quantity.
- Step 5 of the allocation logic applies: bins with insufficient remaining capacity are excluded from recommendations.
- A bin at exactly zero remaining capacity is shown as full and not recommended.

**The outstanding decision (BD-03) remains:** Paul/Craig must decide whether to configure bin capacities from the start. The system works without them but capacity tracking is less reliable until they are configured.

---

### Contradiction 3 — Shortage Approval: Percentage Threshold Disagreement

**Where it appears:**

Task 003A §3 (Exception Approval Matrix):
> "Shortage < 10% of line quantity — senior operator (if configured) or supervisor"

Task 003A BD-10:
> "Recommended option: Shortages within 5% of line quantity can be approved by a senior operator. Shortages above 5% require supervisor."

These two statements give different thresholds (10% vs 5%) for the same decision. They cannot both be correct.

**Resolution:**

The threshold is a business decision, not a technical one. Both values were proposed as recommendations. The correct approach is to not hardcode either value and to ask Paul and Craig to confirm it as part of the outstanding decisions.

**Corrected design:**

The shortage approval threshold is a configurable system setting (`shortage_approval_threshold_pct`) per fulfilment client, with a default that requires Paul and Craig's decision. Until the decision is made, all shortages of any size require supervisor approval (the more conservative default).

The approval matrix entry is corrected to:

| Exception | Who can approve | Notes |
|---|---|---|
| Shortage below configured threshold | Senior operator (if "senior operator" role is implemented) or supervisor | Threshold set in system config — default: 0% (all shortages to supervisor until configured) |
| Shortage at or above configured threshold | Supervisor only | |
| All shortages when threshold not configured | Supervisor only | Conservative default |

**Outstanding decision BD-10 (revised):** Paul/Craig must set the shortage approval threshold before the receiving module goes live. Recommended options: 5% or 10% of the line quantity. See consolidated decisions in Part 3.

---

## Part 3 — Consolidated Outstanding Business Decisions

All decisions from Tasks 003, 003A and the BD-02 revision are consolidated here. Decisions marked **MUST DECIDE BEFORE PHASE 3** must be answered before receiving module development begins. Others can be deferred to later phases.

---

### BD-01: Batch tracking — which products require it?

**Must decide before Phase 3.**

| Option | Consequence |
|---|---|
| Mandatory for all cosmetic and gel products (recommended) | Maximum traceability. Required for UK cosmetics regulation compliance. |
| Mandatory for cosmetics/gels; optional for accessories | Pragmatic. Accessories receive internal tracking reference only. |
| Optional for all | Not recommended. Traceability compromised. Not suitable for cosmetics distribution. |

**Decision required from:** Paul/Craig — which product categories are enrolled?

---

### BD-02A: Calendar month thresholds — confirmed?

**Must decide before Phase 3.**

The revised framework uses exact calendar-month calculations (same day-of-month arithmetic). The tiers are 12, 9, 6, 3 and 1 calendar months.

**Decision required from:** Paul/Craig — confirm these tier boundaries or propose different ones.

---

### BD-02B: Discount percentages — deferred

Not required for initial implementation. Management reviews notifications and takes commercial action outside the WMS. No decision needed at this stage.

---

### BD-02C: Which product categories are enrolled in shelf-life monitoring?

**Must decide before Phase 3.**

Gel polishes and cosmetics: almost certainly yes. Nail files, buffers, tools: possibly not. Admin must configure this per product category before the first receiving session.

**Decision required from:** Paul/Craig — list of exempt product categories.

---

### BD-02D: Shopify approach for short-dated stock

**Must decide before Phase 5 (Shopify sync).**

Not required for Phase 3. When Shopify inventory sync is designed, the approach for short-dated batches must be agreed. Options remain as documented in the BD-02 revision §4.2 (separate listing, single listing with WMS allocation, alternative channel, or draft orders).

**Decision required from:** Paul/Craig — before Phase 5 design begins.

---

### BD-02E: Management review notification window

**Deferred.**

How long does management have to review a shelf-life notification before a reminder is sent? With the simplified review queue, this is less critical — the queue is always visible. A reminder after 5 working days of no review is reasonable as a default. Paul can configure this.

---

### BD-02F: Cross-tier picking — full-price orders and short-dated stock

**Must decide before Phase 5 (order management and picking).**

When a customer orders at full price, can short-dated stock be substituted if no full-price stock is available? Under what circumstances? Who authorises this?

Until Phase 5 the FEFO rule picks the oldest expiry batch regardless of tier. From Phase 5, the picking logic must reflect the customer's shelf-life expectation.

**Decision required from:** Paul/Craig — before Phase 5 picking design.

---

### BD-03: Bin capacity tracking

**Must decide before first receiving session.**

Will MP Transport configure maximum capacity per bin from the start, or defer this?

- If configured from start: system enforces capacity during putaway recommendations. More setup work before go-live.
- If deferred: system tracks quantity per bin without a maximum. Operators must judge physically when a bin is full. Capacity limits can be added later per bin.

**Recommended default:** Defer. Configure capacity per bin after the first few weeks of operation, once actual usage patterns are known.

**Decision required from:** Paul/Craig.

---

### BD-04: Purchase order matching

**Deferred to Phase 4.**

Delivery note reference only for Phase 3. PO matching can be added later if an external accounting system needs to be reconciled.

---

### BD-05: Cold storage

**Must confirm before location setup.**

Do any NeoNail or Fuzion Gel products require temperature-controlled storage? If yes, a cold zone must be created in the location system before any affected products are received.

**Decision required from:** Paul/Craig.

---

### BD-06: Supervisor deputy

**Must decide before first receiving session.**

Who is the designated deputy supervisor for goods receipt exception approval when the primary supervisor is unavailable?

**Decision required from:** Paul/Craig — name at least one deputy.

---

### BD-07: Photo capture during receiving

**Must confirm before hardware selection.**

Does the scanning device have a camera? If yes, photo attachment for damage exceptions is available. If not, damage evidence is text-only.

**Decision required from:** Paul/Craig — confirm device specification.

---

### BD-08: Internal tracking reference format

**Must decide before first receiving session.**

When a product has no manufacturer batch number (e.g. accessories), the WMS assigns an internal tracking reference for warehouse operations. Proposed format: `MP-YYYYMMDD-NNN`. This is never presented as a manufacturer batch number.

**Decision required from:** Paul/Craig — confirm format or provide MP Transport's existing convention.

---

### BD-09: Nail Files SKU 4504 — pack count verification

**Must resolve before first real delivery.**

The pack count for Nail Files outer boxes is unverified. The system will record boxes received and not release saleable units until the count is physically confirmed by a supervisor.

**Action required from:** Paul — physically count one box and confirm the pack quantity so the product record can be updated with `pack_qty_confirmed = true`.

---

### BD-10: Shortage approval threshold

**Must decide before Phase 3 receiving module go-live.**

What percentage shortage on a delivery line can a senior operator approve independently? Above that threshold, a supervisor is required.

| Option | Operational consequence |
|---|---|
| 0% — all shortages to supervisor | Most conservative. Every discrepancy reviewed. Suitable for early operation. |
| 5% (recommended) | Minor shortages handled by operator. Significant shortages escalated. |
| 10% | More operator autonomy. Risk of larger discrepancies not being reviewed promptly. |

**Decision required from:** Paul/Craig — confirm threshold.

---

### BD-11: Role permissions for receiving

**Must decide before Phase 3 receiving module go-live.**

The receiving role permission matrix proposed in Task 003A BD-11 requires confirmation. The key questions are:

1. Does MP Transport need a "senior operator" role between operator and supervisor, or are the existing three roles (admin, supervisor, operator) sufficient?
2. Who may approve shortage exceptions at the configured threshold?

**Decision required from:** Paul/Craig — confirm the role structure.

---

### BD-12 (new): Expiry date precision for month/year-only labels

**Must decide before first cosmetic batch is received.**

Many cosmetic products print "EXP 06/2027" with no day. The WMS will store this as 30/06/2027 (last day of month) — the most conservative interpretation. Is this acceptable, or should it be the first day of the following month (01/07/2027), which would mean the product is treated as valid for one additional day?

| Option | Interpretation | Consequence |
|---|---|---|
| Last day of stated month (recommended) | EXP 06/2027 = 30/06/2027 | Most conservative. Stock classified as expired from 1 July. |
| First day of following month | EXP 06/2027 = 01/07/2027 | Gives one extra day. Some manufacturers use this convention. |

**Decision required from:** Paul/Craig.

---

## Summary: Documents Superseded or Amended by This Reconciliation

| Document | Status after Task 003B |
|---|---|
| WMS-TASK-003-RECEIVING-PUTAWAY-OPERATIONAL-BLUEPRINT.md | Superseded by 003A |
| WMS-TASK-003A-REVISED-OPERATIONAL-BLUEPRINT.md | Remains in force, with three internal contradictions resolved by Part 2 of this document |
| WMS-TASK-003A-BD02-EXPIRY-PRICING-REVISION.md | Superseded by Part 1 of this document |

**After Paul and Craig approve Task 003B, the authorised design is:**
- Task 003A for all operational workflows, stock states, exception handling, putaway and data structures.
- Task 003B Part 2 for the three contradiction resolutions (stock ledger, bin capacity, shortage threshold).
- Task 003B Part 1 (this document) for BD-02 (expiry and shelf-life monitoring).
- The consolidated decisions in Task 003B Part 3 for all outstanding business decisions.

---

*No application code, database structure or production settings were changed during this design work.*  
*All screen concepts are design proposals. None are implemented.*  
*Produced 3 November 2026 — MP Transport WMS Project*
