# WMS TASK 003D — Implementation Readiness

**Project:** MP Transport Warehouse Management System  
**Reference:** WMS-TASK-003C-TECHNICAL-ADDENDUM.md  
**Date:** 5 November 2026  
**Status:** Final design corrections — awaiting approval before development  
**Code changes:** NOT AUTHORISED

---

## Note on the SQLite Output at the Top of This Session

The `sqlite3` commands shown (`ALTER TABLE documents ADD COLUMN document_provider text` etc.) were run against `/home/mptportco/data/nnuk-rp/neonail.db`. That is the existing NeoNail RP application database — a separate system from the WMS. No action has been taken on those commands and they have no bearing on the WMS design.

---

## Correction 1 — Expiry Date Consistency

### Single Operational Rule

A product is valid **on** its operational expiry date and expires **the following day**.

```
Stock is EXPIRED when: today > operational_expiry_date
Stock is valid when:   today ≤ operational_expiry_date
```

This applies identically to full dates, month/year-only labels and year-only labels.

### Deriving operational_expiry_date

| Manufacturer label | Provisional operational_expiry_date | Rationale |
|---|---|---|
| Full date: 30/06/2027 | 2027-06-30 | Stored exactly as printed |
| Month/year: EXP 06/2027 | 2027-06-30 (last day of stated month) | Product valid through end of that month |
| Year only: 2027 | 2027-12-31 (last day of stated year) | Most conservative full-year interpretation |

The raw manufacturer string and its precision are stored separately. The `operational_expiry_date` is the computed field used for all system checks.

### Corrected Acceptance Tests — Expiry Boundary

Using `operational_expiry_date = 2027-06-30` and the rule `today > operational_expiry_date`:

| Assessment date (today) | Comparison | Stock status |
|---|---|---|
| 2027-06-29 | 2027-06-29 > 2027-06-30 → false | Valid (SHORT_1 or higher) |
| 2027-06-30 | 2027-06-30 > 2027-06-30 → false | Valid — last day of usability |
| 2027-07-01 | 2027-07-01 > 2027-06-30 → true | **EXPIRED** |

The same `>` comparison applies at all three hard-stop points (allocation, pick list generation, dispatch confirmation), computed live from `batches.operational_expiry_date`.

### Corrected Classification Tests

Assessment date T = 3 November 2026. Threshold boundary dates:

```
boundary[12] = 2027-11-03   (calendar_add(T, 12 months))
boundary[9]  = 2027-08-03
boundary[6]  = 2027-05-03
boundary[3]  = 2027-02-03
boundary[1]  = 2026-12-03
```

Classification rule (unchanged from Technical Addendum):

```
if   expiry > boundary[12] → FULL_PRICE
elif expiry > boundary[9]  → SHORT_12
elif expiry > boundary[6]  → SHORT_9
elif expiry > boundary[3]  → SHORT_6
elif expiry > boundary[1]  → SHORT_3
elif expiry > T            → SHORT_1
else (today > expiry)      → EXPIRED
```

Corrected acceptance tests with `today > expiry` for EXPIRED boundary:

| Test | operational_expiry_date | Rule evaluation | Classification |
|---|---|---|---|
| AC-01a | 2027-11-04 | > 2027-11-03 ✓ | **FULL_PRICE** |
| AC-01b | 2027-11-03 | not > 2027-11-03; > 2027-08-03 ✓ | **SHORT_12** |
| AC-01c | 2027-11-02 | not > 2027-11-03; > 2027-08-03 ✓ | **SHORT_12** |
| AC-01d | 2027-08-04 | not > 2027-11-03; > 2027-08-03 ✓ | **SHORT_12** |
| AC-01e | 2027-08-03 | not > 2027-08-03; > 2027-05-03 ✓ | **SHORT_9** |
| AC-01f | 2027-08-02 | not > 2027-08-03; > 2027-05-03 ✓ | **SHORT_9** |
| AC-01g | 2027-05-04 | not > 2027-08-03; > 2027-05-03 ✓ | **SHORT_9** |
| AC-01h | 2027-05-03 | not > 2027-05-03; > 2027-02-03 ✓ | **SHORT_6** |
| AC-01i | 2027-02-03 | not > 2027-02-03; > 2026-12-03 ✓ | **SHORT_3** |
| AC-01j | 2026-12-03 | not > 2026-12-03; > 2026-11-03 ✓ | **SHORT_1** |
| AC-01k | 2026-11-03 | not > 2026-11-03; not > 2026-11-03 | **EXPIRED** |
| AC-01l | 2026-11-02 | 2026-11-03 > 2026-11-02 ✓ | **EXPIRED** |

Month/year label `EXP 06/2027` → `operational_expiry_date = 2027-06-30`:

| Test | Today | Classification |
|---|---|---|
| AC-01m | 2027-06-29 | SHORT_1 (at T=3-Nov-2026: boundary[1]=2026-12-03; on 2027-06-29: would be EXPIRED — but classification re-runs daily) |
| AC-01n | 2027-06-30 | Valid (not expired — last day of usability) |
| AC-01o | 2027-07-01 | EXPIRED |

---

## Correction 2 — SQLite Transaction Safety

### What Was Wrong

The Technical Addendum proposed `SELECT … FOR UPDATE` as the locking mechanism for zone eligibility validation. SQLite does not support row-level locking or `SELECT FOR UPDATE`. In SQLite, concurrent writes are serialised at the connection level using an exclusive write lock on the entire database file. Proposing `FOR UPDATE` for SQLite is incorrect.

### SQLite Concurrency Model

SQLite uses three lock levels relevant to transactions:

- **SHARED** — read lock; multiple readers allowed simultaneously.
- **RESERVED** — one writer has started; readers still allowed.
- **EXCLUSIVE** — single writer holds; no other reads or writes.

SQLite does not support:
- `SELECT … FOR UPDATE`
- Row-level locks
- Advisory locks

**What SQLite does provide** that is relevant to this design:

1. **`BEGIN IMMEDIATE`** — acquires a RESERVED lock on the database when the transaction starts, not when the first write occurs. This prevents other writers from starting until this transaction commits or rolls back. Readers can still read the database as of the last committed state.

2. **`BEGIN EXCLUSIVE`** — acquires an EXCLUSIVE lock on start. No other connections can read or write. Used when the application needs a consistent view that cannot be invalidated by concurrent readers.

3. **SQLite triggers** — SQLite fully supports `BEFORE INSERT` and `BEFORE UPDATE` triggers that query other tables. This was incorrectly disclaimed in earlier documents; the limitation stated was about cross-table CHECK constraints (which SQLite does not support), not triggers.

### Correct Enforcement Design for SQLite

**Layer 1: Application-layer validation using `BEGIN IMMEDIATE`**

```sql
BEGIN IMMEDIATE;
-- SQLite acquires RESERVED lock. No concurrent writer can start.

SELECT zone, is_active FROM locations WHERE id = :location_id;
-- Application checks zone eligibility. If ineligible: ROLLBACK and raise error.

UPDATE stock_entries
   SET status = 'AVAILABLE', version = version + 1
 WHERE id = :entry_id AND version = :expected_version;

COMMIT;
```

`BEGIN IMMEDIATE` replaces `SELECT FOR UPDATE`. Once a transaction starts with IMMEDIATE, SQLite prevents any other writer from starting, so the zone value cannot be changed between the read and the write. This is the primary enforcement mechanism.

**Layer 2: SQLite BEFORE UPDATE trigger**

SQLite triggers can read from other tables. The following trigger is valid SQLite and enforces zone eligibility at the database level:

```sql
CREATE TRIGGER trg_stock_entry_zone_check
BEFORE UPDATE OF status ON stock_entries
FOR EACH ROW
WHEN NEW.status = 'AVAILABLE'
BEGIN
  SELECT RAISE(ABORT, 'stock entry location_id is required for AVAILABLE status')
  WHERE NEW.location_id IS NULL;

  SELECT RAISE(ABORT, 'location does not exist or is inactive')
  WHERE NOT EXISTS (
    SELECT 1 FROM locations
     WHERE id = NEW.location_id AND is_active = 1
  );

  SELECT RAISE(ABORT, 'location zone is not eligible for AVAILABLE stock')
  WHERE NOT EXISTS (
    SELECT 1 FROM locations
     WHERE id = NEW.location_id AND zone IN ('A', 'C')
  );
END;
```

This is valid SQLite syntax. `SELECT RAISE(...)` is SQLite's mechanism for raising an error inside a trigger. The trigger fires on every UPDATE that attempts to set `status = 'AVAILABLE'`, queries the `locations` table, and aborts the write if the location is missing, inactive or in an ineligible zone.

An equivalent trigger is added for INSERT:

```sql
CREATE TRIGGER trg_stock_entry_zone_check_insert
BEFORE INSERT ON stock_entries
FOR EACH ROW
WHEN NEW.status = 'AVAILABLE'
BEGIN
  SELECT RAISE(ABORT, 'stock entry location_id is required for AVAILABLE status')
  WHERE NEW.location_id IS NULL;

  SELECT RAISE(ABORT, 'location zone is not eligible for AVAILABLE stock')
  WHERE NOT EXISTS (
    SELECT 1 FROM locations
     WHERE id = NEW.location_id AND zone IN ('A', 'C') AND is_active = 1
  );
END;
```

**Layer 3: Retained single-table CHECK constraint**

```sql
CONSTRAINT chk_available_requires_location
  CHECK (status != 'AVAILABLE' OR location_id IS NOT NULL)
```

This is a minimal guard that SQLite evaluates without subqueries. The trigger provides the full eligibility check; the CHECK provides a last-resort guard if the trigger is ever dropped.

### Location Reclassification Under SQLite Concurrency

When a location is force-reclassified by a supervisor while it contains AVAILABLE or RESERVED stock, the application uses `BEGIN IMMEDIATE` to hold off other writers while it performs the demotion sequence:

```sql
BEGIN IMMEDIATE;

-- Check for affected entries
SELECT id FROM stock_entries
 WHERE location_id = :location_id
   AND status IN ('AVAILABLE', 'RESERVED', 'IN_LOCATION');

-- Demote to IN_LOCATION (removes from picking eligibility)
UPDATE stock_entries
   SET status = 'IN_LOCATION', version = version + 1
 WHERE location_id = :location_id
   AND status IN ('AVAILABLE', 'RESERVED');

-- Record the demotion as a movement (zone reclassification event)
INSERT INTO stock_movements (stock_entry_id, movement_type, quantity_change,
  reference_type, reference_id, performed_by, performed_at, notes)
SELECT id, 'ZONE_RECLASSIFICATION', 0,
  'location_reclassification', :location_id, :supervisor_id, unixepoch(), 
  'Demoted from AVAILABLE/RESERVED due to zone reclassification'
FROM stock_entries WHERE location_id = :location_id AND status = 'IN_LOCATION';

-- Change the location zone
UPDATE locations SET zone = :new_zone WHERE id = :location_id;

-- Raise supervisor task (application layer)
INSERT INTO supervisor_tasks (type, location_id, notes, raised_at)
  VALUES ('ZONE_CHANGE_STOCK_REVIEW', :location_id,
          'Force zone change — stock must be physically relocated or quarantined',
          unixepoch());

COMMIT;
```

### For Future PostgreSQL/MySQL Migration

Replace `BEGIN IMMEDIATE` with `BEGIN` + `SELECT … FOR UPDATE` on the specific rows. Replace the SQLite trigger with PostgreSQL's `BEFORE INSERT OR UPDATE` trigger function or MySQL's equivalent. The application-layer logic does not change; only the SQL dialect for locking and trigger syntax differs.

---

## Correction 3 — Quarantine of Reserved Stock

### Scenario

A storage location is reclassified as quarantine or becomes physically unavailable while one or more `stock_entries` with `status = RESERVED` reference it. Those reservations are linked to customer orders.

### Design

**Step 1 — Immediate picking prevention (automatic)**

The location reclassification sequence in Correction 2 sets all RESERVED entries in that location to `IN_LOCATION`. This removes them from RESERVED status while preserving their quantity and batch data. They can no longer be picked.

However, `IN_LOCATION` alone loses the reservation link. Reservations must be preserved differently.

**Revised step: introduce `RESERVED_HELD` status**

Rather than demoting RESERVED entries to `IN_LOCATION`, the system transitions them to `RESERVED_HELD`:

| Status | Meaning |
|---|---|
| `RESERVED` | Stock allocated to an order and in an eligible bin — picking is authorised |
| `RESERVED_HELD` | Stock allocated to an order but bin is unavailable — picking is blocked; reservation is preserved |

```sql
UPDATE stock_entries
   SET status = 'RESERVED_HELD', version = version + 1
 WHERE location_id = :location_id AND status = 'RESERVED';
```

The reservation link (via `stock_movements` reference to the order) is not modified. The order record retains its reservation; the system shows the reservation as blocked.

**Step 2 — Identify affected orders**

```sql
SELECT sm.reference_id AS order_id, se.id AS entry_id,
       se.batch_id, se.location_id, se.qty
  FROM stock_entries se
  JOIN stock_movements sm ON sm.stock_entry_id = se.id
                          AND sm.movement_type = 'RESERVATION'
 WHERE se.status = 'RESERVED_HELD'
   AND se.location_id = :location_id;
```

This query returns every order affected by the location issue. The supervisor sees: order reference, product, batch, quantity held, bin location.

**Step 3 — Supervisor exception screen**

```
[SCREEN: Supervisor — Reserved Stock Exception]
┌──────────────────────────────────────────────────────────────────┐
│  ⚠ LOCATION A-02-C-04 QUARANTINED — 1 order affected            │
│                                                                  │
│  ORD-002  |  Gel Polish Classic Red  |  6 units  |  Batch A      │
│  Bin A-02-C-04 is quarantined. Picking is blocked.              │
│                                                                  │
│  Options:                                                        │
│  [Reassign to eligible stock]                                    │
│  [Hold order — await resolution]                                 │
│  [Cancel order line — notify customer]                           │
└──────────────────────────────────────────────────────────────────┘
```

**Step 4 — Reassignment to eligible stock (without duplicate reservation)**

If the supervisor selects "Reassign to eligible stock":

1. The system finds AVAILABLE stock of the same SKU and batch (or next-oldest FEFO batch if none in the original batch) in an eligible bin.
2. If found: a single atomic transaction:
   - Releases the RESERVED_HELD entry: `stock_entries[held].status = 'IN_LOCATION'` (or appropriate state given the quarantine).
   - Creates a new RESERVED entry from the found AVAILABLE stock.
   - Inserts a `stock_movements` RESERVATION_TRANSFER record referencing both the original and new entry, and the order.
   - The original RESERVATION movement is not deleted — it remains as a historical record showing the initial allocation.
3. The order now has a valid reservation in an eligible bin. Picking can proceed.

**Step 5 — Dispatch prevention until resolved**

Any order with `RESERVED_HELD` entries fails the dispatch eligibility check:

```
DISPATCH BLOCKED
Order ORD-002 has 6 units on hold in a quarantined location.
Supervisor must resolve the reservation before this order can be dispatched.
```

This check runs at dispatch confirmation, independently of the pick list check.

**Audit trail**

Every stage — initial RESERVED, demotion to RESERVED_HELD, reassignment, and final pick — produces a `stock_movements` record. The order's full allocation history is reconstructible:

```
SM-005: RESERVATION         SE-002(12) → SE-005(12, ORD-001, Bin-01)   [original reservation]
SM-011: RESERVATION         SE-003(13) → SE-008(6, ORD-002, Bin-02)    [ORD-002 reservation]
SM-012: ZONE_RECLASSIFICATION SE-008 → RESERVED_HELD                   [location quarantined]
SM-013: RESERVATION_TRANSFER  SE-008(held) released; SE-009(6, ORD-002, Bin-04) created
         references: original SM-011, order ORD-002
```

No reservation is silently removed. No quantity is duplicated.

---

## Correction 4 — Picked, Packed and Dispatched Stock

### Five Physical States

Task 003C defined DISPATCHED as the final state after picking. This was insufficient. The physical journey has five distinct stages:

| State | Physical meaning | Location | Available to other orders? | Physical inventory? |
|---|---|---|---|---|
| **AVAILABLE / RESERVED** | In storage bin | Named bin (A, C zone) | AVAILABLE: yes; RESERVED: no | Yes |
| **PICKED** | Removed from bin; at packing station | Packing area (staged, not in named bin) | No | Yes — still in warehouse |
| **PACKED** | Sealed in parcel; labelled; in dispatch staging | D zone | No | Yes — still in warehouse |
| **DISPATCHED** | Handed to courier; left warehouse premises | Off-site | No | No — no longer in warehouse |
| **RETURNED** | Returned by courier or customer; at returns inspection | R zone | No (pending inspection) | Yes — back in warehouse |

**Physical inventory = everything still physically within warehouse custody = AVAILABLE + RESERVED + PICKED + PACKED + QUARANTINE.**

Physical inventory decreases only when stock transitions to DISPATCHED (leaves premises) or is formally written off. Picking does not reduce physical inventory.

### Stock Movement Sequence

```
SM-N:   RESERVATION     SE-002 → SE-005 (qty=6, RESERVED, Bin-01, ORD-002)
         -- Operator generates pick list; stock is allocated

SM-N+1: PICK            SE-005: status RESERVED → PICKED
         from_location=Bin-01, to_location=PACKING-01
         quantity_change=0 (qty field unchanged; location updated)
         -- Operator physically removes 6 bottles from Bin-01
         -- Bin-01 no longer contains this stock

SM-N+2: PACK            SE-005: status PICKED → PACKED
         from_location=PACKING-01, to_location=DISPATCH-01
         quantity_change=0 (qty unchanged)
         -- Parcel sealed, labelled, moved to dispatch staging area

SM-N+3: DISPATCH        SE-005: status PACKED → DISPATCHED
         quantity_change=−6 (stock leaves warehouse)
         SE-005.qty = 6 − 6 = 0
         tracking_number=TRACK-001
         -- Courier collects; parcel leaves premises
```

**At each stage:**

- PICK: `stock_entry.location_id` changes from the storage bin to a packing-area location code (e.g. `PACK-01`). Status changes to PICKED. `qty` does not change.
- PACK: `stock_entry.location_id` changes to dispatch-staging location (e.g. `DISPATCH-01`). Status changes to PACKED. `qty` does not change.
- DISPATCH: `stock_movements.quantity_change = −N`. `stock_entry.qty` becomes 0. Status becomes DISPATCHED.

### Cancellation, Failed Packing Checks and Return-to-Stock

**Cancellation after picking but before packing:**

```
SM: PICK_CANCELLATION   SE-005: status PICKED → AVAILABLE (or RESERVED if re-allocated)
    from_location=PACKING-01, to_location=Bin-01 (or nearest eligible bin)
    quantity_change=0
    reason="Order cancelled after pick"
    approved_by=supervisor
```

Stock physically returned to its original or nearest eligible bin. Location updated. The original RESERVATION and PICK movements are preserved; the cancellation is a new movement referencing both.

**Failed packing check (wrong item or quantity detected):**

```
SM: PACK_FAILED         SE-005: status PICKED → PICK_ERROR
    reason="Wrong quantity detected at packing — scanner mismatch"
    raised_by=operator

Supervisor reviews:
  Option A: Correct the error — physical recount, update quantity via ADJUSTMENT movement.
  Option B: Return to stock — PICK_CANCELLATION movement, stock back to bin.
  Option C: Quarantine — QUARANTINE_IN movement if stock is damaged.
```

**Missed courier collection:**

Parcel remains in dispatch staging. `stock_entry.status` remains PACKED. No automatic action. Supervisor decides:
- Rebook collection: no system change needed.
- Return to stock: `DISPATCH_CANCELLATION` movement; `status → AVAILABLE`; location back to storage bin.
- The parcel is physically held in the D zone until resolved.

**Return-to-stock from PICKED or PACKED state:**

The PICKED and PACKED entries retain their batch and quantity. Return-to-stock creates a movement back through the chain:

```
PACKED/PICKED → RETURN_TO_STORAGE → AVAILABLE (in original or new eligible bin)
```

A `stock_movements` RETURN_TO_STORAGE record is created. The stock re-enters the AVAILABLE pool. The original order's pick and pack records are preserved as history. No new quantity is created.

### Physical Inventory Reconciliation (Revised)

```
Physical inventory (all stock in warehouse custody) =
  SUM(stock_entries.qty WHERE status IN (
    'APPROVED_PUTAWAY', 'IN_LOCATION', 'AVAILABLE',
    'RESERVED', 'RESERVED_HELD', 'PICKED', 'PACKED', 'QUARANTINE'
  ))

Available for new orders =
  SUM(stock_entries.qty WHERE status = 'AVAILABLE')

Dispatched (historical) =
  Traceable via stock_movements WHERE type='DISPATCH'
  stock_entries with status=DISPATCHED have qty=0 — not in physical inventory
```

### Updated 48-Bottle Reconciliation (Full 5-State Model)

Extending the example from Task 003C Technical Addendum to show PICKED and PACKED stages:

After Operation 4 (12 units reserved for ORD-001 in SE-005):

```
Operation 5a — Operator picks SE-005 (12 from Bin-01):

SM-006: type=PICK, entry=SE-005, quantity_change=0
        from_location=Bin-01, to_location=PACK-01, performed_at=03/11/2026 14:30
SE-005: location=PACK-01, qty=12, status=PICKED

Physical inventory: 2(SE-002)+13(SE-003)+5(SE-004)+12(SE-005)+5(SE-006)+3(SE-007)+6(SE-008)
  = 46 (all physically in warehouse — 12 now at packing station)

Operation 5b — Parcel packed and moved to dispatch staging:

SM-007: type=PACK, entry=SE-005, quantity_change=0
        from_location=PACK-01, to_location=DISPATCH-01, performed_at=03/11/2026 14:45
SE-005: location=DISPATCH-01, qty=12, status=PACKED

Physical inventory: still 46 (now 12 in dispatch staging)

Operation 5c — Courier collects; parcel leaves warehouse:

SM-008: type=DISPATCH, entry=SE-005, quantity_change=−12
        tracking_number=TRACK-001, performed_at=03/11/2026 15:00
SE-005: qty=0, status=DISPATCHED

Physical inventory: 46 − 12 = 34
```

This matches the final reconciliation in Task 003C (after all 9 operations, physical on-hand = 34), but now the reduction happens at the correct moment — when the parcel leaves the premises, not when the operator picks it.

---

## Genuine Unresolved Implementation Blockers

**Blocker 1 — No warehouse or location records in production**

The `warehouses` and `locations` tables exist in the production database but contain no data. No receiving session can begin without at least:
- One warehouse record for NeoNail UK.
- An INBOUND zone (I) with at least one holding bin.
- A QUARANTINE zone (Q) with at least one bin.
- Storage zone bins (A) for putaway.

This is a data setup task, not a development task. It must be done before any receiving workflow goes live.

**Blocker 2 — Bin capacity is not configured**

BD-03 is unresolved. Without configured bin capacities, the system cannot provide meaningful capacity warnings during putaway. The putaway workflow will function (it will place stock without checking capacity) but operators must judge physically when a bin is full. This is acceptable as an initial operating mode.

**Blocker 3 — 15 outstanding business decisions**

BD-01 through BD-11 (excluding BD-12 and BD-13 which are resolved) remain unanswered. The receiving module cannot be built without decisions on BD-01 (batch tracking scope), BD-06 (supervisor deputy), BD-10 (shortage threshold) and BD-11 (role permissions). Development can begin on the location management UI and user management UI without these decisions.

---

## Recommended First Development Task

**Build: Location Management UI and User Management UI**

These two screens are prerequisites for every operational workflow. They have no unresolved business decisions blocking them. They can be built, tested and deployed before the receiving module design is finalised.

**Scope (location management):**

- Create warehouse record (name, client association).
- Create location record (warehouse, zone, name, barcode — auto-generated or entered manually, location_type, is_active).
- Edit location (name, zone, is_active — with zone-change safeguards from Correction 2).
- List locations with filter by warehouse, zone, active status.
- Print location barcode label (deferred until printer integration is designed — see printer assessment document).

**Scope (user management):**

- Create user (name, email, password, role, fulfilment_client_id) — admin only.
- Edit user (name, role, active status).
- List users with filter by role and client.
- Deactivate user (not delete — preserves audit trail).

**Why this first:**

- Removes the Tinker dependency for user provisioning (a current operational blocker).
- Populates the warehouse and location tables without which no subsequent workflow can run.
- Is self-contained: no dependency on batch tracking decisions, no scanner integration needed.
- Testable with the existing Pest suite pattern.
- Paul and Craig can create locations and users through the UI before the receiving module is ready, giving them early confidence in the system.

**Estimated scope:** 4–6 Livewire components, 2–3 new migrations (location management has the schema; user management needs no migration), 10–12 Pest tests covering role enforcement and client scoping.

---

## Summary of Corrections

| Correction | Change |
|---|---|
| C-01 Expiry consistency | Single rule: valid on expiry date, expired the following day. `today > operational_expiry_date`. Consistent for full dates and month/year labels. All acceptance tests corrected. |
| C-02 SQLite safety | `SELECT FOR UPDATE` replaced with `BEGIN IMMEDIATE`. SQLite trigger specified with correct syntax (`SELECT RAISE(ABORT, ...)`). Full trigger text for both INSERT and UPDATE provided. `SELECT FOR UPDATE` noted for PostgreSQL/MySQL only. |
| C-03 Reserved stock quarantine | New `RESERVED_HELD` status introduced. Affected orders identified by query. Supervisor exception screen defined. Reassignment creates `RESERVATION_TRANSFER` movement. Original reservation records preserved. Dispatch blocked until resolved. |
| C-04 Pick/pack/dispatch stages | Five physical states defined: AVAILABLE/RESERVED → PICKED → PACKED → DISPATCHED. `stock_entry.qty` unchanged at PICK and PACK; reduced to 0 only at DISPATCH. Physical inventory includes PICKED and PACKED. Cancellation, failed pack check and missed collection procedures defined. |

---

*No application code, database structure or production settings were changed during this document.*  
*Produced 5 November 2026 — MP Transport WMS Project*
