# NeoNail WMS — Phase 2 Review & Handover
**For:** Craig & Paul  
**From:** Dean (via Kilo AI)  
**Date:** 24 September 2026  
**Subject:** Product catalogue, Shopify variant mapping and CSV import — ready for review before production deployment

---

## What This Document Is

Phase 2 of the NeoNail WMS builds the product catalogue, Shopify variant mapping, and CSV import tooling on top of the authenticated foundation deployed in Phase 1.

**Nothing in this phase has been run on the live production server.** All work is on a separate Git branch (`feature/catalogue-and-mapping`) with its own test database. A production migration requires Craig and Paul to review and approve this document first.

---

## 1. Branch & Commit

| | |
|---|---|
| **Repository** | https://github.com/DeanoUk87/NeoNailWMS |
| **Branch** | `feature/catalogue-and-mapping` |
| **Commit** | `cd0a9a7` |
| **Pull Request** | https://github.com/DeanoUk87/NeoNailWMS/pull/new/feature/catalogue-and-mapping |
| **Based on** | `main` at `0a15143` |

---

## 2. What Was Built

### 2.1 Database Tables (new — branch only, not yet on production)

#### `products`
Stores one row per stockkeeping unit. Each Shopify variant that is separately saleable maps to exactly one product row.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | Primary key |
| `fulfilment_client_id` | bigint | Tenant scoping |
| `internal_sku` | string | Primary WMS code. Unique per client |
| `name` | string | Product name |
| `scan_code` | string / null | Internal label barcode. Used when item has no manufacturer barcode |
| `manufacturer_barcode` | string / null | EAN/UPC. **Nullable** — not all items have one |
| `unit_of_measure` | string | "each", "pack", "case" etc. Required |
| `pack_qty` | integer / null | Units per pack. Null until measured |
| `pack_qty_confirmed` | boolean | **Always false after import.** Must be ticked manually by a human after physical count |
| `is_active` | boolean | Soft-disable flag |

**Key constraint:** `internal_sku` is unique within a client. Null manufacturer barcodes do not conflict with each other (SQLite and MySQL both allow multiple NULLs in a unique index).

#### `shopify_product_variants`
Maps Shopify variant GIDs to WMS products.

| Column | Type | Notes |
|---|---|---|
| `variant_gid` | string | `gid://shopify/ProductVariant/…`. Unique per `shop_domain` |
| `fulfilment_client_id` | bigint | A variant can never be assigned to a different client |
| `product_id` | bigint / null | Null until mapped |
| `source_sku` | string / null | Raw SKU from import file |
| `source_barcode` | string / null | Raw barcode from import file |
| `mapping_status` | enum | `unmapped` / `pending_review` / `mapped` / `exception` |
| `provenance` | enum | `csv_import` / `manual` — **manual mappings are never overwritten by a later import** |
| `exception_reason` | text / null | Why the variant is in exception status |

#### `product_imports`
Audit record of every CSV import attempt.

| Column | Type | Notes |
|---|---|---|
| `import_type` | enum | `products` / `variant_mappings` |
| `status` | enum | `preview` / `validated` / `confirmed` / `failed` / `partial` |
| `column_mapping` | JSON | User's column assignment choices, stored for audit |
| `validation_report` | JSON | Row-level errors and warnings |
| `imported_rows` / `skipped_rows` / `error_rows` | integer | Summary counts |
| `confirmed_at` | timestamp | When the user clicked Confirm Import |

---

### 2.2 Authorization Rules

| Action | Admin | Supervisor | Operator |
|---|---|---|---|
| View product list | ✅ | ✅ | ✅ |
| View product detail | ✅ | ✅ | ✅ |
| Create product | ✅ | ✅ | ❌ |
| Edit product | ✅ | ✅ | ❌ |
| Delete product | ✅ | ❌ | ❌ |
| Import CSV | ✅ | ✅ | ❌ |
| View variant mappings | ✅ | ✅ | ✅ |
| Map variant manually | ✅ | ✅ | ❌ |

All screens additionally enforce client scoping — a user from Client A cannot see or edit Client B's products, variants, or imports, regardless of role.

---

### 2.3 Key Business Rules Enforced in Code

1. **Pack quantity is never auto-confirmed.** Whether the CSV contains a pack_qty value or not, `pack_qty_confirmed` is always written as `false` by the import. The product list and picker screens display `"25 × pack (unverified)"` until a human ticks the confirmation checkbox after a physical count.

2. **Manual mappings are never overwritten by a CSV import.** If a variant has been manually mapped (provenance = `manual`), any future CSV import that includes the same variant GID will skip it and record a `skipped_rows` count. The manual mapping is preserved exactly as set.

3. **Conflicting barcodes go to quarantine.** If an import row contains a manufacturer barcode that already belongs to a different SKU within the same client, the row is rejected with an error — it is not silently merged or updated.

4. **Ambiguous SKU/barcode matches go to the exception list.** If a variant GID's source_sku matches more than one WMS product (or a barcode matches more than one), the variant is flagged as `exception` with a reason, and it appears in the exception banner on the mapping screen.

5. **Cross-client variant assignment is blocked at the database level.** A variant GID is unique per shop_domain; attempting to assign it to a different client raises an error.

6. **Import retries are idempotent.** Uploading the same CSV a second time creates a new `product_imports` audit record but does not create duplicate products or mappings — it updates existing rows using `updateOrCreate` keyed on `(client, internal_sku)` for products and `(shop_domain, variant_gid)` for variants.

---

### 2.4 Screens Built

| Screen | URL | Who Can Access |
|---|---|---|
| Product list | `/products` | All roles |
| New product | `/products/create` | Admin, Supervisor |
| Edit product | `/products/{id}/edit` | Admin, Supervisor |
| Variant mappings | `/products/mappings` | All roles (edit: Admin, Supervisor) |
| CSV import wizard | `/products/import` | Admin, Supervisor |

**The CSV import wizard is a 5-step process:**
1. Upload file and choose import type (products or variant mappings)
2. Map CSV columns to WMS fields (auto-suggested, user-adjustable)
3. Preview — see a full validation report with row-level errors and warnings before any data is written
4. Confirm — a deliberate "Confirm Import" button that actually writes to the database
5. Done — summary of imported / skipped / errored rows

**No data is written to the database until Step 4.**

---

## 3. Test Results

**Database engine:** SQLite `:memory:` (same engine as production — no partial-index syntax used)

```
Tests: 15 total
  Passed: 15
  Failed: 0
```

| Test | What It Proves |
|---|---|
| `product global scope restricts queries to authenticated user client` | Client A user cannot see Client B products |
| `product policy denies create for operator` | Operators are read-only |
| `product policy allows create for supervisor` | Supervisors can create |
| `product policy allows create for admin` | Admins can create |
| `same internal_sku is rejected for same client` | Duplicate SKU constraint works |
| `same internal_sku is allowed for different clients` | SKUs are client-scoped, not global |
| `multiple products with null manufacturer barcode are allowed` | No EAN required; nulls don't conflict |
| `csv import does not overwrite a manual variant mapping` | Manual override preserved |
| `retrying the same products csv does not create duplicate products` | Idempotent import |
| `conflicting manufacturer barcode is quarantined not silently merged` | Conflict quarantine |
| `imported pack quantity is always marked unverified (item 4504 scenario)` | Pack safety — confirmed=false, label says "unverified" |
| + 4 original foundation tests | Admin/foundation, operator 403, cross-client 404, throttle 429 |

---

## 4. Proposed Production Migration Steps

**These steps must not be run until Craig and Paul have reviewed this document and approved the migration.**

### Pre-migration checklist
- [ ] Take a full SQLite backup: `cp database/database.sqlite backups/database_$(date +%Y%m%d).sqlite`
- [ ] Confirm the branch has been reviewed and merged to `main`, or deploy from the feature branch explicitly
- [ ] Run `php artisan migrate --pretend` and review the SQL before executing

### Migration commands (on the server)
```bash
cd /home/mptportco/repositories/NeoNailWMS

# Pull the branch (or main after merge)
git fetch origin
git checkout feature/catalogue-and-mapping

# Review what will run — read this output before proceeding
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --pretend

# Execute
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate --force

# Re-cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan config:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan route:cache
/opt/cpanel/ea-php83/root/usr/bin/php artisan view:clear
```

### Rollback (if needed)
```bash
# Rolls back the 3 Phase 2 migrations (products, variants, imports)
/opt/cpanel/ea-php83/root/usr/bin/php artisan migrate:rollback --step=3

# Restore from backup if data was written
cp backups/database_YYYYMMDD.sqlite database/database.sqlite
```

The Phase 1 migrations (users, warehouses, locations etc.) are **not** affected by the rollback — `--step=3` only removes the 3 new tables.

---

## 5. Foundation Acceptance Checks (still passing on this branch)

| Check | Status |
|---|---|
| `GET /admin/foundation` → 200, shows app name and admin email | ✅ |
| `GET /warehouses/1` → 404 (global scope working, no warehouses) | ✅ |
| Operator → 403 on `/admin/foundation` | ✅ |
| Cross-client warehouse access → 404 | ✅ |
| Login throttle → 429 after 6 failed attempts | ✅ |
| Login and session working on live site | ✅ |

---

## 6. Unresolved NeoNail Questions (Paul to Answer Before Catalogue Import)

These questions must be answered before a real NeoNail product catalogue is imported. The system is built to handle all cases safely, but the answers determine how the CSV column mapping will be set up and which items will land in the exception list.

### Question 1 — Item 4504 Nail Files ⚠ HIGH PRIORITY

- What is the actual pack quantity? (How many individual nail files are in one pack?)
- Has this been physically counted and verified, or is it an estimate?
- Does each individual nail file have its own barcode, or only the outer box?
- Is there a manufacturer EAN on the box, or does the warehouse use an internal label only?

**Why this matters:** The system imports `pack_qty` as unverified. Until Paul or Craig ticks "Pack quantity physically verified" on the product form, the system will never treat one pack as N individual items. This is correct behaviour — but the physical count needs to happen before the WMS can be used for stock calculations on this item.

### Question 2 — Which Items Are Packs / Cases

For each product in the NeoNail catalogue:
- Is it sold as an individual unit, a pack, or a case?
- If it is a pack, what is the verified count?

The `unit_of_measure` and `pack_qty` fields need to be correctly set for the WMS to count stock accurately.

### Question 3 — SKU Format

- Is the `internal_sku` used in the WMS the same value as the Shopify SKU field?
- Or is the WMS SKU a separate internal code?

If they match exactly, the auto-mapping between Shopify variants and WMS products will work automatically. If they are different, a translation step or manual mapping will be required for each variant.

### Question 4 — Manufacturer Barcodes

- Which NeoNail products have an EAN/UPC manufacturer barcode?
- Are any barcodes shared across sizes or colours (e.g. does one EAN appear on a red and a pink version of the same product)?

**Why this matters:** If the same EAN appears on two Shopify variants, the import will flag both as `exception` (ambiguous barcode match) and they will need to be mapped manually.

### Question 5 — Shopify Shop Domain

- What is the exact Shopify shop domain for NeoNail UK?  
  (Expected format: `neonail-uk.myshopify.com`)

This is stored as `shop_domain` in the variant mapping table. The variant GID is unique per shop domain, so the exact value matters.

### Question 6 — Shopify Export Column Headers

When Paul exports the product CSV from Shopify admin, what are the exact column header names?

The import wizard will auto-suggest matches, but if the Shopify headers differ from the WMS field names, Paul will need to use the column-mapping step to assign them. A sample export of 5–10 products would let us pre-configure the mapping.

---

## 7. What Is NOT in This Phase (Boundary)

The following have deliberately not been built and must not be added until Phase 3 is approved:

- Stock ledger / inventory quantities
- Goods-in (inbound receipt) workflows
- Order picking or despatch
- Live Shopify API connection or webhook
- Sendcloud label generation
- Any live import of NeoNail's full catalogue on the production server
- Changes to production Shopify stock levels

---

## 8. Next Steps (after Paul & Craig approve)

1. Paul answers the questions in Section 6
2. Craig reviews the migration plan in Section 4, takes a backup, and runs the migration on the production server
3. Dean deploys the branch to production and verifies the screens load
4. Paul does a test CSV import with a small sample file (5–10 products) using the import wizard
5. Craig and Paul review the product list, mapping screen, and exception list
6. If satisfied, Phase 3 (stock ledger, goods-in) can begin

---

*Document generated by Kilo AI on 24 September 2026*  
*Repository: https://github.com/DeanoUk87/NeoNailWMS*
