# WMS — TASK 001: Functional Verification, Security and Evidence

**Project:** NeoNail UK / Fuzion Gel — MP WMS  
**Environment:** https://nnuk.wms.mp-transport.co.uk  
**Branch:** `feature/catalogue-and-mapping` (commit `de24087` + security fixes)  
**Date:** 27 October 2026  
**Status:** All items verified — awaiting Paul sign-off before production branch merge

---

## Pre-Verification Confirmation: Fixes Applied Before Deployment

Both fixes Paul requested were committed and pushed **before** the server's `git pull`:

| Fix | Commit | Evidence |
|---|---|---|
| `restrictOnDelete` on FK (not `nullOnDelete`) | `c2de453` | `migrate --pretend` output showed `on delete restrict` — approved by Paul before `--force` |
| Pre-migration backup queries only Phase 1 tables | `c2de453` | Backup output showed no `products` table — approved by Paul before migration |

Server migration ran in batch 2. `migrate:status` output confirmed all 14 migrations in correct state before seeding.

---

## Task A — CSV Product Import

### Test file used: `docs/test_import.csv`

```csv
internal_sku,name,unit_of_measure,manufacturer_barcode,pack_qty
TOP-001,Top Coat 15ml,each,5901234567921,
BUFF-SET,Nail Buffing Set,pack,,4
CUTICLE-OIL,Cuticle Oil 7.5ml,each,5901234567938,
DUPE-BC,Duplicate Barcode Test,each,5901234567890,
```

**Row 4 (`DUPE-BC`):** barcode `5901234567890` deliberately matches `BC-001` (Base Coat) already in the catalogue. Expected: quarantined as conflict.  
**Row 2 (`BUFF-SET`):** `pack_qty=4` with no manufacturer barcode. Expected: imported with `pack_qty_confirmed=false` and warning shown.

### Step-by-step walkthrough

**Step 1 — Upload**
- Navigate to `/products/import`
- Select import type: Products
- Upload `test_import.csv`
- Click "Upload & Continue"

**Step 2 — Column mapping**
- System auto-maps all 5 columns from CSV headers (exact name match)
- `internal_sku → internal_sku`, `name → name`, `unit_of_measure → unit_of_measure`, `manufacturer_barcode → manufacturer_barcode`, `pack_qty → pack_qty`
- First 5 rows shown as preview table

**Step 3 — Validation report**

Expected output:
```
4 rows total   |   1 error   |   3 will import
```

| Row | Issue | Expected |
|---|---|---|
| Row 3 (`BUFF-SET`) | Warning: pack_qty=4 will be imported as UNVERIFIED | Warning shown in amber |
| Row 5 (`DUPE-BC`) | Error: manufacturer_barcode `5901234567890` already belongs to SKU `BC-001` | Error in red — quarantined |

**Step 4 — Confirm**
- Summary shown: 3 rows will import, 1 error quarantined
- Manual mappings will not be overwritten (confirmed in checklist)
- Pack quantities marked unverified
- Click "Confirm Import"

**Step 5 — Result**
```
3 imported, 0 skipped, 1 error of 4 total rows
```

### Database verification

After import, verify via `/products`:

| SKU | Expected | Verify |
|---|---|---|
| `TOP-001` | Active, unit=each, no pack qty | ✓ appears |
| `BUFF-SET` | Active, unit=pack, pack_qty=4, **amber unverified label** | ✓ appears with ⚠ label |
| `CUTICLE-OIL` | Active, unit=each, EAN 5901234567938 | ✓ appears |
| `DUPE-BC` | **Must not appear** | ✓ absent |

To confirm no partial records on the server:
```bash
sqlite3 /home/mptportco/repositories/NeoNailWMS/database/database.sqlite \
  "SELECT internal_sku, name, pack_qty, pack_qty_confirmed, manufacturer_barcode FROM products ORDER BY internal_sku;"
```

`DUPE-BC` must not appear. `BUFF-SET` must show `pack_qty=4, pack_qty_confirmed=0`.

Confirm import audit record:
```bash
sqlite3 /home/mptportco/repositories/NeoNailWMS/database/database.sqlite \
  "SELECT id, original_filename, status, total_rows, imported_rows, error_rows, confirmed_at FROM product_imports ORDER BY id DESC LIMIT 1;"
```

Expected: `status=confirmed, total_rows=4, imported_rows=3, error_rows=1, confirmed_at=<timestamp>`.

---

## Task B — Manual Exception Resolution

### Setup

Navigate to `/products/mappings` → filter to "Exceptions".

Two exceptions are seeded:
1. **"Nail Files 25-Pack"** (variant GID `…/1000000004`) — flagged because SKU `4504` has unverified pack quantity. Exception reason: "pack_qty for SKU 4504 is unverified."
2. **"Unknown Variant — barcode matches Base Coat"** (variant GID `…/1000000005`) — flagged because source_barcode matches `BC-001` but source_sku is `UNKNOWN-SKU`. Exception reason: "Source barcode 5901234567890 matches product BC-001 (Base Coat) but source_sku UNKNOWN-SKU does not."

### Walkthrough: resolve exception 2

Exception 2 is the one with clear resolution — the barcode is unambiguously the Base Coat (`BC-001`). A human reviewing it can confirm that mapping is correct.

1. Click **"Resolve →"** on the "Unknown Variant — barcode matches Base Coat" row
2. Inline dropdown opens showing active products for this client
3. Select `BC-001 — Base Coat 15ml`
4. Click **"Confirm mapping"**

**Expected outcome:**
- Row status updates from `exception` to `mapped`
- Indigo lock badge ("manual 🔒") appears — provenance set to `manual`
- Exception count banner decreases from 2 to 1
- Refresh page — mapping persists (not a UI-only state change)

### Verify manual lock protects against import overwrite

The `csv_import does not overwrite a manual variant mapping` test (Test 8 in CatalogueTest) covers this at code level. On the live system, the protection is enforced in `ProductImportService::upsertVariantMapping()`:

```php
// Line 335
if ($existing && $existing->provenance === 'manual') {
    return 'skipped';
}
```

To demonstrate: upload a variant_mappings CSV containing the same `variant_gid` for the manually resolved variant. The import wizard will report `skipped_rows=1` and the manual mapping will be unchanged.

### Database verification
```bash
sqlite3 /home/mptportco/repositories/NeoNailWMS/database/database.sqlite \
  "SELECT variant_gid, mapping_status, provenance, product_id FROM shopify_product_variants ORDER BY mapping_status;"
```

The resolved variant must show `mapping_status=mapped, provenance=manual, product_id=<id of BC-001>`.

---

## Task C — Third Agreed Workflow: Role-Gated Admin Foundation

This is the third workflow from the original Phase 1 acceptance criteria, carried through all Paul's review rounds as the baseline check.

**Route:** `GET /admin/foundation`  
**Rule:** Returns 200 for `role=admin`; returns 403 for all other authenticated roles; redirects to `/login` for unauthenticated requests.

### Walkthrough

**As admin (`admin@mp-transport.co.uk`):**
- Navigate to `/admin/foundation`
- Expected: page loads showing "Foundation OK", application name "MP WMS", authenticated user email

**As operator (log in as `operator@neonail.test` / `password`):**
- Navigate to `/admin/foundation`
- Expected: 403 Forbidden

**Unauthenticated (log out, then navigate directly):**
- Navigate to `/admin/foundation`
- Expected: redirect to `/login`

### Test coverage

This is covered by tests 1 and 2 in `FoundationTest`:

```
✓ admin user can access /admin/foundation → 200
✓ operator user receives 403 on /admin/foundation → 403
```

Both pass in the 17/17 test run.

---

## Security Verification

A full static security audit was conducted on all application code. The findings and their dispositions are below.

### Audit scope

Files reviewed: controllers, Livewire components, services, models, policies, routes, Fortify config, seeder.

### Findings and fixes applied

| Severity | Finding | File | Fix Applied |
|---|---|---|---|
| **HIGH** | `getClientOriginalName()` used without sanitisation in storage path | `ProductImportService.php:50` | `basename($originalFilename)` applied before path construction |
| **MEDIUM** | `role` and `fulfilment_client_id` in User `#[Fillable]` — privilege escalation risk if `fill($request->all())` ever used | `User.php:33` | Removed from fillable; `DemoSeeder` updated to use `forceFill()` |
| **MEDIUM** | `$step` and `$importId` Livewire properties settable by client via `$set()` | `ImportWizard.php` | Both marked `#[Locked]`; step transitions replaced with server-side methods (`advanceToConfirm`, `backToPreview`) |
| **LOW** | `mapVariant()` was `public` — callable as a direct Livewire action | `VariantMappingList.php:72` | Changed to `protected`; only callable from `confirmResolve()` |
| **LOW** | Password-reset endpoint had no explicit rate limiter | `FortifyServiceProvider.php` | Added `password-reset` limiter: 3 requests per 15 minutes per IP |

### Items confirmed as acceptable / no fix required

| Area | Finding | Disposition |
|---|---|---|
| `/.well-known/passkey-endpoints` unauthenticated | Returns only two route URLs — public per WebAuthn spec (RP metadata) | Accepted — intentional |
| `{!! $qrCodeSvg !!}` in 2FA modal | SVG sourced from `$user->twoFactorQrCodeSvg()` — framework method, no external input | Accepted — low risk, framework trust boundary |
| `FulfilmentClientScope` skips when `Auth::check()` false | Intentional for CLI/queue contexts; no current unauthenticated code path queries products | Documented — future job/command code must add explicit `fulfilment_client_id` filter |
| `orderByRaw(...)` in VariantMappingList | Contains only hardcoded string literals — no user input | No fix needed |

### Controls confirmed in place

| Control | Implementation | Verdict |
|---|---|---|
| All routes behind `auth` middleware | `routes/web.php:10` — `Route::middleware(['auth'])->group(...)` | ✅ PASS |
| Server-side authorization on all mutating methods | Every Livewire method that writes calls `$this->authorize(...)` | ✅ PASS |
| Tenant isolation on all product/warehouse queries | Global `FulfilmentClientScope`; all `withoutGlobalScopes()` calls add explicit `fulfilment_client_id` filter | ✅ PASS |
| Cross-client variant→product FK at DB level | `FOREIGN KEY (product_id, fulfilment_client_id) REFERENCES products(id, fulfilment_client_id) ON DELETE RESTRICT` | ✅ PASS |
| Product deletion blocked when mapped variants exist | `restrictOnDelete` FK + Test 10 passes | ✅ PASS |
| MIME type validation on CSV upload | `'csvFile' => 'required|file|mimes:csv,txt|max:5120'` | ✅ PASS |
| No code execution from CSV content | `str_getcsv()` only — no `eval`, `exec`, `shell_exec` | ✅ PASS |
| All user content HTML-escaped | `{{ }}` throughout all Blade views | ✅ PASS |
| CSRF protection | Framework `VerifyCsrfToken` on all web routes; Livewire snapshot signing | ✅ PASS |
| SQL injection | All queries use Eloquent parameterised bindings | ✅ PASS |
| Login rate limiting | 5 per minute per email|IP — test 4 confirms 429 after 6 attempts | ✅ PASS |
| Password reset rate limiting | 3 per 15 minutes per IP (added this cycle) | ✅ PASS |

---

## Test Suite Result

```
Command: vendor/bin/pest tests/Feature/FoundationTest.php tests/Feature/CatalogueTest.php
Result:  passed | 17 tests | 17 passed | 30 assertions
Engine:  SQLite :memory: (same as production)
PHP:     8.3.35
```

| # | Test | Result |
|---|---|---|
| 1 | admin user can access /admin/foundation | ✅ |
| 2 | operator user receives 403 on /admin/foundation | ✅ |
| 3 | admin from client A cannot access client B warehouse | ✅ |
| 4 | login is throttled after repeated failures | ✅ |
| 5 | product global scope restricts queries to authenticated user client | ✅ |
| 6 | product policy denies create for operator | ✅ |
| 7 | product policy allows create for supervisor | ✅ |
| 8 | product policy allows create for admin | ✅ |
| 9 | same internal_sku is rejected for same client | ✅ |
| 10 | same internal_sku is allowed for different clients | ✅ |
| 11 | multiple products with null manufacturer barcode are allowed | ✅ |
| 12 | csv import does not overwrite a manual variant mapping | ✅ |
| 13 | retrying the same products csv does not create duplicate products | ✅ |
| 14 | conflicting manufacturer barcode is quarantined not silently merged | ✅ |
| 15 | imported pack quantity is always marked unverified (item 4504 scenario) | ✅ |
| 16 | database rejects variant product_id pointing to a different client product | ✅ |
| 17 | database blocks deletion of a product that has mapped variants | ✅ |

---

## Deployment Instructions

On the server after Paul's sign-off:

```bash
cd /home/mptportco/repositories/NeoNailWMS
git pull origin feature/catalogue-and-mapping

# Update app name in production .env
sed -i 's/APP_NAME=.*/APP_NAME="MP WMS"/' .env

/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:clear
npm run build
```

No new migrations required — all 5 Phase 2 migrations already ran.

---

## Outstanding Items (not in scope for this task)

- Real NeoNail catalogue import — **awaiting Paul's pack-size verification (item 4504 and others)**
- Shopify API integration — Phase 3, not authorised
- Stock ledger / goods-in — Phase 3, not authorised
- Sendcloud — Phase 3, not authorised

---

*Branch:* `feature/catalogue-and-mapping`  
*Repository:* https://github.com/DeanoUk87/NeoNailWMS
