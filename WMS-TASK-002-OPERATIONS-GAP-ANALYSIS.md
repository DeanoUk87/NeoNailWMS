# WMS TASK 002 — Operational Workflow Discovery & Gap Analysis

**Project:** MP Transport Warehouse Management System  
**Environment:** https://nnuk.wms.mp-transport.co.uk  
**Branch assessed:** `feature/catalogue-and-mapping` (commit `72861bc`)  
**Date:** 3 November 2026  
**Status:** Assessment complete — awaiting business-owner decisions before development continues  
**Restriction:** No code was changed during this assessment.

---

## 1. Existing System Inventory

### 1.1 Modules Fully Implemented

| Module | What it does | Where |
|---|---|---|
| **Authentication** | Login, logout, password reset, 2FA, passkeys | Fortify + Livewire starter kit |
| **Multi-client tenancy** | Every query scoped to `fulfilment_client_id` at Eloquent level | `FulfilmentClientScope`, `ProductPolicy`, `WarehousePolicy` |
| **User management** | Three roles (admin, supervisor, operator) with server-side enforcement | `User` model, `AdminFoundationController` |
| **Fulfilment clients** | Named client records with slug and active flag | `FulfilmentClient` model + migration |
| **Warehouses** | Named warehouse records scoped to a client | `Warehouse` model + migration |
| **Locations** | Bin/shelf records with barcode, name, type, active flag | `Location` model + migration |
| **Product catalogue** | SKU, name, scan code, manufacturer barcode (nullable), UOM, pack qty with verified flag | `Product` model + migration |
| **CSV product import** | 5-step wizard: upload → column map → validate → confirm → audit record | `ImportWizard`, `ProductImportService` |
| **Shopify variant mapping** | Variant GID → WMS product mapping with provenance (manual/csv), exception queue | `ShopifyProductVariant` model |
| **Import audit log** | Every import attempt recorded with column mapping, row counts, error report | `ProductImport` model |
| **Admin foundation check** | Role-gated health check route | `AdminFoundationController` |

### 1.2 Modules Partially Implemented (Schema Only — No UI or Logic)

| Module | What exists | What is missing |
|---|---|---|
| **Warehouses** | `warehouses` table with `name`, `fulfilment_client_id` | No create/edit UI, no listing screen, no management workflow |
| **Locations** | `locations` table with `barcode`, `name`, `location_type`, `is_active` | No create/edit UI, no barcode printing, no capacity tracking |

> **Note:** Warehouses and locations have a route (`GET /warehouses/{warehouse}`) that shows a warehouse name and location count, but this is a test scaffold only. It has no operational usefulness and is not linked from the navigation.

### 1.3 Functionality Not Yet Developed (No Code Exists)

The following have zero implementation — no models, no migrations, no routes, no UI:

- Stock ledger (quantity on hand per product per location)
- Goods-in / inbound receiving
- Batch and expiry date tracking
- Order management (manual or Shopify)
- Pick lists and picking workflow
- Packing verification
- Dispatch and courier integration (Sendcloud)
- Returns processing
- Stock adjustments and write-offs
- Quarantine management
- Barcode scanner integration
- Reports and dashboards

### 1.4 User Roles and Access Permissions (Currently Implemented)

| Permission | Admin | Supervisor | Operator |
|---|---|---|---|
| Log in | ✅ | ✅ | ✅ |
| View products | ✅ | ✅ | ✅ |
| Create / edit products | ✅ | ✅ | ❌ |
| Delete products | ✅ | ❌ | ❌ |
| Import CSV | ✅ | ✅ | ❌ |
| View variant mappings | ✅ | ✅ | ✅ |
| Resolve mapping exceptions | ✅ | ✅ | ❌ |
| Access /admin/foundation | ✅ | ❌ | ❌ |
| View warehouses (test route only) | ✅ | ✅ | ✅ |
| Manage users | ❌ (Tinker only) | ❌ | ❌ |
| Any stock operation | ❌ | ❌ | ❌ |
| Any order operation | ❌ | ❌ | ❌ |

### 1.5 Existing Integrations and Automation

| Integration | Status | Notes |
|---|---|---|
| Shopify | Partial — mapping table only | Variant GIDs can be imported via CSV. No live API connection. No order pull. No stock push. |
| Sendcloud | None | Not started. No credentials, no API client, no label generation. |
| Barcode scanners | None | No scanner input handling, no mobile-optimised UI, no WebSocket or USB HID support. |
| Email (SMTP) | Partial — log driver only | Password reset emails configured but mail server not set up. |

### 1.6 Known Limitations and Incomplete Workflows

1. **No stock exists in the system.** The product catalogue holds SKU definitions only. There are no quantity records, no location assignments, no batch records.
2. **The warehouse and location tables are empty in production.** No warehouse or location has been created via the UI (no UI exists for this).
3. **The operator role has no useful screens.** Operators can view the product list but cannot perform any warehouse operation.
4. **The dashboard shows placeholder grey boxes only.** No live data is displayed.
5. **User provisioning requires Tinker.** There is no admin UI to create or manage users.
6. **Pack quantities are unverified for all demo products.** Including item 4504 (Nail Files) which Paul has flagged for physical verification.

---

## 2. End-to-End Warehouse Process Maps

For each process, the table shows what the current system can do, what it cannot do, and what a complete process requires.

### Process 1 — Product Creation and Identification

**Physical steps required:**
1. Receive product specification (name, SKU, EAN if available, UOM, pack size).
2. Create product record in WMS.
3. Print internal label if no manufacturer barcode exists.
4. Physically verify pack quantities before confirming.

**Current system capability:**

| Step | Can the system do it? | Notes |
|---|---|---|
| Manual product creation | ✅ Fully implemented | `/products/create` form — admin/supervisor |
| CSV bulk import | ✅ Fully implemented | 5-step wizard with validation |
| Nullable manufacturer barcode | ✅ | Deliberate design for items with no EAN |
| Internal scan code field | ✅ | For internal label barcodes |
| Pack qty with unverified flag | ✅ | `pack_qty_confirmed` must be set manually |
| Duplicate barcode detection | ✅ | Quarantines conflicting imports |
| Unknown product handling | ✅ | Exception queue in variant mappings |
| Print internal label | ❌ | No label printing capability |
| Barcode scanner to look up product | ❌ | No scanner input |

**Missing:** Label printing; scanner lookup.

---

### Process 2 — Goods Receiving

**Physical steps required by a warehouse operator:**
1. Delivery vehicle arrives; operator checks paperwork (delivery note or PO).
2. Operator scans or reads each product's barcode.
3. System looks up product by barcode → confirms identity.
4. Operator counts units, records quantity received.
5. Operator records manufacturer batch number and expiry date (critical for cosmetics/gels).
6. Operator records condition (good, damaged, short).
7. System creates a goods-in record linked to product, batch, quantity, location.
8. Supervisor reviews and approves the receipt.
9. Stock quantities update on approval.

**Current system capability:**

| Step | Can the system do it? | Notes |
|---|---|---|
| Record an inbound delivery | ❌ | No goods-in model, table, screen or route |
| Scan a barcode to identify a product | ❌ | No scanner support; no barcode lookup endpoint |
| Record quantity received | ❌ | No stock ledger |
| Record batch number | ❌ | No batch/lot tracking field anywhere |
| Record expiry date | ❌ | No expiry date field anywhere |
| Record delivery condition (damaged, short) | ❌ | No condition or discrepancy field |
| Link receipt to a purchase order | ❌ | No purchase order module |
| Supervisor approval of receipt | ❌ | No approval workflow |
| Auto-update stock on approval | ❌ | No stock ledger to update |
| Handle unidentified products | ❌ | No quarantine stock record |

**Assessment:** Goods receiving is entirely absent. This is the entry point for all stock into the system. Without it, the WMS cannot record any physical stock.

---

### Process 3 — Storage and Bin Allocation

**Physical steps required:**
1. After receiving, operator moves stock to a storage location.
2. System records which product (and batch) is in which bin, in what quantity.
3. Bins are identified by barcode label on the shelf.
4. System warns if a bin already contains a different batch of the same product (FEFO risk).
5. System warns if a bin is at or over capacity.

**Current system capability:**

| Step | Can the system do it? | Notes |
|---|---|---|
| Warehouse record exists | ✅ (schema) | Table exists but no UI; production has no warehouses created |
| Location/bin record exists | ✅ (schema) | Table exists: barcode, name, location_type, is_active |
| Create warehouse via UI | ❌ | No form, no route |
| Create location/bin via UI | ❌ | No form, no route |
| Print location barcode label | ❌ | No label printing |
| Assign stock to a bin | ❌ | No stock_locations table or model |
| Track quantity per bin | ❌ | No stock ledger |
| Track batch per bin | ❌ | No batch model |
| Warn on bin capacity | ❌ | No capacity field on locations |
| Warn on mixed batches | ❌ | No batch tracking |
| Scanner: scan bin then scan product | ❌ | No scanner workflow |

**Assessment:** Location data structures exist in the schema but are empty and unmanaged. No stock can be allocated to any bin.

---

### Process 4 — Stock Rotation and Traceability

**Physical requirements:**
- FEFO (First Expired First Out) — cosmetics and gel products have expiry dates. The oldest expiry must be picked first.
- Batches must be traceable (recall capability).
- Expired or near-expiry stock must be flagged automatically.
- Quarantined stock must be separated and locked from picking.

**Current system capability:**

| Step | Can the system do it? | Notes |
|---|---|---|
| Batch number field | ❌ | No batch model or field exists anywhere |
| Expiry date field | ❌ | No expiry date field exists anywhere |
| FEFO picking rule | ❌ | No batch logic to implement FEFO |
| Expired stock flag | ❌ | No expiry tracking |
| Quarantine status | ❌ | No quarantine stock record (variant mapping exception is unrelated) |
| Stock recall by batch | ❌ | No batch tracking means no recall capability |
| Near-expiry alerts | ❌ | No alerting system |

**Assessment:** This is a critical gap for a cosmetics/gel warehouse. UK cosmetics regulations (Regulation (EC) No 1223/2009 as retained in UK law) require batch traceability for cosmetic products. An operator cannot legally receive, store or dispatch gel products without recording batch numbers and expiry dates.

---

### Process 5 — Order Processing

**Physical requirements:**
- Order arrives from Shopify or is created manually.
- System checks stock availability.
- Stock is reserved (soft-allocated) against the order.
- If stock is insufficient, supervisor is alerted.
- Order is released to picking when stock is confirmed available.

**Current system capability:**

| Step | Can the system do it? | Notes |
|---|---|---|
| Receive Shopify order | ❌ | No Shopify API connection, no webhook, no order model |
| Create manual order | ❌ | No order model or screen |
| Check stock availability | ❌ | No stock ledger |
| Reserve (soft-allocate) stock | ❌ | No reservation mechanism |
| Alert on stock shortage | ❌ | No alerting |
| Release to picking | ❌ | No pick list generation |

**Assessment:** Order processing is entirely absent.

---

### Process 6 — Picking and Packing

**Physical requirements:**
- Operator receives a pick list (paper or screen/scanner).
- List shows: product, bin location, batch, quantity.
- Operator navigates to bin, scans bin barcode to confirm location.
- Operator scans product barcode to confirm correct item.
- Operator counts and confirms quantity.
- System deducts stock from the bin.
- Operator packs items and confirms packing complete.
- System generates packing list for parcel.

**Current system capability:**

| Step | Can the system do it? | Notes |
|---|---|---|
| Generate pick list | ❌ | No order → pick workflow |
| Display bin location on pick list | ❌ | No stock-location link |
| Display correct batch on pick list | ❌ | No batch tracking |
| Scanner: confirm bin scan | ❌ | No scanner integration |
| Scanner: confirm product scan | ❌ | No scanner integration |
| Deduct stock on confirmation | ❌ | No stock ledger |
| Packing verification | ❌ | No packing record |
| Packing list document | ❌ | No document generation |
| Handle incorrect scan | ❌ | No scan validation |
| Handle short pick | ❌ | No shortage workflow |

**Assessment:** Picking and packing are entirely absent. These are the core day-to-day activities of the warehouse operator role. No operator can currently perform their primary function.

---

### Process 7 — Dispatch

**Physical requirements:**
- Packed parcel is labelled with courier label (Sendcloud).
- Label includes recipient address from Shopify order.
- Tracking number is recorded against the order.
- Parcel is handed to courier.
- System confirms despatch and updates Shopify order status.

**Current system capability:**

| Step | Can the system do it? | Notes |
|---|---|---|
| Generate courier label (Sendcloud) | ❌ | No Sendcloud integration |
| Book courier collection | ❌ | No courier API |
| Record tracking number | ❌ | No tracking field, no order model |
| Confirm despatch | ❌ | No despatch record |
| Update Shopify order status | ❌ | No Shopify API |
| Handle label failure | ❌ | No label retry workflow |
| Handle missed collection | ❌ | No exception workflow |

**Assessment:** Dispatch is entirely absent.

---

### Process 8 — Returns and Stock Adjustments

**Physical requirements:**
- Returned parcel arrives.
- Operator inspects and classifies (resaleable, damaged, wrong item, expired).
- System records return against original order.
- Resaleable stock is returned to a bin.
- Damaged/expired stock is quarantined and written off.
- Stock adjustment is authorised by supervisor.
- Adjustment is audited.

**Current system capability:**

| Step | Can the system do it? | Notes |
|---|---|---|
| Record return | ❌ | No returns model |
| Classify return condition | ❌ | No condition field |
| Return stock to bin | ❌ | No stock ledger |
| Write-off damaged stock | ❌ | No write-off record |
| Quarantine stock | ❌ | No quarantine stock |
| Supervisor authorisation of adjustment | ❌ | No adjustment approval workflow |
| Audit trail of adjustments | ❌ | No stock movement audit |

**Assessment:** Returns and adjustments are entirely absent.

---

## 3. Operator / Software Responsibility Matrix

| Action | Physical (Staff) | Automatic (System) | Human Decision | Exception |
|---|---|---|---|---|
| Create product record | Enter details or prepare CSV | Validate uniqueness, quarantine conflicts, flag unverified packs | Confirm import; verify pack qty physically | Ambiguous barcode → exception queue |
| Receive delivery | Unload, count, check condition | Look up product by barcode scan ❌ | Approve receipt ❌ | Short, damage, unknown product ❌ |
| Put stock away | Carry to bin, scan bin barcode ❌ | Record bin assignment ❌ | — | Bin full, wrong batch ❌ |
| Pick an order | Navigate to bin, scan product ❌ | Generate pick list ❌, deduct stock ❌ | — | Short pick, missing stock ❌ |
| Pack | Place in box, seal | Generate packing list ❌ | — | Wrong item detected ❌ |
| Dispatch | Hand to courier | Book Sendcloud label ❌, record tracking ❌ | — | Label failure, missed collection ❌ |
| Return | Inspect, classify | — | Approve return to stock / write-off ❌ | Damaged, expired, wrong item ❌ |
| Stock adjustment | — | — | Authorise adjustment ❌ | Discrepancy investigation ❌ |

**Key:** ❌ = not currently supported by the system. Every row marked ❌ requires a manual paper or spreadsheet workaround.

---

## 4. Exception and Recovery Matrix

| Exception | Detectable? | Recoverable? | Current workaround |
|---|---|---|---|
| Barcode does not match any product | ❌ | ❌ | Paper note, manual lookup |
| Short delivery (fewer units than expected) | ❌ | ❌ | Spreadsheet |
| Damaged stock on arrival | ❌ | ❌ | Physical segregation only |
| Bin is full when putting away | ❌ | ❌ | Operator judgement |
| Two batches of same SKU in one bin | ❌ | ❌ | Manual label on bin |
| Oldest batch cannot be located | ❌ | ❌ | Manual search |
| Wrong product picked | ❌ | ❌ | Repack, no system record |
| Stock count discrepancy | ❌ | ❌ | Spreadsheet adjustment |
| Expired stock in picking location | ❌ | ❌ | Operator must visually check |
| Sendcloud label failure | ❌ | ❌ | Retry manually outside system |
| Parcel returned undelivered | ❌ | ❌ | Manual receipt, no system record |
| Scanner fails mid-pick | ❌ | ❌ | Paper pick list (not generated) |
| Network outage during receiving | ❌ | ❌ | No offline mode |

**Assessment:** None of the above exceptions can currently be managed within the WMS. All require manual workarounds outside the system, creating audit gaps and traceability risks.

---

## 5. Operational Gap Register

### Critical Gaps (prevent safe, traceable or legal operation)

| # | Requirement | Current capability | Gap | Severity | Recommended action |
|---|---|---|---|---|---|
| G-01 | Record goods received with quantity and condition | None | No goods-in module, no stock ledger | **Critical** | Build goods-in workflow as Phase 3 priority |
| G-02 | Record batch number during receiving | None | No batch field anywhere in schema | **Critical** | Add `batches` table before any stock entry |
| G-03 | Record expiry date during receiving | None | No expiry date field anywhere | **Critical** | Add `expiry_date` to batch record |
| G-04 | FEFO picking rule enforcement | None | No batch/expiry tracking → FEFO impossible | **Critical** | Cannot implement until G-02/G-03 resolved |
| G-05 | Traceability of stock by batch (recall capability) | None | No batch model → no recall possible | **Critical** | Legal requirement for cosmetics distribution |
| G-06 | Stock ledger (quantity on hand per location) | None | No `stock_levels` table | **Critical** | Core of any WMS — needed before any other stock operation |
| G-07 | Operator can perform any warehouse task | None | Operator role has no operational screens | **Critical** | All warehouse operations need building |

### High Gaps (significant manual workaround or operational risk)

| # | Requirement | Current capability | Gap | Severity | Recommended action |
|---|---|---|---|---|---|
| G-08 | Warehouse and location management UI | Schema exists | No create/edit screens, production locations empty | **High** | Build location management before goods-in |
| G-09 | Barcode scanner input | None | No scanner support, no mobile UI | **High** | Needed for accurate receiving and picking |
| G-10 | Order management (Shopify or manual) | None | No order model or screen | **High** | Phase 3/4 — after stock ledger |
| G-11 | Pick list generation | None | No workflow exists | **High** | Depends on G-06, G-10 |
| G-12 | Sendcloud courier integration | None | No API client, no credentials | **High** | Phase 4 |
| G-13 | Quarantine stock management | None | No quarantine stock record | **High** | Needed for damaged/expired stock control |
| G-14 | User management UI | None | Admin must use Tinker to create users | **High** | Basic operational requirement |
| G-15 | Returns processing | None | No returns model | **High** | Phase 4/5 |
| G-16 | Stock adjustment with audit trail | None | No adjustment record | **High** | Required for stock accuracy and audit |

### Medium Gaps

| # | Requirement | Current capability | Gap | Severity | Recommended action |
|---|---|---|---|---|---|
| G-17 | Dashboard with live stock data | Placeholder only | No real data displayed | **Medium** | Build after stock ledger |
| G-18 | Internal label printing | None | No label generation | **Medium** | Needed for products without EAN |
| G-19 | Packing verification | None | No packing record | **Medium** | Phase 4 |
| G-20 | Near-expiry stock alerts | None | No alerting system | **Medium** | Phase 4 |
| G-21 | Purchase order management | None | No PO model | **Medium** | Useful for planned receiving |
| G-22 | Reports (stock on hand, movements, ageing) | None | No reporting module | **Medium** | Phase 5 |

### Low Gaps

| # | Requirement | Current capability | Gap | Severity | Recommended action |
|---|---|---|---|---|---|
| G-23 | Offline / degraded mode for scanner failures | None | Full network dependency | **Low** | Phase 5 |
| G-24 | Mobile-optimised operator UI | None | Desktop-oriented Flux UI | **Low** | Review after scanner integration |
| G-25 | Email notifications (low stock, receipts) | Partial (log driver) | SMTP not configured | **Low** | Configure mail server when needed |

---

## 6. Scenario Test Findings

Each scenario was assessed against the current codebase. No test data was altered in production.

| # | Scenario | Can it be completed? | Reason / Workaround required |
|---|---|---|---|
| S-01 | Straightforward delivery received, stored, picked, dispatched | ❌ Blocked | G-01, G-06, G-10, G-11, G-12 all absent |
| S-02 | Mixed pallet with multiple batches and expiry dates | ❌ Blocked | G-02, G-03 — no batch or expiry fields anywhere |
| S-03 | Product without readable barcode | ⚠ Partial | Product can be created with scan_code field; but cannot be looked up by scanner during receiving |
| S-04 | Two batches of same SKU in different bins | ❌ Blocked | G-02 — no batch model; system cannot distinguish batches |
| S-05 | Order where oldest batch cannot be found | ❌ Blocked | G-04 — no FEFO, no batch tracking, no pick exception workflow |
| S-06 | Damaged or quarantined stock | ❌ Blocked | G-13 — no quarantine stock record |
| S-07 | Incorrect scan or quantity entry during pick | ❌ Blocked | G-09, G-11 — no scanner, no pick workflow |
| S-08 | Partial order and stock discrepancy | ❌ Blocked | G-10, G-16 — no order or adjustment workflow |
| S-09 | Scanner, network or printer failure | ❌ Blocked | G-23 — no offline mode; no pick list paper alternative auto-generated |
| S-10 | Product CSV import with barcode conflict | ✅ Completed | DUPE-BC quarantined; 3 valid rows imported; audit record created |
| S-11 | Manual resolution of variant mapping exception | ✅ Completed | Inline resolve panel; manual lock applied; persists on refresh |
| S-12 | Operator cannot access admin route | ✅ Completed | `/admin/foundation` returns 403 for operator role |

**Only scenarios S-10, S-11 and S-12 can currently be completed within the system.** All warehouse operational scenarios are blocked.

---

## 7. Recommended Development Priorities

The following is a suggested phased build order based on operational dependency. No phase should begin until business owners have reviewed and approved it.

### Phase 3 — Stock Foundation (prerequisite for everything)

1. **Batch model** — `batches` table with `product_id`, `batch_number`, `expiry_date`, `received_at`, `notes`. This unblocks FEFO, traceability and recall.
2. **Stock ledger** — `stock_entries` table recording quantity per (product, batch, location). This is the central data structure for all stock operations.
3. **Location management UI** — Create/edit warehouse and bin records via the admin screens.
4. **User management UI** — Admin screen to create, edit and deactivate users without Tinker.

### Phase 4 — Receiving and Putaway

5. **Goods-in module** — Inbound delivery record, line items, batch capture, condition flags, supervisor approval, stock ledger update on approval.
6. **Putaway workflow** — Assign received stock to bin locations. Scanner-optimised flow.
7. **Internal label printing** — Generate barcode labels for products and bins.
8. **Quarantine module** — Separate stock status for damaged, expired, investigation-hold.

### Phase 5 — Orders and Picking

9. **Order management** — Shopify webhook integration (or manual order creation). Stock reservation on order creation.
10. **Pick list generation** — FEFO-ordered pick list per order, with bin locations and batch numbers.
11. **Picking workflow** — Scanner-based confirm bin → confirm product → confirm quantity. Short pick exception.
12. **Packing verification** — Confirm packed items match pick list before despatch.

### Phase 6 — Dispatch and Integration

13. **Sendcloud integration** — Label generation, booking, tracking number recording.
14. **Shopify stock sync** — Update Shopify inventory levels when stock changes.
15. **Dispatch confirmation** — Mark order despatched, record tracking number.

### Phase 7 — Returns, Adjustments and Reporting

16. **Returns processing** — Link return to original order, condition classification, stock re-entry or write-off.
17. **Stock adjustment workflow** — Supervisor-authorised adjustment with full audit trail.
18. **Reporting** — Stock on hand, stock movement history, expiry report, picking performance, goods-in history.

---

## 8. Proposed Structure for Illustrated Operating Manuals

The following manuals will be required. **None should be produced until the corresponding workflow has been operationally validated.**

| Manual | Status | Trigger for production |
|---|---|---|
| MAN-01: Logging in and account management | Ready to draft | ✅ Auth fully implemented |
| MAN-02: Product catalogue — creating and editing products | Ready to draft | ✅ Product screens implemented |
| MAN-03: CSV product import | Ready to draft | ✅ Import wizard implemented |
| MAN-04: Shopify variant mapping and exception resolution | Ready to draft | ✅ Mapping screens implemented |
| MAN-05: Warehouse and location setup | Not ready | ❌ Awaiting Phase 3 location UI |
| MAN-06: Receiving a delivery | Not ready | ❌ Awaiting Phase 4 goods-in |
| MAN-07: Putting stock away | Not ready | ❌ Awaiting Phase 4 putaway |
| MAN-08: Processing a pick list | Not ready | ❌ Awaiting Phase 5 picking |
| MAN-09: Packing and dispatch | Not ready | ❌ Awaiting Phase 6 |
| MAN-10: Returns and stock adjustments | Not ready | ❌ Awaiting Phase 7 |
| MAN-11: Reports and stock takes | Not ready | ❌ Awaiting Phase 7 |
| MAN-12: Administrator guide (users, clients, settings) | Not ready | ❌ Awaiting user management UI |

**Manuals MAN-01 through MAN-04 can be produced now** using the existing live screens. They are suitable for training admin/supervisor staff on the catalogue and mapping tools ahead of the real NeoNail catalogue import.

---

## 9. Questions Requiring Business-Owner Decisions

The following cannot be resolved by technical assessment alone. Each requires a decision from Craig and/or Paul before the corresponding development phase begins.

**Q-01: Batch and expiry tracking scope**
Cosmetics and gel products distributed by MP Transport may be subject to UK Cosmetics Regulation batch traceability requirements. Do Craig and Paul confirm that batch numbers and expiry dates must be recorded for all goods received? If yes, this is a prerequisite for Phase 4 and cannot be deferred.

**Q-02: Item 4504 (Nail Files) pack size**
The pack quantity for SKU 4504 is currently recorded as 25 in the demo data but marked unverified. Before the real NeoNail catalogue is imported, Paul has committed to physically verifying this. Has that verification been done? What is the confirmed count?

**Q-03: Scanner hardware**
Will warehouse operators use handheld barcode scanners, mobile phones with a camera app, or tablets with an integrated camera? The answer determines whether the WMS needs a native app, a PWA, or can rely on a keyboard-wedge USB/Bluetooth scanner input into a browser field. This affects Phase 4 design significantly.

**Q-04: Shopify connection timing**
At what point does a live Shopify API connection need to be established? Specifically: is the Shopify stock level expected to update in real time as stock is received and despatched, or is a nightly batch sync acceptable? This determines whether Phase 5 needs a webhook listener or a scheduled job.

**Q-05: Multiple clients (Fuzion Gel)**
The system is built for multiple fulfilment clients. When is Fuzion Gel expected to be onboarded? Will Fuzion Gel share the same warehouse locations as NeoNail, or have separate physical zones? Shared zones require the stock ledger to enforce strict per-client segregation at the bin level.

**Q-06: Purchase order management**
Does MP Transport receive products against purchase orders raised in an external accounting system (e.g. Xero, Sage)? If yes, should the WMS link goods receipts to external PO numbers for reconciliation, or is a simpler delivery note reference sufficient?

**Q-07: Sendcloud account**
Does MP Transport already have a Sendcloud account and API credentials? Which carriers and services are used? This determines the Sendcloud integration scope.

**Q-08: User provisioning responsibility**
Who is responsible for creating and managing WMS user accounts? Is this Dean (via Tinker currently), or should Craig or Paul be able to manage users through the admin UI once Phase 3 user management is built?

**Q-09: Real NeoNail catalogue import readiness**
The real NeoNail product catalogue has not yet been imported. Paul has stated pack quantities must be verified first. Is there a target date for the physical verification? And does Paul have a Shopify product export file ready for the variant mapping import?

---

## 10. Assessment Summary

| Category | Assessment |
|---|---|
| **Authentication and security** | Fully implemented and verified |
| **Multi-client tenancy** | Fully implemented and verified |
| **Product catalogue** | Implemented — ready for real data once pack sizes verified |
| **Shopify variant mapping** | Implemented — awaiting Shopify export file |
| **Stock ledger** | Not started — critical prerequisite |
| **Batch/expiry tracking** | Not started — critical for cosmetics compliance |
| **Goods receiving** | Not started |
| **Location management** | Schema only — no UI, no data |
| **Order management** | Not started |
| **Pick/pack/dispatch** | Not started |
| **Returns/adjustments** | Not started |
| **Overall operational readiness** | **Not operationally ready** — the system cannot support any warehouse operation beyond product data maintenance |

**The WMS is currently a product catalogue and tenant security foundation. It is not yet a warehouse management system in any operational sense. Phases 3 through 7 must be completed before the system can support a single physical warehouse operation.**

---

*Produced by Kilo AI — 3 November 2026*  
*No application code, database structure or production settings were changed during this assessment.*  
*Branch: `feature/catalogue-and-mapping` | Repository: https://github.com/DeanoUk87/NeoNailWMS*
