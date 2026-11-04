# WMS TASK 003C — Final Design Corrections and Stock Integrity Verification

**Project:** MP Transport Warehouse Management System  
**Reference:** WMS-TASK-003B-DESIGN-RECONCILIATION.md  
**Date:** 4 November 2026  
**Status:** Focused corrections only — awaiting approval before development  
**Code changes:** NOT AUTHORISED  
**Scope:** This document corrects five specific points in Task 003B. It does not rewrite Tasks 003A or 003B.

---

## Correction 1 — Calendar-Month Classification Boundaries

### What Was Wrong in Task 003B

The classification table in Task 003B §1.3 contained this row:

> 3 August 2027 → 9 months exactly → **FULL_PRICE**

This is wrong. Paul's commercial rule is that stock is **flagged for review when it reaches 12 months remaining**, meaning the threshold triggers *at* 12 months. Normal full-price stock therefore has *more than* 12 months remaining. By the same logic, the 9-month boundary triggers *at* 9 months. A batch with exactly 9 months remaining has crossed the 9-month threshold and belongs in SHORT_9, not FULL_PRICE.

The same error applied to all boundary examples that showed "exactly N months = FULL_PRICE". They should all be SHORT_(N).

### Corrected Classification Rule

**A batch is classified at the tier corresponding to the first threshold it has reached or passed.**

The boundaries are *inclusive at the lower end*: a batch with exactly 12 calendar months remaining has reached the 12-month threshold and enters SHORT_12. A batch with more than 12 calendar months remaining is FULL_PRICE.

```
Remaining shelf life (R)          Classification
────────────────────────────────  ──────────────
R > 12 calendar months            FULL_PRICE
9 calendar months ≤ R ≤ 12 months SHORT_12
6 calendar months ≤ R < 9 months  SHORT_9
3 calendar months ≤ R < 6 months  SHORT_6
1 calendar month  ≤ R < 3 months  SHORT_3
0 days < R < 1 calendar month     SHORT_1
R ≤ 0 (today ≥ expiry date*)      EXPIRED
```

*See Correction 2 for the precise definition of "expiry date" used in this comparison.*

### Corrected Example Table

Assessment date: **3 November 2026**

| Expiry date | Calculation | Remaining | Correct classification |
|---|---|---|---|
| 4 November 2027 | 12 months 1 day | > 12 months | **FULL_PRICE** |
| 3 November 2027 | exactly 12 months | = 12 months | **SHORT_12** ← notification triggered |
| 2 November 2027 | 11 months 29 days | < 12 months | **SHORT_12** |
| 3 August 2027 | exactly 9 months | = 9 months | **SHORT_9** ← *was incorrectly shown as FULL_PRICE in Task 003B* |
| 2 August 2027 | 8 months 30 days | < 9 months | **SHORT_9** |
| 3 May 2027 | exactly 6 months | = 6 months | **SHORT_6** |
| 3 February 2027 | exactly 3 months | = 3 months | **SHORT_3** |
| 3 December 2026 | exactly 1 month | = 1 month | **SHORT_1** |
| 3 November 2026 | 0 days | Expiry date = today | **EXPIRED** |
| 30 June 2027 | 7 months 27 days | < 9 months, ≥ 6 months | **SHORT_9** |
| 30 September 2027 | 10 months 27 days | < 12 months, ≥ 9 months | **SHORT_12** |

### Corrected Management Review Queue Example

The screen example in Task 003B §1.6 contained a Ballet Pink entry showing "407 days remaining — ENTERING SHORT_12." That entry has been removed in Task 003B but the corrected examples above now give a consistent set of reference cases covering every tier boundary.

### How the System Calculates Calendar Months

The system uses the following algorithm (implementable in any language using standard date arithmetic):

```
function remaining_months(today, expiry_date):
    years_diff  = expiry_date.year  - today.year
    months_diff = expiry_date.month - today.month
    days_diff   = expiry_date.day   - today.day

    total_months = (years_diff * 12) + months_diff

    if days_diff < 0:
        total_months = total_months - 1
        # Partial month does not count as a complete month

    return total_months
    # Returns whole months only. Fractional months are ignored for classification.
    # A result of 12 means exactly 12 complete months remain → SHORT_12.
    # A result of 13 means 13 complete months remain → FULL_PRICE.
```

**Examples using this algorithm (today = 3 Nov 2026):**

```
expiry = 3 Nov 2027:  years=1, months=0, days=0  → total=12       → SHORT_12
expiry = 4 Nov 2027:  years=1, months=0, days=+1 → total=12+0=12  
  Wait — 4 Nov 2027 to 3 Nov 2026:
  years=1, months=0, days=+1 (days_diff > 0, no deduction)
  total_months = 12 → SHORT_12
  
  CORRECTION: 4 Nov 2027 is 12 months and 1 day. The algorithm above
  returns 12 (whole months only). To classify 4 Nov 2027 as FULL_PRICE,
  the rule must be: remaining_months > 12.
  
  So: remaining_months = 12 → SHORT_12 (12 complete months remain, threshold reached)
      remaining_months = 13 → FULL_PRICE
      
expiry = 3 Aug 2027:  years=0, months=9, days=0  → total=9        → SHORT_9
expiry = 4 Aug 2027:  years=0, months=9, days=+1 → total=9        → SHORT_9
  (9 complete months remain regardless of the extra day)
expiry = 2 Aug 2027:  years=0, months=8, days=30 (days_diff<0 for Feb-style months; 
  actually days: 2-3 = -1) → total=9-1=8 → SHORT_9
  Wait: Aug(2027) month=8, Nov(2026) month=11. months_diff = 8-11 = -3.
  years_diff = 2027-2026 = 1. total = 12 + (-3) = 9. days: 2-3 = -1 < 0 → 9-1=8.
  So 2 Aug 2027 → 8 complete months → SHORT_9. ✓
```

**Definitive classification rule (consistent with Paul's commercial intent):**

| Whole calendar months remaining | Classification |
|---|---|
| 13 or more | FULL_PRICE |
| 12 | SHORT_12 |
| 9–11 | SHORT_12 |
| 6–8 | SHORT_9 |
| 3–5 | SHORT_6 |
| 1–2 | SHORT_3 |

Wait — that collapses 9–11 and 12 incorrectly. Let me state it unambiguously:

| Whole calendar months remaining (M) | Classification |
|---|---|
| M ≥ 13 | FULL_PRICE |
| 9 ≤ M ≤ 12 | SHORT_12 |
| 6 ≤ M ≤ 8 | SHORT_9 |
| 3 ≤ M ≤ 5 | SHORT_6 |
| 1 ≤ M ≤ 2 | SHORT_3 |
| M = 0 (days remain but < 1 month) | SHORT_1 |
| Today ≥ operational expiry date | EXPIRED |

This matches Paul's intent: a batch with exactly 12 whole months remaining is in SHORT_12 (the 12-month threshold has been reached). A batch with 13 or more whole months remaining is FULL_PRICE.

---

## Correction 2 — Expiry Date Boundaries: Full Dates and Month/Year Labels

### The Problem

Task 003B said stock is EXPIRED when `today > expiry_date`. It also said that month/year-only labels (EXP 06/2027) are stored as the last day of the stated month (30/06/2027). But it did not confirm whether "EXPIRED when today > 30/06/2027" means expired from 1 July (correct) or from 30 June itself (one day early, wrong). Paul has asked for consistency and accuracy on both points.

### Two Distinct Dates Must Be Stored

The WMS must store two separate pieces of information per batch:

| Field | What it holds | Example |
|---|---|---|
| `manufacturer_expiry_raw` | Exactly what the manufacturer printed, as a string | `"06/2027"` or `"30/06/2027"` or `"2027"` |
| `manufacturer_expiry_precision` | The precision of the printed date | `"month_year"` or `"full_date"` or `"year_only"` |
| `operational_expiry_date` | The calculated date used by the system for all expiry checks | `2027-06-30` |

The `operational_expiry_date` is the date stored in the database for all classification and dispatch checks. The `manufacturer_expiry_raw` is preserved for display, audit and regulatory traceability.

### How Operational Expiry Date Is Derived

| Manufacturer precision | Printed example | Operational expiry date | Rule |
|---|---|---|---|
| Full date | 30/06/2027 | 2027-06-30 | Stored exactly as printed |
| Month/year only | EXP 06/2027 | 2027-06-30 | Last day of stated month |
| Year only | 2027 | 2027-12-31 | Last day of stated year |

### The Expiry Comparison Rule

Stock is EXPIRED when:

```
today > operational_expiry_date
```

Using strict greater-than (`>`), not greater-than-or-equal (`>=`).

**This means:**
- Stock with `operational_expiry_date = 2027-06-30` is available on 30 June 2027 (`today = 2027-06-30`, which is not `> 2027-06-30`).
- It is EXPIRED from 1 July 2027 (`today = 2027-07-01 > 2027-06-30` = true).

**This is consistent for all precision types:**

| Scenario | Operational expiry | Last day available | First day expired |
|---|---|---|---|
| Full date: 30/06/2027 | 2027-06-30 | 30 June 2027 | 1 July 2027 |
| Month/year: EXP 06/2027 → last day of month | 2027-06-30 | 30 June 2027 | 1 July 2027 |
| Year only: 2027 → last day of year | 2027-12-31 | 31 December 2027 | 1 January 2028 |

Storing month/year-only as the last day of the stated month with strict `>` comparison gives the same result as if the manufacturer had printed "30/06/2027". This is both correct and consistent: the product is usable through the last day of the stated month, and expired from the following day.

**BD-12 (from Task 003B) is now answered.** The rule is last day of stated month with `today > operational_expiry_date`. No business decision required; this is the operationally correct convention and is consistent with industry practice for cosmetics products.

### Display to Operators

The WMS always displays the manufacturer's original date string alongside the precision note:

- Full date: `Expiry: 30 Jun 2027`
- Month/year: `Expiry: Jun 2027 (last day assumed: 30 Jun 2027)`
- Year only: `Expiry: 2027 (last day assumed: 31 Dec 2027)`

The precision note is shown in parentheses so an operator can see when an exact date was not available. This preserves transparency without complicating the operator's workflow.

---

## Correction 3 — Database Constraint and Zone Eligibility Enforcement

### What Was Wrong in Task 003B

The proposed CHECK constraint in Task 003B Contradiction 1 was:

```sql
CONSTRAINT chk_available_requires_location
  CHECK (
    status != 'AVAILABLE'
    OR (location_id IS NOT NULL)
  )
```

Paul correctly identified that this only checks `location_id IS NOT NULL`. It does not verify that the referenced location is in an eligible storage zone. A `stock_entry` could have `status = AVAILABLE` and a `location_id` pointing to a quarantine bin or dispatch staging area and the constraint would not catch it.

### The Limitation of CHECK Constraints for Cross-Table Rules

A `CHECK` constraint on `stock_entries` cannot reference the `locations` table in standard SQL — `CHECK` constraints are evaluated per-row without joining other tables. The zone eligibility rule (stock is only AVAILABLE if the bin is in an eligible zone) is a cross-table business rule and must be enforced differently.

### Three-Layer Enforcement

**Layer 1: Application-layer validation (primary enforcement)**

Before any `stock_entry` is set to `AVAILABLE`, the application code checks:

```
location = Location::find(stock_entry.location_id)
if location.zone NOT IN ('A', 'C'):
    raise InvalidZoneException("Stock in zone #{location.zone} cannot be AVAILABLE")
```

This runs inside the same database transaction as the status update. If the zone check fails, the transaction rolls back. No stock is silently made available in a non-eligible zone.

Eligible zones for AVAILABLE status (configurable in system settings):
- `A` — Ambient storage
- `C` — Cold/chilled storage (if applicable)

Non-eligible zones (stock in these zones can never be AVAILABLE):
- `I` — Inbound (temporary holding)
- `Q` — Quarantine
- `D` — Dispatch staging
- `R` — Returns inspection

**Layer 2: Database CHECK constraint (belt and braces)**

The CHECK constraint catches any direct database write that bypasses the application layer (e.g. a migration script or manual SQL query):

```sql
-- This constraint uses a subquery to check zone eligibility.
-- Supported in PostgreSQL and MySQL 8.0.16+ with CHECK constraints
-- that reference other tables via subqueries.
-- For SQLite (current production), enforce at application layer only
-- and add this constraint when migrating to MySQL/PostgreSQL.

CONSTRAINT chk_available_requires_eligible_location
  CHECK (
    status != 'AVAILABLE'
    OR (
      location_id IS NOT NULL
      AND location_id IN (
        SELECT id FROM locations WHERE zone IN ('A', 'C') AND is_active = true
      )
    )
  )
```

**SQLite note:** SQLite does not support subqueries in `CHECK` constraints. For the current SQLite production database, Layer 1 (application enforcement) is the primary control. The subquery constraint is added when the database is migrated to MySQL/MariaDB.

The simpler `location_id IS NOT NULL` constraint from Task 003B is retained as an immediate constraint even in SQLite:

```sql
CONSTRAINT chk_available_requires_location
  CHECK (status != 'AVAILABLE' OR location_id IS NOT NULL)
```

This catches the most dangerous error (AVAILABLE with no location) in SQLite, while the full eligibility check is enforced at the application layer.

**Layer 3: Transition audit**

Every status change on a `stock_entry` creates a `stock_movements` record. If a stock entry somehow becomes AVAILABLE in a non-eligible zone, the movement record identifies when it happened, who performed the action, and from which code path. This enables post-hoc detection and correction even in edge cases the constraints did not catch.

### When a Location's Eligibility Changes

If a bin changes zone (e.g. bin A-02-C-04 is reclassified from ambient to quarantine), existing stock in that bin has a problem: it may be AVAILABLE but now in a non-eligible zone.

**The system must handle this in two steps:**

1. **Before reclassifying a bin's zone**, the system checks whether any AVAILABLE or RESERVED `stock_entries` reference that bin. If yes, the reclassification is blocked:
   ```
   ZONE CHANGE BLOCKED
   Bin A-02-C-04 contains 20 units of Gel Polish Red (AVAILABLE).
   You cannot change this bin to Quarantine zone while it holds active stock.
   Move or quarantine the stock first, then reclassify the bin.
   ```

2. **If a zone change must proceed urgently** (e.g. contamination discovered), a supervisor can force the zone change. The system automatically sets all AVAILABLE and RESERVED stock in that bin to `IN_LOCATION` (temporarily ineligible) and raises a supervisor task: "Stock in bin A-02-C-04 must be physically relocated or quarantined."

This ensures the physical and electronic states remain consistent: stock is never silently AVAILABLE in a quarantine zone.

---

## Correction 4 — Notification Audit Behaviour

### What Was Ambiguous in Task 003B

Task 003B §1.6 described `shelf_life_notifications` as "append-only" and "records are never deleted." This correctly captures the creation side. However, the same records include `reviewed`, `reviewed_by`, `reviewed_at` and `review_notes` — fields that management can update after creation. Task 003B did not explicitly clarify whether updating these fields is permissible or whether that constitutes a mutation that conflicts with the audit intent.

Task 003B §8.3 of the BD-02 revision described `batch_pricing_events` as "immutable: records are never updated or deleted." This is now replaced by the simpler `shelf_life_notifications` model, but the same question arises: can a notification be edited after it is marked reviewed?

### The Correct Model: Immutable Core, Mutable Review Fields

A `shelf_life_notifications` record has two distinct parts:

**The immutable core (set at creation, never changed):**
- `id`
- `batch_id`, `product_id`, `fulfilment_client_id`
- `transition_date`
- `previous_classification`, `new_classification`
- `months_remaining` at the time of transition
- `units_in_stock` at the time of transition
- `bin_locations` at the time of transition (JSON snapshot)
- `expiry_date` (from the batch record at that moment)
- `created_at`

**The mutable review fields (updated when management reviews):**
- `reviewed` (boolean — false until marked)
- `reviewed_by` (user_id)
- `reviewed_at` (datetime)
- `review_notes` (text)

The review fields are updated in place. This is intentional and correct: it is operationally equivalent to signing off a paper notification. A notification exists once; it is reviewed once. The historical record of what the notification said is preserved in the immutable core. The review action is recorded in the mutable fields.

**What cannot be changed:** The core facts of what classification change occurred, when it occurred, how many units were affected, and where they were located. These are immutable because they are facts about the physical world at a point in time.

**What can be changed:** Whether management has acknowledged the notification and what notes they added. This is a workflow state, not a historical fact.

**Can review notes be edited after the initial review?** Yes, with an important caveat: updating `review_notes` after initial review creates a secondary audit entry:

```
shelf_life_notification_edits (new table):
  id
  notification_id
  previous_notes
  new_notes
  edited_by
  edited_at
  edit_reason
```

This table records every change to the review notes after the first entry, preserving the full edit history without cluttering the main notification table. The main table's `review_notes` always shows the current text; the edits table shows what it previously said and who changed it.

**The practical result:** An auditor can always reconstruct the full history — what the notification said when it was created, what notes were entered when it was first reviewed, and all subsequent changes to those notes.

---

## Correction 5 — Stock Ledger Integrity: Worked Transaction Examples

### Setup

All examples use these identifiers. Today is 3 November 2026.

```
Delivery DN-2026-0042, NeoNail UK
Product: Gel Polish Classic Red  SKU: GP-RED-001
Batch:   A2026-047               Expiry: 31 Dec 2027 (FULL_PRICE — 13 months remaining)
Received: 48 bottles             Approved for putaway.

Bins:
  Bin-01 = A-02-C-04  (zone A, eligible, capacity unconfigured)
  Bin-02 = A-02-C-05  (zone A, eligible, capacity unconfigured)
  Bin-03 = A-02-C-06  (zone A, eligible, capacity unconfigured)
  Bin-04 = A-02-C-07  (zone A, eligible, capacity unconfigured)

Stock entries: SE = stock_entry. SM = stock_movement (immutable).
```

---

### Operation 1 — Receive 48 Bottles

**What happens:**
Operator completes receiving. Supervisor approval not required (no exceptions). System creates one holding stock entry for the full quantity, with no bin location yet.

```
Stock entries created:
  SE-001: batch=A2026-047, location=null, qty=48, status=APPROVED_PUTAWAY

Stock movement created:
  SM-001: type=RECEIPT, to_entry=SE-001, qty=+48, performed_by=operator,
          reference=DN-2026-0042, performed_at=03/11/2026 10:34

State after Operation 1:
┌────────────────────────────────────────────────────────────┐
│ Entry  Batch      Location  Qty  Status           │
│ SE-001 A2026-047  –         48   APPROVED_PUTAWAY  │
├────────────────────────────────────────────────────────────┤
│ AVAILABLE:  0    APPROVED_PUTAWAY: 48    Total: 48  │
└────────────────────────────────────────────────────────────┘
Balance check: SM-001(+48) = SE-001(48) ✓
```

---

### Operation 2 — Putaway 20 Bottles into Bin-01

**What happens:**
Operator scans Bin-01 and confirms placing 20 bottles. System creates a new located stock entry (SE-002) and reduces the holding entry (SE-001) by 20. SE-002 transitions to AVAILABLE immediately because Bin-01 is in an eligible zone.

```
SM-002: type=PUTAWAY, from_entry=SE-001, qty=−20, to_entry=SE-002(new),
        from_location=null, to_location=Bin-01, performed_by=operator,
        performed_at=03/11/2026 10:41

Stock entries after:
  SE-001: batch=A2026-047, location=null,   qty=28, status=APPROVED_PUTAWAY
  SE-002: batch=A2026-047, location=Bin-01, qty=20, status=AVAILABLE

State after Operation 2:
┌────────────────────────────────────────────────────────────────────┐
│ Entry  Batch      Location  Qty  Status           │
│ SE-001 A2026-047  –         28   APPROVED_PUTAWAY  │
│ SE-002 A2026-047  Bin-01    20   AVAILABLE         │
├────────────────────────────────────────────────────────────────────┤
│ AVAILABLE: 20    APPROVED_PUTAWAY: 28    Total: 48  │
└────────────────────────────────────────────────────────────────────┘
Balance: SM-001(+48) + SM-002(−20 from SE-001, +20 to SE-002) = 48 total ✓
No quantity created or destroyed. Transfer only.
```

---

### Operation 3 — Putaway 20 Bottles into Bin-02, then 8 into Bin-03

**Two movements, same pattern:**

```
SM-003: type=PUTAWAY, from_entry=SE-001, qty=−20, to_entry=SE-003(new, Bin-02)
SM-004: type=PUTAWAY, from_entry=SE-001, qty=−8,  to_entry=SE-004(new, Bin-03)

Stock entries after:
  SE-001: location=null,   qty=0,  status=PUTAWAY_COMPLETE (closed)
  SE-002: location=Bin-01, qty=20, status=AVAILABLE
  SE-003: location=Bin-02, qty=20, status=AVAILABLE
  SE-004: location=Bin-03, qty=8,  status=AVAILABLE

State after Operations 2–3 (all putaway complete):
┌───────────────────────────────────────────────────────────────────────┐
│ Entry  Batch      Location  Qty  Status           │
│ SE-001 A2026-047  –          0   PUTAWAY_COMPLETE  │
│ SE-002 A2026-047  Bin-01    20   AVAILABLE         │
│ SE-003 A2026-047  Bin-02    20   AVAILABLE         │
│ SE-004 A2026-047  Bin-03     8   AVAILABLE         │
├───────────────────────────────────────────────────────────────────────┤
│ AVAILABLE: 48    Total on-hand: 48                  │
└───────────────────────────────────────────────────────────────────────┘
Balance: +48 (SM-001) −20 (SM-002) −20 (SM-003) −8 (SM-004) = 0 net movement of SE-001.
Quantities redistributed to SE-002(20) + SE-003(20) + SE-004(8) = 48 ✓
```

---

### Operation 4 — Reserve 12 Bottles for Order ORD-001

**What happens:**
System applies FEFO — only one batch exists, so picks from Bin-01 (SE-002, 20 units). Reserves 12 units. SE-002 is split: 12 go to a new RESERVED entry (SE-005), 8 remain AVAILABLE in SE-002.

```
SM-005: type=RESERVATION, from_entry=SE-002, qty=−12, to_entry=SE-005(new, Bin-01),
        reference=ORD-001, performed_at=03/11/2026 11:00

Stock entries after:
  SE-002: location=Bin-01, qty=8,  status=AVAILABLE
  SE-003: location=Bin-02, qty=20, status=AVAILABLE
  SE-004: location=Bin-03, qty=8,  status=AVAILABLE
  SE-005: location=Bin-01, qty=12, status=RESERVED  ← linked to ORD-001

State after Operation 4:
┌────────────────────────────────────────────────────────────────────────┐
│ Entry  Batch      Location  Qty  Status    Order    │
│ SE-002 A2026-047  Bin-01     8   AVAILABLE –        │
│ SE-003 A2026-047  Bin-02    20   AVAILABLE –        │
│ SE-004 A2026-047  Bin-03     8   AVAILABLE –        │
│ SE-005 A2026-047  Bin-01    12   RESERVED  ORD-001  │
├────────────────────────────────────────────────────────────────────────┤
│ AVAILABLE: 36    RESERVED: 12    Total on-hand: 48  │
└────────────────────────────────────────────────────────────────────────┘
Balance: SE-002(8) + SE-003(20) + SE-004(8) + SE-005(12) = 48 ✓
SM-005 moves 12 units from SE-002 to SE-005. No new quantity created.
Shopify-visible inventory: 36 (AVAILABLE only — RESERVED not shown as available to new orders)
```

---

### Operation 5 — Pick and Dispatch the 12 Reserved Bottles

**What happens:**
Operator picks SE-005 (12 units from Bin-01), packs, and confirms dispatch. SE-005 transitions to DISPATCHED. The quantity leaves on-hand stock.

```
SM-006: type=PICK,     entry=SE-005, qty=−12, performed_at=03/11/2026 14:30
        (Operator physically removes 12 bottles from Bin-01)

SM-007: type=DISPATCH, entry=SE-005, qty=0 (confirmatory record — no qty change),
        reference=ORD-001, tracking_number=TRACK-001, performed_at=03/11/2026 15:00

SE-005: qty=12, status=DISPATCHED  (qty field preserved for audit; status shows it left)

State after Operation 5:
┌────────────────────────────────────────────────────────────────────────┐
│ Entry  Batch      Location  Qty  Status      │
│ SE-002 A2026-047  Bin-01     8   AVAILABLE   │
│ SE-003 A2026-047  Bin-02    20   AVAILABLE   │
│ SE-004 A2026-047  Bin-03     8   AVAILABLE   │
│ SE-005 A2026-047  Bin-01    12   DISPATCHED  │
├────────────────────────────────────────────────────────────────────────┤
│ AVAILABLE: 36    RESERVED: 0    DISPATCHED: 12   │
│ Physical on-hand (AVAILABLE + RESERVED): 36      │
│ Total accounted for: 36 + 12 = 48 ✓             │
└────────────────────────────────────────────────────────────────────────┘
Balance: All movements sum to zero net change in total quantity (48 received, 12 dispatched,
36 remaining on-hand). ✓
Shopify-visible inventory now: 36
```

**Why SE-005 is preserved at qty=12 with status=DISPATCHED rather than deleted:**
Every unit received must be traceable to its final destination. SE-005 provides the link:
Delivery DN-2026-0042 → Batch A2026-047 → SE-005 → Order ORD-001 → Tracking TRACK-001.
If a recall is needed, the system can find every customer who received units from Batch A2026-047.

---

### Operation 6 — Transfer 5 Bottles from Bin-02 to Bin-04

**What happens:**
Operator physically moves 5 bottles from Bin-02 (SE-003) to Bin-04 (new bin, eligible zone). Creates a new entry SE-006 for the 5 bottles in Bin-04 and reduces SE-003 by 5.

```
SM-008: type=TRANSFER, from_entry=SE-003, qty=−5, to_entry=SE-006(new, Bin-04),
        from_location=Bin-02, to_location=Bin-04,
        performed_by=operator, performed_at=03/11/2026 15:30

Stock entries after:
  SE-002: location=Bin-01, qty=8,  status=AVAILABLE
  SE-003: location=Bin-02, qty=15, status=AVAILABLE  ← reduced by 5
  SE-004: location=Bin-03, qty=8,  status=AVAILABLE
  SE-006: location=Bin-04, qty=5,  status=AVAILABLE  ← new

State after Operation 6:
┌──────────────────────────────────────────────────────────────┐
│ Entry  Batch      Location  Qty  Status    │
│ SE-002 A2026-047  Bin-01     8   AVAILABLE │
│ SE-003 A2026-047  Bin-02    15   AVAILABLE │
│ SE-004 A2026-047  Bin-03     8   AVAILABLE │
│ SE-005 A2026-047  Bin-01    12   DISPATCHED│
│ SE-006 A2026-047  Bin-04     5   AVAILABLE │
├──────────────────────────────────────────────────────────────┤
│ AVAILABLE: 8+15+8+5 = 36    DISPATCHED: 12    Total: 48 ✓  │
└──────────────────────────────────────────────────────────────┘
Transfer is a zero-sum operation: 5 removed from SE-003, 5 added to SE-006.
No quantity created or destroyed. ✓
```

---

### Operation 7 — Quarantine 3 Bottles in Bin-03

**What happens:**
Supervisor discovers 3 bottles in Bin-03 are damaged (arrived undetected during receiving). Supervisor raises a quarantine order. 3 bottles are physically moved to the Q zone. SE-004 is split: 3 go to quarantine entry SE-007, 5 remain AVAILABLE.

```
SM-009: type=QUARANTINE_IN, from_entry=SE-004, qty=−3, to_entry=SE-007(new, Q-01),
        reason=DAMAGED, raised_by=supervisor, approved_by=supervisor,
        performed_at=04/11/2026 09:00

quarantine_records entry created:
  QR-001: stock_entry=SE-007, reason=DAMAGED, qty=3, status=OPEN

Stock entries after:
  SE-004: location=Bin-03, qty=5,  status=AVAILABLE
  SE-007: location=Q-01,   qty=3,  status=QUARANTINE

State after Operation 7:
┌──────────────────────────────────────────────────────────────────┐
│ Entry  Batch      Location  Qty  Status      │
│ SE-002 A2026-047  Bin-01     8   AVAILABLE   │
│ SE-003 A2026-047  Bin-02    15   AVAILABLE   │
│ SE-004 A2026-047  Bin-03     5   AVAILABLE   │
│ SE-005 A2026-047  Bin-01    12   DISPATCHED  │
│ SE-006 A2026-047  Bin-04     5   AVAILABLE   │
│ SE-007 A2026-047  Q-01       3   QUARANTINE  │
├──────────────────────────────────────────────────────────────────┤
│ AVAILABLE:  8+15+5+5 = 33                          │
│ QUARANTINE: 3                                       │
│ DISPATCHED: 12                                      │
│ Physical on-hand (AVAILABLE + QUARANTINE): 36       │
│ Total accounted for: 36 + 12 dispatched = 48 ✓     │
└──────────────────────────────────────────────────────────────────┘
Shopify-visible inventory: 33 (AVAILABLE only — QUARANTINE excluded) ✓
```

---

### Operation 8 — Correct an Erroneous Quantity

**Scenario:** A stock count reveals SE-003 (Bin-02) actually contains 13 bottles, not 15. The system shows 15; physical reality is 13. A write-down of 2 units is required.

**What happens:**
Supervisor authorises a stock adjustment. A negative movement is recorded against SE-003. The original records are untouched — the correction is a new movement that explains the discrepancy.

```
SM-010: type=WRITE_OFF, entry=SE-003, qty=−2,
        reason="Stock count discrepancy — physical count: 13, system: 15",
        performed_by=supervisor, approved_by=supervisor,
        references_original_movement=SM-003 (the putaway that created SE-003),
        performed_at=04/11/2026 10:00

SE-003: location=Bin-02, qty=13, status=AVAILABLE  (updated)

State after Operation 8:
┌──────────────────────────────────────────────────────────────────┐
│ Entry  Batch      Location  Qty  Status      │
│ SE-002 A2026-047  Bin-01     8   AVAILABLE   │
│ SE-003 A2026-047  Bin-02    13   AVAILABLE   ← corrected from 15│
│ SE-004 A2026-047  Bin-03     5   AVAILABLE   │
│ SE-005 A2026-047  Bin-01    12   DISPATCHED  │
│ SE-006 A2026-047  Bin-04     5   AVAILABLE   │
│ SE-007 A2026-047  Q-01       3   QUARANTINE  │
├──────────────────────────────────────────────────────────────────┤
│ AVAILABLE:  8+13+5+5 = 31                                │
│ QUARANTINE: 3                                             │
│ DISPATCHED: 12                                            │
│ Physical on-hand: 34                                      │
│ Total accounted for: 34 + 12 dispatched = 46             │
│ Original received: 48                                     │
│ Discrepancy: −2 (accounted for by SM-010 WRITE_OFF)       │
└──────────────────────────────────────────────────────────────────┘

Audit trail for the discrepancy:
  SM-001 (+48 received) + SM-010 (−2 write-off) = net +46 accounted for.
  The 2-unit variance is fully explained by the write-off record.
  Who approved it: supervisor. When: 04/11/2026 10:00. Why: physical count. ✓
```

**The original movement records are not changed.** SM-003 still says "+20 to SE-003 on putaway." SM-010 says "−2 write-off from SE-003." The history is complete and shows both the original error and the correction.

---

### Operation 9 — Two Operators Attempting the Same Stock Movement Simultaneously

**Scenario:** Two operators (Operator A and Operator B) both attempt to reserve stock from SE-002 (8 units, Bin-01) for different orders at the same moment.

- Operator A: reserving 6 units for Order ORD-002.
- Operator B: reserving 5 units for Order ORD-003.
- Total requested: 11 units. Available: 8. Only one can fully succeed.

**Mechanism: Optimistic Locking via Version Counter**

Every `stock_entry` has a `version` integer field, incremented on every write.

```
Initial state:
  SE-002: qty=8, status=AVAILABLE, version=3
  (Version reflects all previous operations on this entry)

Operator A reads SE-002: qty=8, version=3
Operator B reads SE-002: qty=8, version=3
  (Both read the same state simultaneously)

Operator A executes:
  BEGIN TRANSACTION
  UPDATE stock_entries
     SET qty = 8-6 = 2, version = 4
   WHERE id = SE-002 AND version = 3
  → Rows affected: 1 (success — version matched)
  INSERT stock_entries (SE-008, qty=6, status=RESERVED, Bin-01, ORD-002)
  INSERT stock_movements (SM-011, RESERVATION, SE-002→SE-008, qty=−6)
  COMMIT
  → Operator A's reservation succeeds.

Operator B executes (milliseconds later):
  BEGIN TRANSACTION
  UPDATE stock_entries
     SET qty = 8-5 = 3, version = 4
   WHERE id = SE-002 AND version = 3
  → Rows affected: 0 (FAILURE — version already updated to 4 by Operator A)
  ROLLBACK
  → No stock movement created. No reservation written.

WMS response to Operator B:
  "Reservation conflict: stock has changed since you began.
   SE-002 now has 2 units available (8 − 6 reserved by another order).
   Your order requires 5 units. Please review."

Operator B's options:
  1. Wait and retry if more stock becomes available.
  2. Reserve the available 2 units now and backorder the remaining 3.
  3. Cancel the reservation and notify the sales team.

State after both operations:
┌──────────────────────────────────────────────────────────────────────┐
│ Entry  Batch      Location  Qty  Status    Order    │
│ SE-002 A2026-047  Bin-01     2   AVAILABLE –        │  ← 8−6=2
│ SE-003 A2026-047  Bin-02    13   AVAILABLE –        │
│ SE-004 A2026-047  Bin-03     5   AVAILABLE –        │
│ SE-006 A2026-047  Bin-04     5   AVAILABLE –        │
│ SE-007 A2026-047  Q-01       3   QUARANTINE–        │
│ SE-008 A2026-047  Bin-01     6   RESERVED  ORD-002  │  ← Operator A's reservation
├──────────────────────────────────────────────────────────────────────┤
│ AVAILABLE:  2+13+5+5 = 25    RESERVED: 6    QUARANTINE: 3   │
│ DISPATCHED: 12                                               │
│ Total: 25+6+3+12 = 46 (= 48 received − 2 write-off) ✓       │
└──────────────────────────────────────────────────────────────────────┘
No duplicate reservation. No inventory inflated. Operator B's transaction
rolled back cleanly. ✓
```

---

### Full Balance Reconciliation After All Operations

The following table traces every unit from receipt to final state:

```
48 bottles received (SM-001)

Accounted for as follows after all 9 operations:
─────────────────────────────────────────────────
 SE-002  Bin-01    2  AVAILABLE      (8 original − 6 reserved to ORD-002)
 SE-003  Bin-02   13  AVAILABLE      (20 original − 5 transferred − 2 written off)
 SE-004  Bin-03    5  AVAILABLE      (8 original − 3 quarantined)
 SE-005  Bin-01   12  DISPATCHED     (reserved for ORD-001, picked and dispatched)
 SE-006  Bin-04    5  AVAILABLE      (5 transferred from Bin-02)
 SE-007  Q-01      3  QUARANTINE     (3 damaged bottles)
 SE-008  Bin-01    6  RESERVED       (reserved for ORD-002)
─────────────────────────────────────────────────
 Subtotal on-hand (AVAILABLE):       2+13+5+5 = 25
 Subtotal RESERVED:                  6
 Subtotal QUARANTINE:                3
 Subtotal DISPATCHED:                12
 Total:                              25+6+3+12 = 46
─────────────────────────────────────────────────
 Original received:                  48
 Write-off (Operation 8):            −2
 Expected total:                     46 ✓
─────────────────────────────────────────────────
Every unit is accounted for. No unit appears in two places.
No unit was created or destroyed by any transfer, reservation or correction.
```

---

## Remaining Material Risks

Three risks remain in the design that have not been fully resolved by Task 003C:

**Risk 1 — SQLite zone-eligibility constraint**
The cross-table CHECK constraint for zone eligibility cannot be enforced in SQLite. Application-layer enforcement is the only control in the current production database. Any direct database access (Tinker, SQL scripts, future migrations) bypasses this control. The risk is low while the team is small, but increases with growth. Mitigation: enforce a policy that no direct SQL writes to `stock_entries` are permitted without code review. Add the full CHECK constraint when migrating to MySQL/MariaDB.

**Risk 2 — Expiry check frequency for SHORT_1 batches**
The daily classification run (default 06:00) means a batch could expire during the working day without the system detecting it until the next morning. For stock in SHORT_1 (less than one month remaining), this is a real operational risk — a batch could expire at midnight and still be on a pick list generated at 08:00.

Mitigation options:
- Run the classification job more frequently (e.g. hourly) for SHORT_1 batches.
- At pick list generation, always re-check expiry live (not from the cached classification field) for any batch classified SHORT_1 or SHORT_3.
- At dispatch confirmation, re-check expiry live (already designed — see Task 003B §1.8 Point 3).

**Decision required (BD-13):** Paul/Craig — should the classification job run hourly for near-expiry batches, or is the daily run with a live check at dispatch acceptable?

**Risk 3 — Concurrent partial putaway of the same delivery line**
Operations 2–3 above show sequential putaway. If two operators attempt to put away from the same holding entry (SE-001) simultaneously, the same optimistic locking mechanism applies. One succeeds; the other retries with the updated remaining quantity. This is correct. However, the WMS UI should show which operator is currently performing putaway on a delivery line, to reduce unnecessary contention. This is a UX concern, not a data integrity concern.

---

## Summary of Changes to Task 003B

| Task 003B section | Correction in Task 003C |
|---|---|
| §1.3 Classification table (3 Aug 2027 as FULL_PRICE) | Corrected — 9 months = SHORT_9. Full boundary table restated. |
| §1.3 Boundary rule ("≤" vs "< 12 months") | Corrected — boundary is inclusive. 12 whole months remaining = SHORT_12. |
| §1.4 Manufacturer expiry precision | Full-date vs month/year rule stated precisely. BD-12 answered — no business decision needed. |
| §1.6 Contradiction 1 CHECK constraint | Zone eligibility: three-layer enforcement defined. SQLite limitation noted. Zone-change handling added. |
| §1.6 `shelf_life_notifications` as "immutable" | Corrected — core fields immutable; review fields mutable; edit history table added. |
| (new) Stock ledger integrity | Worked examples for all 9 operations. Full balance reconciliation. |
| (new) BD-13 | Near-expiry classification frequency — outstanding decision added. |

---

*No application code, database structure or production settings were changed during this document.*  
*All screen concepts and data structures are design proposals only.*  
*Produced 4 November 2026 — MP Transport WMS Project*
