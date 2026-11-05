# WMS TASK 003C — Technical Addendum

**Reference:** WMS-TASK-003C-FINAL-DESIGN-CORRECTIONS.md  
**Date:** 5 November 2026  
**Type:** Concise technical corrections — no blueprint rewrite  
**Code changes:** NOT AUTHORISED  
**Status:** Awaiting approval before development

This addendum corrects three technical errors in Task 003C, records the confirmed BD-13 decision, and restates outstanding business decisions.

---

## Correction 1 — Calendar-Month Expiry Classification

### What Was Wrong

Task 003C replaced fixed-day approximations with a whole-month truncation algorithm. That algorithm is still wrong. It truncates remaining life to whole months before classifying, which makes expiry dates either side of a threshold boundary indistinguishable:

```
remaining_months(3-Nov-2026, 3-Nov-2027) = 12  → SHORT_12
remaining_months(3-Nov-2026, 4-Nov-2027) = 12  → SHORT_12   ← WRONG: should be FULL_PRICE
```

Both return 12 whole months, so both classify identically. The error affects every threshold boundary.

### Correct Algorithm: Direct Date Comparison Against Threshold Dates

The correct approach computes the exact threshold boundary date for each tier and compares the expiry date directly against those boundaries — no truncation.

**Definition of threshold boundary dates** (computed fresh each time classification runs, using the assessment date T):

```
boundary[12] = calendar_add(T, 12 months)
boundary[9]  = calendar_add(T,  9 months)
boundary[6]  = calendar_add(T,  6 months)
boundary[3]  = calendar_add(T,  3 months)
boundary[1]  = calendar_add(T,  1 month)
```

`calendar_add(date, N months)` = same day-of-month, N months later. If the target month has fewer days (e.g. 31 Jan + 1 month = 28/29 Feb), use the last day of the target month. This is standard behaviour in PHP (`date_add`), Python (`dateutil.relativedelta`), SQL (`DATE_ADD` / `INTERVAL`), and Java (`LocalDate.plusMonths`).

**Classification rule:**

```
function classify(T, expiry):
    if expiry > boundary[12]:  return FULL_PRICE
    if expiry > boundary[9]:   return SHORT_12
    if expiry > boundary[6]:   return SHORT_9
    if expiry > boundary[3]:   return SHORT_6
    if expiry > boundary[1]:   return SHORT_3
    if expiry > T:             return SHORT_1
    else:                      return EXPIRED
```

All comparisons are strict `>`. A date that equals a boundary threshold falls into the lower tier (the threshold has been reached). `expiry > T` treats stock as SHORT_1 on its expiry date itself; `expiry = T` or `expiry < T` is EXPIRED.

**Edge-case — month-end boundary dates:**

`calendar_add(31-Jan-2027, 1 month)` = 28-Feb-2027 (last day of February, non-leap year).

An expiry of 1-Mar-2027 is strictly greater than 28-Feb-2027 → SHORT_12 or higher depending on other boundaries.
An expiry of 28-Feb-2027 is not strictly greater than 28-Feb-2027 → SHORT_1 or lower.

This is internally consistent and requires no special-casing. The standard library handles it.

### Acceptance Tests — Assessment Date 3 November 2026

Threshold boundary dates:
```
boundary[12] = 3 Nov 2027
boundary[9]  = 3 Aug 2027
boundary[6]  = 3 May 2027
boundary[3]  = 3 Feb 2027
boundary[1]  = 3 Dec 2026
```

**Tests immediately before, exactly on, and immediately after each boundary:**

| Expiry date | Relationship to boundary | Expected classification |
|---|---|---|
| **FULL_PRICE / SHORT_12 boundary** | | |
| 4 Nov 2027 | 1 day after boundary[12] | **FULL_PRICE** |
| 3 Nov 2027 | exactly boundary[12] | **SHORT_12** |
| 2 Nov 2027 | 1 day before boundary[12] | **SHORT_12** |
| **SHORT_12 / SHORT_9 boundary** | | |
| 4 Aug 2027 | 1 day after boundary[9] | **SHORT_12** |
| 3 Aug 2027 | exactly boundary[9] | **SHORT_9** |
| 2 Aug 2027 | 1 day before boundary[9] | **SHORT_9** |
| **SHORT_9 / SHORT_6 boundary** | | |
| 4 May 2027 | 1 day after boundary[6] | **SHORT_9** |
| 3 May 2027 | exactly boundary[6] | **SHORT_6** |
| 2 May 2027 | 1 day before boundary[6] | **SHORT_6** |
| **SHORT_6 / SHORT_3 boundary** | | |
| 4 Feb 2027 | 1 day after boundary[3] | **SHORT_6** |
| 3 Feb 2027 | exactly boundary[3] | **SHORT_3** |
| 2 Feb 2027 | 1 day before boundary[3] | **SHORT_3** |
| **SHORT_3 / SHORT_1 boundary** | | |
| 4 Dec 2026 | 1 day after boundary[1] | **SHORT_3** |
| 3 Dec 2026 | exactly boundary[1] | **SHORT_1** |
| 2 Dec 2026 | 1 day before boundary[1] | **SHORT_1** |
| **SHORT_1 / EXPIRED boundary** | | |
| 4 Nov 2026 | 1 day after T (assessment date) | **SHORT_1** |
| 3 Nov 2026 | exactly T (expiry = today) | **EXPIRED** |
| 2 Nov 2026 | 1 day before T | **EXPIRED** |

**Additional verification of originally-incorrect examples:**

| Expiry date | Correct classification | Was wrong in Task 003C |
|---|---|---|
| 4 Nov 2027 | FULL_PRICE | Task 003C said SHORT_12 (whole-month truncation gave 12) |
| 3 Nov 2027 | SHORT_12 | Correct in 003C |
| 3 Aug 2027 | SHORT_9 | Task 003B said FULL_PRICE (original error); 003C corrected intent but algorithm still misclassified boundary |
| 4 Aug 2027 | SHORT_12 | Needed to verify — now confirmed |

**Expiry on operational_expiry_date (dispatch hard stop):**

The dispatch check uses `today > operational_expiry_date` (strict greater-than), unchanged from Task 003C Correction 2. Combined with the classification rule `expiry > T` for SHORT_1 and `else EXPIRED`, this gives:

- Expiry = 3 Nov 2026 (today): classified EXPIRED; dispatch blocked.
- Expiry = 4 Nov 2026 (yesterday): classified EXPIRED; dispatch blocked.

This is correct. The classification of EXPIRED and the dispatch block use the same `today > expiry` logic, applied consistently.

---

## Correction 2 — Database Zone Eligibility Enforcement

### What Was Wrong

Task 003C proposed a cross-table CHECK constraint using a subquery:

```sql
CHECK (
  status != 'AVAILABLE'
  OR location_id IN (SELECT id FROM locations WHERE zone IN ('A', 'C'))
)
```

This is invalid. Neither MySQL nor PostgreSQL supports subqueries inside CHECK constraints. MySQL silently ignores CHECK constraints in versions before 8.0.16 and in later versions still does not allow subqueries in them. PostgreSQL disallows subqueries in CHECK constraints outright. The constraint would either be silently ignored or rejected at migration time.

### Correct Enforcement Design

Zone eligibility is a cross-table business rule. It must be enforced at the application layer, optionally reinforced by a database trigger. There is no valid single-statement CHECK constraint that can express it.

**Layer 1: Transactional application validation (primary enforcement)**

Every code path that sets `stock_entries.status = 'AVAILABLE'` must:

1. Begin a database transaction.
2. Lock the target `stock_entry` row: `SELECT ... FOR UPDATE`.
3. Lock the target `location` row: `SELECT ... FOR UPDATE`.
4. Verify `location.is_active = true` and `location.zone IN ('A', 'C')` (or the configured eligible zone set).
5. If verification fails: rollback and raise a domain exception — `IneligibleZoneException`.
6. If verification passes: write `stock_entries.status = 'AVAILABLE'` and commit.

The `FOR UPDATE` lock prevents a concurrent process from changing the location's zone between the check at step 4 and the write at step 6. Without the lock, a narrow race condition could allow a location to be reclassified between the check and the write.

```
BEGIN;
SELECT zone, is_active FROM locations WHERE id = :location_id FOR UPDATE;
-- application checks zone eligibility --
UPDATE stock_entries SET status = 'AVAILABLE', version = version + 1
  WHERE id = :entry_id AND version = :expected_version;
COMMIT;
```

This pattern is used for every status transition that sets or requires AVAILABLE, including:
- Putaway confirmation (APPROVED_PUTAWAY → IN_LOCATION → AVAILABLE)
- Quarantine release (QUARANTINE → AVAILABLE after supervisor approval)
- Bin transfer confirmation (new location must be verified before receiving stock)

**Layer 2: Database trigger (belt-and-braces for direct SQL access)**

A BEFORE INSERT OR UPDATE trigger on `stock_entries` enforces the zone check at the database level, catching any write that bypasses the application layer (manual SQL, migration scripts, database console):

```sql
-- PostgreSQL
CREATE OR REPLACE FUNCTION check_stock_entry_zone()
RETURNS TRIGGER AS $$
DECLARE
  loc_zone      VARCHAR;
  loc_is_active BOOLEAN;
BEGIN
  IF NEW.status = 'AVAILABLE' THEN
    IF NEW.location_id IS NULL THEN
      RAISE EXCEPTION 'AVAILABLE stock entry must have a location_id';
    END IF;
    SELECT zone, is_active
      INTO loc_zone, loc_is_active
      FROM locations
     WHERE id = NEW.location_id;
    IF NOT FOUND OR NOT loc_is_active THEN
      RAISE EXCEPTION 'location % does not exist or is inactive', NEW.location_id;
    END IF;
    IF loc_zone NOT IN ('A', 'C') THEN
      RAISE EXCEPTION 'location % is in zone % which is not eligible for AVAILABLE stock', NEW.location_id, loc_zone;
    END IF;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_stock_entry_zone
BEFORE INSERT OR UPDATE ON stock_entries
FOR EACH ROW EXECUTE FUNCTION check_stock_entry_zone();
```

```sql
-- MySQL 8.0+
DELIMITER $$
CREATE TRIGGER trg_stock_entry_zone_bi
BEFORE INSERT ON stock_entries
FOR EACH ROW
BEGIN
  DECLARE loc_zone VARCHAR(10);
  DECLARE loc_active TINYINT(1);
  IF NEW.status = 'AVAILABLE' THEN
    IF NEW.location_id IS NULL THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'AVAILABLE entry must have location_id';
    END IF;
    SELECT zone, is_active INTO loc_zone, loc_active
      FROM locations WHERE id = NEW.location_id;
    IF loc_zone NOT IN ('A','C') OR loc_active = 0 THEN
      SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Location zone is not eligible for AVAILABLE stock';
    END IF;
  END IF;
END$$

CREATE TRIGGER trg_stock_entry_zone_bu
BEFORE UPDATE ON stock_entries
FOR EACH ROW
BEGIN
  -- same body as above, using NEW.location_id and NEW.status
END$$
DELIMITER ;
```

**SQLite (current production):** SQLite does not support triggers that read from other tables in a portable way within a transaction. Application-layer enforcement (Layer 1) is the sole control in SQLite. This is acceptable while the team is small. The trigger is added when the production database migrates to MySQL/MariaDB or PostgreSQL.

The simple single-table CHECK constraint from Task 003B is retained as a minimal guard in SQLite:

```sql
CONSTRAINT chk_available_requires_location
  CHECK (status != 'AVAILABLE' OR location_id IS NOT NULL)
```

**Layer 3: Eligible zone denormalisation (alternative to triggers)**

If triggers are undesirable, an `is_eligible_for_available_stock` boolean column on `locations` enables a single-row CHECK constraint on `stock_entries` that joins via FK without a subquery:

```
locations:
  + is_eligible_for_available_stock BOOLEAN NOT NULL DEFAULT FALSE
    (set TRUE for zones A and C; FALSE for I, Q, D, R)

stock_entries:
  + location_eligible_snapshot BOOLEAN
    (copied from locations.is_eligible_for_available_stock at putaway time)
  
  CONSTRAINT chk_available_eligible
    CHECK (status != 'AVAILABLE' OR location_eligible_snapshot = TRUE)
```

This converts the cross-table rule into a single-row CHECK by denormalising the eligibility flag. It introduces a synchronisation responsibility: when `locations.is_eligible_for_available_stock` changes, all affected `stock_entries` must be updated. This is handled in the location reclassification workflow below. It is a valid approach but adds complexity. The trigger approach is simpler and preferred.

### Location Reclassification and Deactivation

**Scenario: A bin is changed from zone A (eligible) to zone Q (quarantine) while it contains AVAILABLE or RESERVED stock.**

**Step 1 — Block reclassification if stock is present:**

Before writing any change to `locations.zone` or `locations.is_active`, the application checks:

```sql
SELECT COUNT(*) FROM stock_entries
 WHERE location_id = :location_id
   AND status IN ('AVAILABLE', 'RESERVED', 'IN_LOCATION');
```

If the count is greater than zero, the reclassification is blocked:

```
ZONE CHANGE BLOCKED
Bin A-02-C-04 contains 20 units (AVAILABLE).
Clear or relocate all stock before reclassifying this bin.
```

**Step 2 — Force reclassification (supervisor, emergency only):**

If a supervisor forces the zone change (e.g. contamination), the system executes this sequence atomically:

```sql
BEGIN;
-- Lock all affected entries
SELECT id FROM stock_entries
 WHERE location_id = :location_id
   AND status IN ('AVAILABLE', 'RESERVED', 'IN_LOCATION')
   FOR UPDATE;

-- Demote all AVAILABLE/RESERVED entries to IN_LOCATION
-- (removes them from picking eligibility immediately)
UPDATE stock_entries
   SET status = 'IN_LOCATION'
 WHERE location_id = :location_id
   AND status IN ('AVAILABLE', 'RESERVED');

-- Change the location zone
UPDATE locations SET zone = 'Q' WHERE id = :location_id;

-- Create supervisor task
INSERT INTO supervisor_tasks (type, location_id, notes, raised_at)
  VALUES ('ZONE_CHANGE_STOCK_REVIEW', :location_id,
          'Force zone change — stock must be physically relocated or quarantined', NOW());
COMMIT;
```

After this transaction:
- The stock in the bin is no longer AVAILABLE (not on any pick list, not visible in Shopify inventory).
- It is physically still in the bin (now a quarantine zone bin).
- A supervisor task requires the stock to be physically relocated or formally quarantined before the zone change is considered complete.

**Scenario: A location is deactivated (`is_active = FALSE`) while it contains stock:**

Same logic as zone reclassification. Active stock cannot reside in an inactive location. The application blocks deactivation while stock exists. Force deactivation (supervisor) demotes stock to IN_LOCATION and raises a task.

---

## Correction 3 — Dispatched Stock Reconciliation

### What Was Wrong

Task 003C preserved `stock_entries.qty = 12` with `status = DISPATCHED` after dispatch, then included the dispatched quantity in the final balance reconciliation:

```
AVAILABLE: 25 + RESERVED: 6 + QUARANTINE: 3 + DISPATCHED: 12 = 46 ✓
```

This is misleading. `DISPATCHED` entries with non-zero `qty` suggest that dispatched stock is "current inventory of a type called dispatched." It is not. It has physically left the warehouse. Including it in a sum labelled "total" makes the reconciliation look correct while obscuring that the 12 units are not on-hand stock.

Paul's requirement: dispatch reduces current physical stock exactly once; dispatched quantities remain traceable without being counted as current inventory.

### Correct Model

**When stock is dispatched:**

1. A `stock_movements` record is written with `quantity_change = -N` (negative — stock leaves).
2. The `stock_entry.qty` is set to **0** (the physical units are gone).
3. The `stock_entry.status` is set to `DISPATCHED` (a tombstone — the entry exists for traceability, not inventory).

The stock entry at qty=0 anchors the traceability chain (delivery → batch → entry → movement → order → tracking number) without contributing to any inventory sum.

**Ledger balance rule:**

```
Current physical inventory = SUM(stock_entries.qty)
  WHERE status IN ('APPROVED_PUTAWAY', 'IN_LOCATION', 'AVAILABLE', 'RESERVED', 'QUARANTINE')
```

DISPATCHED entries have qty=0, so they contribute nothing even if they appear in a full-table sum. The WHERE clause makes the exclusion explicit.

**Independently computable reconciliation:**

```
On-hand physical inventory = SUM(stock_entries.qty WHERE status NOT IN ('DISPATCHED'))

Alternatively derived from movements:
  Total received    = SUM(stock_movements.quantity_change WHERE type='RECEIPT')
  Total dispatched  = ABS(SUM(stock_movements.quantity_change WHERE type='DISPATCH'))
  Total written off = ABS(SUM(stock_movements.quantity_change WHERE type='WRITE_OFF'))
  
  Expected on-hand = Total received − Total dispatched − Total written off
  Actual on-hand   = SUM(stock_entries.qty WHERE status NOT IN ('DISPATCHED'))
  
  These two values must be equal. Any difference is a reconciliation error.
```

### Revised 48-Bottle Example

Columns: Entry, Batch, Location, Current Qty (stock_entries.qty), Status.

**After all 9 operations (using corrected dispatch model):**

Operation 5 revised — picking and dispatching the 12 reserved bottles:

```
SM-006: type=PICK,     entry=SE-005, quantity_change=0  
        (picking is a physical action recorded as a status change, no qty change here —
         the stock was already separated from AVAILABLE when reserved in SM-005)

SM-007: type=DISPATCH, entry=SE-005, quantity_change=−12
        reference=ORD-001, tracking_number=TRACK-001

After SM-007:
  SE-005.qty    = 12 − 12 = 0
  SE-005.status = DISPATCHED
```

**Full state table after all 9 operations:**

```
┌───────────────────────────────────────────────────────────────────────────┐
│  Stock Entries — GP-RED-001, Batch A2026-047                              │
│  Assessment date: 4 November 2026 (day after all operations)              │
├────────┬────────────┬──────────┬─────────────┬────────────────────────────┤
│ Entry  │ Location   │ Curr Qty │ Status      │ Notes                      │
├────────┼────────────┼──────────┼─────────────┼────────────────────────────┤
│ SE-001 │ –          │        0 │ PUTAWAY_    │ Holding entry, closed      │
│        │            │          │ COMPLETE    │                            │
│ SE-002 │ Bin-01     │        2 │ AVAILABLE   │ 8 − 6 reserved for ORD-002 │
│ SE-003 │ Bin-02     │       13 │ AVAILABLE   │ 20 − 5 transferred − 2     │
│        │            │          │             │ written off                │
│ SE-004 │ Bin-03     │        5 │ AVAILABLE   │ 8 − 3 quarantined          │
│ SE-005 │ Bin-01     │        0 │ DISPATCHED  │ 12 dispatched (ORD-001)    │
│ SE-006 │ Bin-04     │        5 │ AVAILABLE   │ 5 transferred from Bin-02  │
│ SE-007 │ Q-01       │        3 │ QUARANTINE  │ 3 damaged bottles          │
│ SE-008 │ Bin-01     │        6 │ RESERVED    │ Reserved for ORD-002       │
├────────┴────────────┴──────────┴─────────────┴────────────────────────────┤
│  CURRENT PHYSICAL INVENTORY  =  SUM(qty) WHERE status != DISPATCHED       │
│  = 0 + 2 + 13 + 5 + 0 + 5 + 3 + 6  =  34                                │
│  (SE-001 and SE-005 contribute 0; SE-005 excluded by DISPATCHED status)   │
├────────────────────────────────────────────────────────────────────────────┤
│  BREAKDOWN OF CURRENT INVENTORY:                                           │
│  AVAILABLE         =  2 + 13 + 5 + 5                 =  25                │
│  RESERVED          =  6                               =   6                │
│  QUARANTINE        =  3                               =   3                │
│  APPROVED_PUTAWAY/ │                                                       │
│  IN_LOCATION       =  0 (all putaway complete)        =   0                │
│  Current physical  =  25 + 6 + 3 + 0                 =  34                │
├────────────────────────────────────────────────────────────────────────────┤
│  RECONCILIATION:                                                           │
│  Received    (SM-001)                              = +48                   │
│  Dispatched  (SM-007)                              = −12                   │
│  Written off (SM-010)                              =  −2                   │
│  Expected on-hand                                  =  34  ✓                │
│  Actual on-hand (sum of current qtys)              =  34  ✓                │
│                                                                            │
│  TRACEABILITY (not in current inventory):                                  │
│  12 units → SM-007 → SE-005 → ORD-001 → TRACK-001 (fully traceable)       │
│  2 units  → SM-010 → write-off → supervisor authorisation (fully audited) │
└────────────────────────────────────────────────────────────────────────────┘
```

**Key properties demonstrated:**

1. `SE-005.qty = 0` — dispatched stock does not inflate on-hand inventory.
2. The movement ledger (`SM-007 quantity_change = −12`) is the single point where the quantity left the books.
3. `SM-001(+48) + SM-007(−12) + SM-010(−2) = +34` — matches `SUM(stock_entries.qty)` for all non-dispatched entries.
4. Traceability is via `stock_movements`, not via `stock_entries.qty`. SE-005 at qty=0 is the anchor; SM-007 is the evidence of dispatch.
5. No quantity appears in two places. The transfer in SM-008 moved 5 units from SE-003 to SE-006 at zero net change. The reservation in SM-005 moved 12 units from SE-002 to SE-005 at zero net change. SM-007 reduced SE-005 from 12 to 0 — that is the single reduction for dispatch.

---

## Correction 4 — BD-13 Decision Confirmed

Paul has confirmed:

- Shelf-life classification runs **daily** for management notifications.
- Expiry eligibility at **allocation, picking and dispatch** is recalculated **live** from `batches.operational_expiry_date`, not from the cached `expiry_classification` field.

**Design impact:**

The `expiry_classification` field on `batches` is used only for the management notification queue and reporting. It is a denormalised cache of the daily run result.

The three hard enforcement points (allocation, pick list generation, dispatch confirmation) each execute:

```sql
SELECT operational_expiry_date FROM batches WHERE id = :batch_id
-- Application: IF today > operational_expiry_date THEN block
```

This check is performed inside the transaction that creates the allocation, pick list, or dispatch record. It cannot be bypassed by a stale cached classification. A batch that expired between the morning classification run and an afternoon dispatch confirmation will be blocked at the dispatch check.

**Accepted — no further business decision required for BD-13.**

---

## Outstanding Business Decisions (Retained, Unapproved)

The following decisions remain open. None are authorised. Provisional assumptions used in design documents are design defaults, not operational approvals.

| ID | Decision | Urgency | Provisional assumption |
|---|---|---|---|
| BD-01 | Mandatory batch tracking — which product categories? | HIGH — before first receiving session | Mandatory for cosmetics/gels; exempt for accessories when configured by admin |
| BD-02A | Confirm 12/9/6/3/1 month tier boundaries | HIGH — before Phase 3 development | Tiers confirmed in this document; boundary algorithm now correct |
| BD-02C | Which product categories enrolled in shelf-life programme? | HIGH — before first receiving session | Gel polishes and cosmetics; accessories exempt when configured |
| BD-02D | Shopify approach for short-dated stock | HIGH — before Phase 5 Shopify sync design | No automatic exclusion; management decides per batch |
| BD-02F | Cross-tier picking — full-price orders and short-dated stock | HIGH — before Phase 5 pick-list design | FEFO within tier; cross-tier requires supervisor override |
| BD-03 | Bin capacity tracking from start? | MEDIUM — before first receiving session | Defer; configure per bin over time |
| BD-05 | Cold storage products? | MEDIUM — before location setup | Ambient only assumed |
| BD-06 | Supervisor deputy for approvals | HIGH — before first receiving session | Any admin-role user |
| BD-07 | Photo capture — does scanning device have a camera? | MEDIUM — before hardware selection | Optional; text-only fallback if no camera |
| BD-08 | Internal tracking reference format | MEDIUM — before first receiving session | `MP-YYYYMMDD-NNN` |
| BD-09 | Nail Files SKU 4504 — physical pack count | HIGH — before first real delivery | Record boxes only until count verified by supervisor |
| BD-10 | Shortage approval threshold | HIGH — before Phase 3 go-live | 0% (all shortages to supervisor) until confirmed |
| BD-11 | Role permissions matrix for receiving | HIGH — before Phase 3 go-live | Proposed matrix in Task 003A BD-11 — not yet approved |
| BD-12 | ANSWERED — month/year expiry = last day of stated month | Resolved | `operational_expiry_date = last day of stated month; check: today > operational_expiry_date` |
| BD-13 | ANSWERED — daily classification; live check at hard stops | Resolved | Confirmed by Paul |

---

## Testable Acceptance Criteria

The following criteria must pass before the Phase 3 receiving module is accepted.

### AC-01: Expiry Classification Boundaries

Using assessment date 3 November 2026:

| Test | Input expiry | Expected classification |
|---|---|---|
| AC-01a | 4 Nov 2027 | FULL_PRICE |
| AC-01b | 3 Nov 2027 | SHORT_12 |
| AC-01c | 2 Nov 2027 | SHORT_12 |
| AC-01d | 4 Aug 2027 | SHORT_12 |
| AC-01e | 3 Aug 2027 | SHORT_9 |
| AC-01f | 2 Aug 2027 | SHORT_9 |
| AC-01g | 4 May 2027 | SHORT_9 |
| AC-01h | 3 May 2027 | SHORT_6 |
| AC-01i | 3 Feb 2027 | SHORT_3 |
| AC-01j | 3 Dec 2026 | SHORT_1 |
| AC-01k | 3 Nov 2026 | EXPIRED |
| AC-01l | 2 Nov 2026 | EXPIRED |

### AC-02: Zone Eligibility Enforcement

| Test | Scenario | Expected outcome |
|---|---|---|
| AC-02a | Putaway to zone A bin | Stock entry transitions to AVAILABLE |
| AC-02b | Putaway to zone Q bin | Application raises IneligibleZoneException; transaction rolls back; stock entry status unchanged |
| AC-02c | Putaway to inactive bin | Application raises exception; transaction rolls back |
| AC-02d | Direct SQL insert of AVAILABLE entry with zone Q location | Trigger raises exception; write rejected |
| AC-02e | Reclassify bin from A to Q while it contains AVAILABLE stock | WMS blocks reclassification; displays stock count |
| AC-02f | Force reclassify bin from A to Q (supervisor) | Stock demoted to IN_LOCATION; supervisor task created; location zone updated |

### AC-03: Dispatch Reduces Physical Inventory Exactly Once

| Test | Scenario | Expected outcome |
|---|---|---|
| AC-03a | Dispatch 12 units | `stock_entry.qty` set to 0; `stock_movements` record with `quantity_change = −12` |
| AC-03b | On-hand inventory sum after dispatch | Does not include dispatched entry (qty=0); sum = received − dispatched − adjustments |
| AC-03c | Traceability | Dispatched entry links via `stock_movements` to order and tracking number |
| AC-03d | Reconciliation | `SUM(received movements) + SUM(dispatch movements) + SUM(write-off movements) = SUM(stock_entries.qty)` |

### AC-04: Live Expiry Check at Hard Stops

| Test | Scenario | Expected outcome |
|---|---|---|
| AC-04a | Batch classified SHORT_1 (cached) but expired since morning run | Allocation attempt blocked (live check shows expired) |
| AC-04b | Batch classified FULL_PRICE (cached) but operational_expiry_date = today | Dispatch confirmation blocked (live check shows expired) |
| AC-04c | Batch expires during pick-to-dispatch gap | Dispatch confirmation blocked; supervisor notification raised |

---

*No application code, database structure or production settings were changed during this document.*  
*Produced 5 November 2026 — MP Transport WMS Project*
