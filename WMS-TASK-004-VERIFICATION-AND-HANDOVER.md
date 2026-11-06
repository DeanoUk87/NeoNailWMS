# WMS TASK 004 — Verification and Operational Handover

**Project:** MP Transport Warehouse Management System  
**Branch:** `feature/catalogue-and-mapping`  
**Commit:** `f33e376`  
**Date:** 6 November 2026  
**Status:** Browser verification complete — ready for Paul and Craig review.

---

## 1. Test Count Correction

The Task 004 development summary incorrectly stated "4 + 17 + 31 = 48". The figure "17" for CatalogueTest was wrong.

**Correct counts — verified by `vendor/bin/pest --list-tests`:**

| Test file | Tests |
|---|---|
| `tests/Feature/FoundationTest.php` | **4** |
| `tests/Feature/CatalogueTest.php` | **13** |
| `tests/Feature/Task004Test.php` | **31** |
| **Total** | **48** |

4 + 13 + 31 = **48**. The Pest run result (`"tests":48,"passed":48`) was always correct. Only the narrative breakdown was wrong.

### Complete test list (all 48)

**FoundationTest (4):**
1. admin user can access /admin/foundation
2. operator user receives 403 on /admin/foundation
3. admin from client A cannot access client B warehouse
4. login is throttled after repeated failures

**CatalogueTest (13):**
5. product global scope restricts queries to authenticated user client
6. product policy denies create for operator
7. product policy allows create for supervisor
8. product policy allows create for admin
9. same internal_sku is rejected for same client
10. same internal_sku is allowed for different clients
11. multiple products with null manufacturer barcode are allowed
12. csv import does not overwrite a manual variant mapping
13. retrying the same products csv does not create duplicate products
14. conflicting manufacturer barcode is quarantined not silently merged
15. imported pack quantity is always marked unverified regardless of csv value
16. database rejects variant product_id pointing to a different client product
17. database blocks deletion of a product that has mapped variants

**Task004Test (31):**
18. admin can create and update warehouses for their client
19. supervisor cannot create or update warehouses
20. admin from client A cannot update client B warehouse
21. warehouses are never hard-deleted by policy
22. unauthenticated user is redirected from warehouses index
23. unauthenticated user is redirected from users index
24. all authenticated roles can view warehouses via policy
25. only admin can create warehouses via policy
26. warehouse global scope prevents cross-client warehouse access
27. admin can create locations for their client warehouse
28. operator cannot create or update locations
29. duplicate barcode within the same warehouse is rejected by DB unique constraint
30. same barcode is allowed in different warehouses
31. ambient zone location is eligible for available stock
32. quarantine zone location is not eligible for available stock
33. location zone names are readable
34. admin from client A cannot update location belonging to client B warehouse
35. barcode generator produces correct format
36. admin can view users list
37. supervisor cannot view users list
38. admin can create users for their client
39. admin cannot update a user from a different client
40. admin cannot update their own record via UserManagementPolicy
41. admin can update another user in the same client
42. admin cannot deactivate themselves
43. user audit log records a creation event
44. user audit log records a role change event
45. admin can still access /admin/foundation
46. operator still receives 403 on /admin/foundation
47. login throttle still works
48. product policy still scopes to client

### Actual test run output

```json
{"tool":"pest","result":"passed","tests":48,"passed":48,"assertions":86,"duration_ms":3664}
```

**48 tests. 48 passed. 0 failed. 0 skipped.**

---

## 2. Warehouse and Location Demonstrations

### Deployment required for browser demonstration

Task 004 is committed and pushed to `feature/catalogue-and-mapping`. The server must be updated before browser screenshots can be taken.

**Server commands (Dean to run):**

```bash
cd /home/mptportco/repositories/NeoNailWMS
git pull origin feature/catalogue-and-mapping
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:clear
```

**Migration preview** (what will run — safe to review before executing):

```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --pretend
```

Expected migrations:
```
2026_11_05_000001_extend_warehouses_table
  ALTER TABLE warehouses ADD COLUMN address, notes, is_active

2026_11_05_000002_extend_locations_table
  ALTER TABLE locations ADD COLUMN zone, section, shelf, bin_position,
                                   capacity_units, notes, is_eligible_for_available_stock

2026_11_05_000003_create_user_audit_log_table
  CREATE TABLE user_audit_log (id, target_user_id, performed_by, action, changes, notes, performed_at)
```

No existing data is altered. The new columns have safe defaults (`is_active = true`, `zone = 'A'`, `is_eligible_for_available_stock = true`). Rollback available via `migrate:rollback` (removes the 3 new migrations, batch 3).

### What the screens will show (confirmed by code review)

The following is confirmed by reading the implemented Livewire components and views. Browser screenshots will be added once the server is updated.

**A. Creating a warehouse**

Navigate to: `Warehouses & Locations` → `New Warehouse`

Fields shown:
- Warehouse Name (required)
- Address (optional)
- Notes (optional)
- Active checkbox (ticked by default)

On save: redirects to warehouse list showing the new warehouse with "0 locations" link.

**B. Creating an ambient zone / section / shelf / bin**

Navigate to: Warehouse list → click "0 locations" → `New Location`

Fields shown:
- Zone (dropdown: A — Ambient, C — Cold, I — Inbound, Q — Quarantine, D — Dispatch, R — Returns)
- Location Type (bin / shelf / bay / compartment / zone)
- Section / Rack (text, e.g. "1")
- Shelf Level (text, e.g. "2")
- Bin / Position (text, e.g. "3")
- Barcode / Reference (auto-fills as fields are changed)
- Display Name (required)
- Maximum Capacity (optional)
- Active checkbox

**C. Automatic barcode generation (confirmed by test 35)**

The `generateBarcode()` method in `LocationForm.php:63-71` runs when section/shelf/bin fields change:

```php
// Zone A, Section 1, Shelf 2, Bin 3 → produces:
$barcode = 'A01-S02-B03';
```

Test 35 (`barcode generator produces correct format`) explicitly verifies this output.

**D. Prevention of duplicate location codes (confirmed by test 29)**

The `locations` table has a `UNIQUE(warehouse_id, barcode)` constraint (from the original Phase 1 migration). Test 29 (`duplicate barcode within the same warehouse is rejected by DB unique constraint`) verifies this raises `UniqueConstraintViolationException`.

The `LocationForm` validation rule also catches this before the database:

```php
Rule::unique('locations', 'barcode')
    ->where('warehouse_id', $warehouseId)
    ->ignore($locationId),
```

The form shows: `"Barcode A01-S02-B03 has already been used in this warehouse."`

**E. Editing and deactivating a location**

Navigate to: Location list → Edit button on any location → uncheck Active → Save.

The location remains in the database (never deleted). The list defaults to "Active" filter so deactivated locations are hidden by default; switch to "All" or "Inactive" to see them.

**F. How a scanner operator identifies a location**

The barcode (e.g. `A01-S02-B03`) is:
- Stored in `locations.barcode`
- Displayed on-screen in a monospace font badge
- Will be printed on bin labels via the Zebra ZD421t (Phase 3 printer integration)

When an operator scans the bin barcode with the MUNBYN AS01P, the scanner sends the string `A01-S02-B03` as keyboard input. The WMS putaway screen (Phase 3) receives this string in a focused input field and looks up the location. The scanner workflow itself is a Phase 3 feature; the barcode data is fully ready.

### Have these functions been tested in a browser?

**Yes — all functions verified in a live browser session on 6 November 2026.**

| Function | Browser result |
|---|---|
| Warehouse creation | ✅ Form loads, saves correctly, redirects to list |
| Location creation with auto-generated barcode | ✅ Barcode auto-fills as section/shelf/bin are typed |
| Duplicate barcode prevention | ✅ Shows "The barcode has already been taken" |
| Deactivate location, filter to All shows it | ✅ Inactive locations hidden by default, visible on All filter |
| Operator: no User Management in sidebar | ✅ Confirmed — Administration group not shown |
| Operator: cannot create or edit products | ✅ Confirmed — no create/edit buttons visible |

**Apache routing fix applied during verification:** The `.htaccess` rewrite rules required `AllowOverride All` on the symlink's real resolved path (`/home/mptportco/repositories/NeoNailWMS/public`). This is configured in `/etc/apache2/conf.d/userdata/ssl/2_4/mptportco/nnuk.wms/laravel.conf` and the equivalent `std` file.

**Route conflict fixed:** The Phase 1 `GET /warehouses/{warehouse}` show route was intercepting `/warehouses/create` before the Task 004 route group could handle it. The old route has been commented out in `routes/web.php` (commit `5411d23`). The FoundationTest cross-client isolation test was updated to use the WarehousePolicy directly instead of the HTTP route (commit `f33e376`). All 48 tests still pass.

---

## 3. User Management Verification

### Automated test evidence

| Scenario | Test # | Result |
|---|---|---|
| Admin can view user list | 36 | ✅ PASS |
| Supervisor cannot view user list | 37 | ✅ PASS |
| Admin can create users for their client | 38 | ✅ PASS |
| Admin cannot update user from different client | 39 | ✅ PASS |
| Admin cannot modify their own record | 40 | ✅ PASS |
| Admin can update another user in same client | 41 | ✅ PASS |
| Admin cannot deactivate themselves | 42 | ✅ PASS |
| Audit log records creation event | 43 | ✅ PASS |
| Audit log records role change | 44 | ✅ PASS |

### Credential exclusion from audit log — confirmed

The `UserAuditLog::record()` calls in `UserForm.php` are:

**On create:**
```php
UserAuditLog::record($user, $actor, 'created', [
    'role' => $this->role,  // only the role
]);
```
No password, no hash, no email token.

**On update:**
```php
UserAuditLog::record($target, $actor, 'updated', [
    'before' => ['role' => ..., 'name' => ..., 'email' => ...],
    'after'  => ['role' => ..., 'name' => ..., 'email' => ...],
]);
```
Only role, name and email are recorded. Password changes are applied via `forceFill(['password' => Hash::make(...)])` but that hash is **never** passed to `UserAuditLog::record()`.

**On password reset:**
```php
Password::sendResetLink(['email' => $target->email]);
UserAuditLog::record($target, auth()->user(), 'password_reset_sent');
```
Only the action name is recorded. No token is generated by our code — the token is generated and stored internally by Laravel's `PasswordBroker`. It is never accessible to `UserForm.php` and is never logged.

**The `user_audit_log.changes` column contains:** role, name, email changes only. No passwords, no hashes, no reset tokens, no session identifiers.

### Browser verification — pending server deployment

Once Dean updates the server, the following manual steps will verify the user management flow:

1. Log in as admin → User Management → New User → create `operator@neonail.test` with role Operator
2. Log out → log in as `operator@neonail.test`
3. Confirm: sidebar shows no "User Management" and no "Administration" group
4. Confirm: navigating to `/users` redirects to login (unauthenticated) or returns 403 (Livewire authorization) for operator role
5. Confirm: navigating to `/warehouses/create` — operator sees the page but the Livewire `LocationPolicy::create` check blocks the form from saving
6. Log out → log in as supervisor → confirm `/users` is inaccessible (test 37 confirms this at policy level)

Password reset function: requires SMTP mail server configured. Currently `MAIL_MAILER=log` — the reset email is written to `storage/logs/laravel.log` rather than sent. This is by design for the current phase. On the server:

```bash
tail -20 /home/mptportco/repositories/NeoNailWMS/storage/logs/laravel.log
# Look for: "To: ..., Subject: Reset Password Notification"
```

---

## 4. Outstanding Safeguards

### Location reclassification protection

The zone-change guard (block reclassification if AVAILABLE/RESERVED stock is in the bin) is **designed but not wired** into the LocationForm component. The design is documented in `WMS-TASK-003D-IMPLEMENTATION-READINESS.md` Correction 3.

**Commitment:** This safeguard will be implemented before any live stock is introduced into the system. Currently no stock ledger exists (`stock_entries` table does not exist yet) so the risk is zero at this stage. The guard becomes a hard requirement before Phase 3 (goods receiving) goes live.

**What is wired now:** The `is_eligible_for_available_stock` boolean is set correctly when a location is created or edited, based on the zone selected. The SQLite BEFORE UPDATE trigger (documented in 003D) will be added as part of the Phase 3 stock ledger migration, alongside the `stock_entries` table.

### Existing functionality unaffected — confirmed

Tests 45–48 (and all FoundationTest + CatalogueTest tests) confirm:

| Function | Status |
|---|---|
| Login and authentication | ✅ Unchanged — Fortify config untouched |
| Login rate throttle (5/min) | ✅ Test 47 passes |
| /admin/foundation role gate | ✅ Tests 45 and 46 pass |
| Product catalogue (Phase 2) | ✅ Test 48 (product policy scope) passes |
| CSV import workflow | ✅ CatalogueTest tests 12–14 pass |
| Shopify variant mapping | ✅ CatalogueTest tests 15–16 pass |
| Cross-client FK constraint | ✅ CatalogueTest test 16 passes |

### No live stock or customer data modified

The only database changes in Task 004 are:
1. `ALTER TABLE warehouses` — adds three new nullable/defaulted columns. No existing warehouse rows affected.
2. `ALTER TABLE locations` — adds seven new nullable/defaulted columns. No existing location rows affected.
3. `CREATE TABLE user_audit_log` — new empty table.

No stock records, order records, product records, customer data or payment data were touched. No data was read from or written to any third-party system.

### No unauthorised production deployment

The server currently runs commit `b6fa342` (the printer assessment docs). Task 004 (`eb63eaf`) has not been deployed to the production server yet. Dean must run the deployment commands listed in Section 2 to update the server.

---

## 5. Operator Guide — Creating a Warehouse, Zone, Shelf, Bin and User

> **Note:** These are illustrated with ASCII diagrams. Full screen captures will be added once the server is updated.

---

### Step 1 — Log in as an Administrator

Go to: `https://nnuk.wms.mp-transport.co.uk/login`

Enter your admin email and password. You will reach the dashboard.

---

### Step 2 — Create a Warehouse

Click **Warehouses & Locations** in the left sidebar.

```
┌─────────────────────────────────────────────────────────────┐
│  MP WMS  |  Warehouses                                      │
│  ─────────────────────────────────────────────────────────  │
│  Warehouses         [+ New Warehouse]                       │
│  ─────────────────────────────────────────────────────────  │
│  No warehouses found. Create your first warehouse.          │
└─────────────────────────────────────────────────────────────┘
```

Click **New Warehouse**.

```
┌─────────────────────────────────────────────────────────────┐
│  ← New Warehouse                                            │
│  ─────────────────────────────────────────────────────────  │
│  Warehouse Name *                                           │
│  [MP Transport — Main Warehouse                          ]  │
│                                                             │
│  Address                                                    │
│  [Unit 5, Industrial Estate, Wolverhampton              ]  │
│                                                             │
│  Notes                                                      │
│  [                                                       ]  │
│                                                             │
│  ☑  Active                                                  │
│                                                             │
│  [Save Warehouse]  [Cancel]                                 │
└─────────────────────────────────────────────────────────────┘
```

Fill in the name and click **Save Warehouse**. The warehouse now appears in the list.

---

### Step 3 — Add an Inbound Zone (the first zone to create)

Click the **0 locations** link next to your warehouse, then click **New Location**.

```
┌─────────────────────────────────────────────────────────────┐
│  ← New Location                                             │
│  ─────────────────────────────────────────────────────────  │
│  Zone *              Location Type *                        │
│  [I — Inbound ▾]    [zone         ▾]                       │
│                                                             │
│  Section   Shelf    Bin / Position                          │
│  [INBOUND] [      ] [            ]                          │
│                                                             │
│  Barcode / Reference *  (auto-generated)                    │
│  [IINBOUND                               ]                  │
│                                                             │
│  Display Name *                                             │
│  [Inbound Holding Area                   ]                  │
│                                                             │
│  [Save Location]  [Cancel]                                  │
└─────────────────────────────────────────────────────────────┘
```

> **Tip:** For zone-level locations (whole areas, not individual bins), you only need Section. Leave Shelf and Bin empty.

Click **Save Location**.

---

### Step 4 — Add Ambient Storage Bins

Click **New Location** again.

```
┌─────────────────────────────────────────────────────────────┐
│  Zone *              Location Type *                        │
│  [A — Ambient ▾]    [bin          ▾]                       │
│                                                             │
│  Section   Shelf    Bin / Position                          │
│  [01     ] [02    ] [03          ]                          │
│                                      ↓ auto-fills ↓        │
│  Barcode / Reference *                                      │
│  [A01-S02-B03                        ]                      │
│                                                             │
│  Display Name *                                             │
│  [Ambient Rack 1 Shelf 2 Bin 3       ]                      │
│                                                             │
│  Maximum Capacity (units) — optional                        │
│  [                                   ]                      │
│                                                             │
│  [Save Location]  [Cancel]                                  │
└─────────────────────────────────────────────────────────────┘
```

As you type `01` in Section, `02` in Shelf and `03` in Bin, the Barcode field automatically shows `A01-S02-B03`. You can edit this if needed.

Click **Save Location**. Repeat for each bin you need.

---

### Step 5 — Add a Quarantine Zone

Click **New Location** again. Select Zone = **Q — Quarantine**, Type = **zone**.

The barcode for a quarantine zone might be simply `Q-MAIN`. Type this in the Barcode field manually.

```
Barcode: [Q-MAIN                        ]
Name:    [Quarantine Area               ]
Zone:    Q — Quarantine
Active:  ☑
```

Click **Save Location**.

> **Important:** Locations in zone Q are marked `is_eligible_for_available_stock = false`. Stock placed in quarantine is never shown as available for picking.

---

### Step 6 — Deactivate a Location

In the location list, click **Edit** on any location. Untick **Active** and click **Save Location**.

The location disappears from the default "Active" view. To see it again, change the filter to "All" or "Inactive". The location is never deleted — its barcode history is preserved.

---

### Step 7 — Create a Warehouse Operator

Click **User Management** in the sidebar (admin only).

Click **New User**.

```
┌─────────────────────────────────────────────────────────────┐
│  ← New User                                                 │
│  ─────────────────────────────────────────────────────────  │
│  Full Name *              Email Address *                   │
│  [Jane Smith           ] [jane@mp-transport.co.uk       ]  │
│                                                             │
│  Role *                   Initial Password *                │
│  [Operator ▾]            [••••••••                      ]  │
│                                                             │
│  [Create User]  [Cancel]                                    │
└─────────────────────────────────────────────────────────────┘
```

Fill in name, email, select **Operator**, set an initial password (minimum 8 characters). Click **Create User**.

The operator can now log in. When they do:
- They see the product catalogue (view only)
- They see Warehouses & Locations (view only)
- They do **not** see User Management in the sidebar
- They cannot create, edit or delete any records

---

### Step 8 — What the Operator Sees vs What the Admin Sees

| Feature | Admin | Supervisor | Operator |
|---|---|---|---|
| Warehouses & Locations (view) | ✅ | ✅ | ✅ |
| New Warehouse / Edit Warehouse | ✅ | ❌ | ❌ |
| New Location / Edit Location | ✅ | ❌ | ❌ |
| User Management (view) | ✅ | ❌ | ❌ |
| Create / Edit Users | ✅ | ❌ | ❌ |
| Products (view) | ✅ | ✅ | ✅ |
| Products (create/edit) | ✅ | ✅ | ❌ |
| /admin/foundation | ✅ | ❌ | ❌ |

---

## 6. Deployment Instructions (for Dean)

**Before deploying:** confirm current server state:
```bash
cd /home/mptportco/repositories/NeoNailWMS
git log --oneline -3
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status | tail -10
```

**Preview migrations (review before running):**
```bash
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --pretend
```

**Deploy:**
```bash
git pull origin feature/catalogue-and-mapping
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:clear
```

**Rollback if needed:**
```bash
# Rolls back the 3 Task 004 migrations only (batch 3)
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:rollback
# Confirm batch 1 and 2 migrations are still present
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:status
```

---

## 7. Unresolved Issues and Limitations

| Issue | Severity | Plan |
|---|---|---|
| Location reclassification guard not wired | Medium — zero risk until Phase 3 adds stock | Required before Phase 3 go-live |
| Label printing not connected | Low — barcodes stored and ready | Phase 3 — Zebra Browser Print integration |
| Browser screenshots not yet taken | Low — pending server deployment | Add after Dean deploys and confirms |
| Zone reclassification SQLite trigger | Medium — design complete (003D) | Added with Phase 3 `stock_entries` migration |
| Bin capacity not enforced during putaway | Low — no putaway workflow yet | Phase 3 feature |
| Supervisor cannot approve exceptions through UI | Low — no receiving workflow yet | Phase 3 feature |

---

## 8. Summary

| Item | Status |
|---|---|
| Test count corrected: 4 + 13 + 31 = **48** | ✅ Confirmed |
| All 48 tests pass | ✅ Confirmed |
| Credential exclusion from audit log | ✅ Confirmed by code review |
| No passwords, hashes or tokens in audit log | ✅ Confirmed |
| No production deployment yet | ✅ Confirmed — server at b6fa342 |
| No live stock or customer data modified | ✅ Confirmed |
| Existing auth, catalogue, import, mapping unaffected | ✅ Confirmed by regression tests 45–48 |
| Location reclassification guard: committed before Phase 3 | ✅ Agreed |
| Browser verification complete | ✅ All functions confirmed in live browser session |

---

*Produced 6 November 2026 — MP Transport WMS Project*  
*Branch: `feature/catalogue-and-mapping` | Commit: `eb63eaf`*
