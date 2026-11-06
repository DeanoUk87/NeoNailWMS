# WMS TASK 005 — Batch Tracking and Stock Ledger Foundation

**Branch:** `feature/catalogue-and-mapping`  
**Commit:** `4a0b26e`  
**Date:** 6 November 2026  
**Status:** Development complete — ready for review  
**Tests:** 85/85 pass (33 new + 52 existing, zero regressions)  
**Production server:** NOT changed. Simulated stock only.

---

## 1. Database Changes (not yet on production server)

### 4 new migrations

| Migration | Creates |
|---|---|
| `2026_11_06_000001_create_batches_table` | `batches` — manufacturer batch tracking with expiry, internal reference, cached classification |
| `2026_11_06_000002_create_stock_entries_table` | `stock_entries` — core inventory record with 11 statuses, optimistic version lock, 3 SQLite triggers |
| `2026_11_06_000003_create_stock_movements_table` | `stock_movements` — immutable audit ledger, 16 movement types, idempotency key |
| `2026_11_06_000004_create_quarantine_records_table` | `quarantine_records` — quarantine tracking with resolution path |

### SQLite triggers (created inside the migration, not in a separate file)

| Trigger | Enforces |
|---|---|
| `trg_stock_entry_available_check_insert` | AVAILABLE entries must have a non-null location in an eligible zone (A or C) |
| `trg_stock_entry_available_check_update` | Same check on status updates |
| `trg_stock_entry_no_negative_qty` | qty can never go below 0 |

These use SQLite's `SELECT RAISE(ABORT, '...')` syntax — valid in-trigger cross-table queries as confirmed in Task 003D Correction 2.

---

## 2. Stock Status Flow

```
RECEIVED_UNVERIFIED ──► PENDING_SUPERVISOR ──► APPROVED_PUTAWAY
                                                      │
                                              putaway (to bin)
                                                      │
                                               IN_LOCATION
                                                      │
                                      (eligible zone confirmed)
                                                      │
                                               AVAILABLE ──────────► QUARANTINE
                                                      │
                                              reserve() [live expiry check]
                                                      │
                                               RESERVED ──────────► RESERVED_HELD
                                                      │               (bin quarantined)
                                              pick() [live expiry check]
                                                      │
                                               PICKED
                                                      │
                                               pack()
                                                      │
                                               PACKED
                                                      │
                                        dispatch() [live expiry check]
                                                      │
                                             DISPATCHED (qty=0, tombstone)
```

---

## 3. Expiry Classification Algorithm

Implemented in `Batch::classifyExpiry()`. Uses direct boundary-date comparison — no whole-month truncation (the error from Task 003C Technical Addendum).

```php
$b12 = $today->addMonthsNoOverflow(12);  // e.g. 3 Nov 2027
$b9  = $today->addMonthsNoOverflow(9);
// ...

if ($expiry->gt($b12)) return 'FULL_PRICE';
if ($expiry->gt($b9))  return 'SHORT_12';
// ...
if ($expiry->gt($today)) return 'SHORT_1';
return 'EXPIRED';
```

**BD-13 confirmed:** Live expiry checks at allocation, pick, and dispatch always use `operational_expiry_date` directly — never the cached `expiry_classification` field.

**Expiry rule:** `today > operational_expiry_date` = EXPIRED. Stock valid ON the expiry date; expired the FOLLOWING day.

**Month/year labels:** `EXP 06/2027` → `operational_expiry_date = 2027-06-30` (last day of stated month). Stored using explicit year/month parsing to avoid locale ambiguity.

---

## 4. Ledger Invariants

| Invariant | Enforcement |
|---|---|
| Physical inventory = SUM(stock_entries.qty WHERE status NOT IN ('DISPATCHED')) | Verified by `assertReconciled()` |
| Movement ledger = Physical inventory | SUM(RECEIPT) − ABS(SUM(DISPATCH)) − ABS(SUM(WRITE_OFF)) = on-hand |
| DISPATCHED entries have qty=0 | Enforced in `dispatch()` method |
| No negative quantities | SQLite trigger `trg_stock_entry_no_negative_qty` |
| AVAILABLE requires eligible zone | SQLite triggers on INSERT and UPDATE |
| Concurrent updates detected | Optimistic version counter — second writer raises RuntimeException |
| No duplicate movements on retry | Idempotency key — silent no-op on retry |
| Expired stock blocked at 3 points | `reserve()`, `pick()`, `dispatch()` each call `assertNotExpired()` live |

---

## 5. Test Results

```
{"tool":"pest","result":"passed","tests":85,"passed":85,"assertions":223}

Breakdown:
  FoundationTest.php   4 tests  — all original acceptance checks
  CatalogueTest.php   13 tests  — Phase 2 catalogue
  Task004Test.php     35 tests  — warehouse/location/user management
  Task005Test.php     33 tests  — stock ledger (new)
  Total:              85/85 PASS
```

### Task005 test coverage

| Section | Tests | What they prove |
|---|---|---|
| Expiry classification | 5 | 15 boundary cases; last-day-of-month; year-only; no-expiry; valid-on/expired-day-after |
| 48-bottle scenario | 7 | Receive; partial putaway ×3; reserve; pick/pack/dispatch; transfer; quarantine; write-down; full reconciliation |
| Failed transactions | 7 | Overpuraway; overreserve; wrong-status reserve; dispatch expired; pick expired; write-down below zero; negative receive; zero adjustment |
| SQLite triggers | 4 | AVAILABLE in Q zone blocked; null location blocked; negative qty blocked; eligible zone allowed |
| Concurrent modification | 2 | Version mismatch raises RuntimeException; idempotency key deduplicates |
| Client separation | 1 | Cross-client on-hand always 0 |
| Location reclassification | 3 | Blocked when stock present; allowed when empty; force path: RESERVED→RESERVED_HELD |
| Reconciliation | 2 | Dispatched tombstone excluded from on-hand; complex multi-op balance |

---

## 6. What Is NOT Built in This Task

Per the task specification, these are explicitly excluded:

- Goods receiving UI (no screens, no forms)
- Shopify inventory synchronisation
- Label printing
- Order management or pick lists
- Real stock import or catalogue import

---

## 7. Outstanding Risks and Limitations

| Risk | Severity | Notes |
|---|---|---|
| SQLite cross-table trigger limit | Low | Triggers use `SELECT RAISE()` which is valid SQLite. The limitation in earlier docs was about CHECK constraints, not triggers. Tested and working. |
| No production migration yet | — | The 4 new migrations have NOT been applied to `nnuk.wms.mp-transport.co.uk`. Requires Paul/Craig approval and a verified backup before `migrate --force`. |
| No goods-in UI | — | Stock can only be created by calling `StockLedgerService::receive()` directly (e.g. via Tinker). The goods receiving interface is Phase 4. |
| `resolveWarehouseId` fallback | Low | If `warehouse_id` is not passed to `receive()`, the service uses the first warehouse for the client. This should be replaced with explicit warehouse selection in Phase 4. |
| Quarantine resolution not wired to UI | Low | `quarantine_records` are created but no Livewire screen to review and resolve them. Phase 4 feature. |

---

## 8. Backup and Rollback Arrangements

**Before applying to production server:**

```bash
# Step 1: Backup (SQLite Online Backup API)
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP=/home/mptportco/backups/neonailwms_pre_task005_${TIMESTAMP}.sqlite
sqlite3 database/database.sqlite ".backup $BACKUP"
sqlite3 "$BACKUP" "PRAGMA integrity_check;"  # Must return 'ok'
sqlite3 "$BACKUP" "SELECT name FROM sqlite_master WHERE type='table' ORDER BY name;"

# Step 2: Preview
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --pretend

# Step 3: Apply
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force

# Rollback: batch 4 only (the 4 Task 005 migrations)
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status  # confirm batch numbers
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:rollback  # rolls back most recent batch
```

**The 4 Task 005 migrations do not touch any existing tables** (batches, stock_entries, stock_movements, quarantine_records are all new). Rolling back removes these 4 tables only. No existing data is affected.

---

*Branch: `feature/catalogue-and-mapping` | Commit: `4a0b26e`*  
*85 tests passing | SQLite :memory: | PHP 8.3.35*  
*No production server changes — simulated stock only*  
*Produced 6 November 2026 — MP Transport WMS Project*
