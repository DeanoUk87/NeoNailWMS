# WMS TASK 003A — Revised Goods Receiving & Putaway Operational Blueprint

**Project:** MP Transport Warehouse Management System  
**Supersedes:** WMS-TASK-003-RECEIVING-PUTAWAY-OPERATIONAL-BLUEPRINT.md  
**Date:** 3 November 2026  
**Status:** Design revision — awaiting approval before development  
**Code changes:** NOT AUTHORISED  
**STOP point:** Paul and Craig must review and approve this document before Phase 3 development begins.

---

## Change Log — Ten Corrections Applied

| # | Correction | Change made | Sections affected |
|---|---|---|---|
| C-01 | Exception-based supervisor approval | Routine lines proceed to putaway without supervisor gate. Only specific exception types require approval. Approval matrix defined. | §1G, §3 (new), §10 |
| C-02 | Stock availability and status control | Eight distinct stock states defined. Stock cannot be available or Shopify-visible until physically located in an eligible bin. Double-counting prevention explained. | §4, §8 |
| C-03 | Manufacturer batch identification | Internal MP Transport tracking IDs separated completely from manufacturer batch numbers. Cosmetic batches without a manufacturer number placed on hold, not released. Accessories handling defined. | §5, §8 |
| C-04 | Bin allocation priorities | Decision order revised: client/warehouse → storage conditions → stock condition → batch compatibility → capacity → existing location → alternatives → override. Batch mixing prohibited by default. | §6 |
| C-05 | Packaging units and quantity conversion | Pallet/carton/inner/individual receiving hierarchy defined. Unverified conversion quantities blocked from adding to saleable inventory. Nail Files 4504 scenario addressed explicitly. | §1B (revised), §5 |
| C-06 | Partial putaway across multiple bins | Full partial putaway workflow with quantity tracking per bin, resume support, and batch traceability per destination. Screen concepts added. | §6.5, §10 (P screens) |
| C-07 | Mistakes, interruptions and recovery | Expanded recovery matrix covering 11 failure types. Operator vs supervisor correction authority defined. Audit trail preservation on post-confirmation corrections. Duplicate submission prevention. | §7 |
| C-08 | Stock ledger and data integrity | Revised data structures aligned to physical operation. Immutable movement records. Balance calculation rules. Concurrency and rollback design. | §8 |
| C-09 | Physical control of goods awaiting action | Physical holding zones defined. Labelling standards for goods in each state. Overflow procedures. Physical and electronic state agreement. | §9 (new) |
| C-10 | Revised operator and supervisor screens | All screens updated to reflect corrections 1–9. Partial putaway screens added. Correction and resume screens added. Packaging quantity screens added. | §10 |

---

## How to Read This Document

- **Operator** = the person physically handling stock in the warehouse.
- **Supervisor** = the person who approves exceptions, reviews discrepancies and authorises unusual actions.
- **WMS** = the warehouse management software running on the handheld scanner or desktop.
- **[SCREEN]** = a proposed screen that does not yet exist in the software.
- **[SCAN]** = a scanner action — the device beeps on success and sounds an error tone on failure.
- **[AUTO]** = an action performed automatically by the system without operator input.
- **[HOLD]** = stock placed in a hold state that prevents picking or Shopify visibility.

All screen concepts in this document are design proposals only. None are implemented.

---

## Scenario Being Designed

A pallet arrives at MP Transport from NeoNail UK containing:

1. **Gel Polish Classic Red** — 48 individual bottles, all with EAN barcodes, single batch, good condition.
2. **Gel Polish Classic Red** — 12 bottles, same EAN, different batch number and earlier expiry date.
3. **Base Coat 15ml + Top Coat 15ml** — mixed outer carton containing 24 Base Coat and 24 Top Coat.
4. **Nail Files (SKU 4504)** — 3 outer boxes, unknown number of individual packs per box, no EAN, internal label only. Pack count unverified.
5. **Gel Polish Ballet Pink** — 12 bottles, 2 visibly damaged.
6. **Base Coat quantity discrepancy** — delivery note says 24 but only 20 are present.

This scenario exercises every condition the revised workflow must handle: two batches of the same SKU, mixed cartons, packaging units with unverified conversions, damaged goods, unreadable barcodes and a shortage.

---

## Section 1 — Delivery Arrival and Document Check

### 1A: Vehicle Arrival

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Delivery vehicle arrives.
Operator meets driver and
checks vehicle seals are
intact.

Operator opens the WMS            [SCREEN S1] Goods Receiving home.
on their scanner.                 Shows: [New Delivery] and recent
                                  deliveries in progress.

Operator taps [New Delivery].     [SCREEN S2] Delivery header.
                                  Fields: Supplier (dropdown),
                                  Delivery Note number (text/scan),
                                  Vehicle/driver ref (optional).

Operator selects supplier         [AUTO] WMS records: supplier_id,
"NeoNail UK" and enters           delivery_note_ref, arrived_at,
delivery note number.             received_by (logged-in user).

                                  [AUTO] If a purchase order exists
                                  for this supplier: WMS shows expected
                                  line summary for reference only.
                                  If no PO: delivery proceeds as
                                  "unplanned" — note added to record.

Operator photographs delivery     [OPTIONAL] Photo attached to delivery
note if device has camera.        record. See BD-07.

Operator taps [Start Receiving].  WMS creates delivery record with
                                  status: IN_PROGRESS.
                                  Delivery is now resumable by any
                                  authorised operator.
```

**Vehicle damage at arrival:** If seals are broken or vehicle is visibly damaged, operator taps "Vehicle condition issue" before starting. WMS records the flag. Supervisor is notified by push notification. Operator proceeds with receiving but all lines receive an automatic "vehicle damage noted" flag.

---

### 1B: Line-by-Line Receiving — Packaging Hierarchy First (C-05)

**Correction C-05 applied:** The original design went straight to product identity and quantity. This revision records what the operator physically has in hand — the packaging unit — before converting to saleable units.

The operator must identify:
1. What packaging unit they are holding (pallet / outer carton / inner pack / individual unit).
2. The product inside it.
3. The count at the packaging level.
4. The conversion to saleable units — only if the conversion is verified.

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Operator picks up the first       [SCREEN S3] Scan product.
item from the pallet.             "Scan a barcode or enter SKU."

                                  Packaging type prompt:
                                  ┌─────────────────────────────────┐
                                  │ What are you scanning?          │
                                  │ ○ Individual unit               │
                                  │ ○ Inner pack / sleeve           │
                                  │ ● Outer carton                  │
                                  │ ○ Pallet                        │
                                  └─────────────────────────────────┘

Operator selects "Outer carton"
and scans the carton barcode.     [SCAN] WMS identifies product.
```

**If the barcode identifies the product directly (e.g. EAN on a single bottle):**
WMS records packaging = individual unit.

**If the barcode is an outer carton code:**
WMS looks up the product associated with that carton barcode, or asks the operator to confirm the product inside.

**Mixed carton (Base Coat + Top Coat):**
```
Operator scans mixed carton.      WMS: "This carton contains multiple
                                  products. Scan or select each
                                  product separately."

Operator scans a Base Coat        WMS identifies: Base Coat 15ml.
bottle inside the carton.         "How many Base Coat in this carton?"
Operator counts: 24.              Operator enters: 24.

Operator scans a Top Coat         WMS identifies: Top Coat 15ml.
bottle inside the carton.         "How many Top Coat in this carton?"
Operator counts: 24.              Operator enters: 24.

                                  WMS records two separate lines:
                                  - Base Coat × 24 individual units
                                  - Top Coat × 24 individual units
                                  from one physical carton. Carton
                                  reference noted on both lines.
```

---

### 1C: Quantity Entry by Packaging Level

After product identification, the operator counts and records the quantity **at the packaging level they physically have**.

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Operator has 48 individual        [SCREEN S4a] Quantity.
bottles of Gel Polish Red.        Product: Gel Polish Classic Red
                                  Packaging: Individual unit
                                  Quantity received: [48]
                                  Unit: each
                                  [Next →]

Operator has 3 outer boxes        [SCREEN S4b] Quantity — unverified pack.
of Nail Files (no EAN,            Product: Nail Files (SKU 4504)
internal label only).             Packaging: Outer box
                                  Quantity received: [3]
                                  Unit: boxes

                                  ┌─────────────────────────────────┐
                                  │ ⚠ Pack conversion unverified    │
                                  │ Units per box: UNKNOWN          │
                                  │                                  │
                                  │ Do NOT enter an assumed total.  │
                                  │ Record boxes only.              │
                                  │                                  │
                                  │ [Record 3 boxes — count pending]│
                                  └─────────────────────────────────┘

                                  WMS records:
                                  - quantity_received_packaging = 3
                                  - packaging_unit = "box"
                                  - quantity_saleable = NULL
                                  - conversion_verified = false
                                  - conversion_pending_task created
                                    for supervisor

                                  Stock status for Nail Files:
                                  RECEIVED_UNVERIFIED — cannot be
                                  picked or shown in Shopify inventory
                                  until physical count is completed.
```

**No quantity from an unverified conversion is ever added to saleable inventory. Three boxes of Nail Files does not create 75 packs. It creates 3 boxes with a pending task to verify the pack count.**

---

### 1D: Batch and Expiry — Manufacturer Information Only (C-03)

**Correction C-03 applied:** The original design allowed the system to generate internal batch numbers as substitutes when no manufacturer batch was present. This is removed. The manufacturer's batch number is the only authentic batch identifier. An internal tracking reference is separate and must never be presented as a manufacturer batch.

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Operator reads the batch          [SCREEN S5] Batch Information.
number from the bottle or         ┌─────────────────────────────────┐
carton. It is printed as          │ Gel Polish Classic Red × 48     │
"Batch: A2026-047" and            │                                  │
"Best before: 12/2027".           │ Manufacturer batch number:      │
                                  │ [🔍 Scan label or type]        │
                                  │ [ A2026-047              ]      │
                                  │                                  │
                                  │ Expiry / best before:           │
                                  │ [DD/MM/YYYY]                    │
                                  │ [ 31 / 12 / 2027  ]            │
                                  │                                  │
                                  │ [No manufacturer batch on item] │
                                  └─────────────────────────────────┘

Operator enters batch number      [AUTO] WMS creates or matches a
and expiry date.                  batch record:
                                  - manufacturer_batch_number: A2026-047
                                  - expiry_date: 2027-12-31
                                  - batch_source: manufacturer
                                  - internal_tracking_ref: auto-assigned
                                    (MP-20261103-001) — used for
                                    internal warehouse operations only.
                                    Never shown as "batch number" to
                                    operators or customers.
```

**What "No manufacturer batch on item" means (cosmetics):**

For cosmetics and gel products, if the operator cannot find a manufacturer batch number:

```
                                  [SCREEN S5b] No manufacturer batch.
                                  ┌─────────────────────────────────┐
                                  │ ⚠ No manufacturer batch found   │
                                  │                                  │
                                  │ For cosmetic / gel products,    │
                                  │ a manufacturer batch is required │
                                  │ for traceability.               │
                                  │                                  │
                                  │ Options:                         │
                                  │ [Check carton again]            │
                                  │ [Place on hold — supervisor]    │
                                  │ [Confirm: this product does not │
                                  │  carry a manufacturer batch]    │
                                  └─────────────────────────────────┘
```

- **"Check carton again"** → operator re-examines. Batch number is often on the bottom of the bottle or inner side of the carton.
- **"Place on hold — supervisor"** → stock is received as HOLD_INVESTIGATION. Supervisor is notified. Stock cannot be picked until supervisor resolves.
- **"Confirm: no batch"** → only for products where Paul/Craig have confirmed in WMS settings that batch tracking does not apply (see BD-01). Selecting this for a cosmetic product without prior configuration requires supervisor approval.

**An internal MP Transport tracking reference is assigned automatically to every delivery line for internal warehouse operations. This number is used to track the stock within the warehouse. It is never presented to the operator as a "batch number" and is never shared with customers or used on labels that leave the warehouse.**

**Accessories (e.g. nail files, buffers, tools):**

Accessories for which batch tracking is not applicable (configured by admin) are exempt from the manufacturer batch prompt. The operator sees:

```
                                  [SCREEN S5c] Accessories — No batch.
                                  ┌─────────────────────────────────┐
                                  │ Nail Files (SKU 4504)           │
                                  │                                  │
                                  │ Batch tracking: Not applicable  │
                                  │ (configured by admin)           │
                                  │                                  │
                                  │ Internal tracking ref assigned: │
                                  │ MP-20261103-006 (auto)          │
                                  │                                  │
                                  │ [Continue]                      │
                                  └─────────────────────────────────┘
```

The internal tracking ref still exists for warehouse operations but is clearly labelled as such.

---

### 1E: Condition Recording

After batch entry, the operator records condition for each line.

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Ballet Pink: operator notices     [SCREEN S6] Condition.
2 bottles are cracked/leaking.    ┌─────────────────────────────────┐
                                  │ Gel Polish Ballet Pink × 12     │
                                  │ Batch E2026-012                 │
                                  │                                  │
                                  │ ● All items good                │
                                  │ ○ Some items damaged            │
                                  │ ○ All damaged / reject           │
                                  └─────────────────────────────────┘

Operator selects "Some damaged"   [SCREEN S6b] Damaged split.
                                  ┌─────────────────────────────────┐
                                  │ Of 12 bottles:                  │
                                  │ Damaged quantity: [2]           │
                                  │ Good quantity:   [10]           │
                                  │                                  │
                                  │ Damaged items action:           │
                                  │ ● Quarantine (hold for review)  │
                                  │ ○ Return to supplier            │
                                  │ ○ Dispose immediately           │
                                  └─────────────────────────────────┘

                                  WMS records:
                                  Line 5a: Ballet Pink × 10 — GOOD
                                          Status: awaiting putaway
                                          (after supervisor if exception)
                                  Line 5b: Ballet Pink × 2 — DAMAGED
                                          Status: QUARANTINE_PENDING
                                  [AUTO] Supervisor notification sent.
                                  [AUTO] Credit note flag raised.
```

---

### 1F: Quantity Discrepancy

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Operator counts Base Coat:        [SCREEN S4] Quantity entry.
only 20 bottles present.          Product: Base Coat 15ml
Delivery note says 24.            Quantity: [20]

                                  [AUTO] WMS compares to expected
                                  quantity (from delivery note or PO).
                                  Expected: 24  Entered: 20

                                  [SCREEN] Discrepancy alert:
                                  ┌─────────────────────────────────┐
                                  │ ⚠ Quantity discrepancy          │
                                  │ Expected: 24  Received: 20      │
                                  │ Short by: 4 units               │
                                  │                                  │
                                  │ [Recount]                       │
                                  │ [Confirm 20 — shortage]         │
                                  │ [Mark as disputed]              │
                                  └─────────────────────────────────┘

Operator recounts. Confirms 20.   Operator selects [Confirm 20].

                                  WMS records:
                                  - quantity_received: 20
                                  - quantity_expected: 24
                                  - discrepancy: -4
                                  - discrepancy_type: shortage
                                  - line_status: EXCEPTION_SHORTAGE
                                  [AUTO] Supervisor notified.
                                  [AUTO] Credit note flag raised.

                                  IMPORTANT: The 20 units are recorded
                                  and proceed through receiving normally.
                                  The discrepancy is an exception on this
                                  line only. Other lines are unaffected.
```

---

### 1G: Delivery Completion and Exception-Based Approval (C-01)

**Correction C-01 applied:** The original design required supervisor approval for every delivery before any stock could be put away. This is revised. Routine lines proceed automatically. Only lines carrying specific exception flags require supervisor action.

#### Which lines require supervisor approval?

| Condition | Exception type | Supervisor action required |
|---|---|---|
| All goods received as described, batch confirmed, condition good | None | **No supervisor approval needed** |
| Quantity discrepancy (short or over) | EXCEPTION_SHORTAGE / EXCEPTION_OVERAGE | Supervisor must review and approve or query |
| Damaged goods present | EXCEPTION_DAMAGE | Supervisor must authorise quarantine action |
| No manufacturer batch on a cosmetic/gel product | EXCEPTION_NO_BATCH | Supervisor must resolve before stock released |
| Unknown product (barcode not recognised) | EXCEPTION_UNKNOWN_PRODUCT | Supervisor must approve product creation or quarantine |
| Unverified pack conversion (e.g. Nail Files) | EXCEPTION_UNVERIFIED_CONVERSION | Supervisor must complete physical count before saleable qty released |
| Near-expiry stock (< threshold) | EXCEPTION_NEAR_EXPIRY | Supervisor must approve receipt |
| Expired stock (past date) | EXCEPTION_EXPIRED | Supervisor must authorise quarantine or rejection |
| Vehicle damage noted on arrival | EXCEPTION_VEHICLE_CONDITION | Supervisor must review all lines from that delivery |

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
All lines entered.                [SCREEN S7] Delivery Summary.
Operator reviews summary.         ┌─────────────────────────────────┐
                                  │ DN-2026-0042  NeoNail UK        │
                                  │ 7 lines  |  4 exceptions        │
                                  │                                  │
                                  │ ROUTINE (proceed to putaway):   │
                                  │ ✓ Gel Polish Red  48u  Batch A  │
                                  │ ✓ Gel Polish Red  12u  Batch B  │
                                  │ ✓ Top Coat        24u  Batch D  │
                                  │                                  │
                                  │ EXCEPTIONS (supervisor needed): │
                                  │ ⚠ Base Coat 20u — shortage (-4) │
                                  │ ⚠ Ballet Pink 2u — damaged      │
                                  │ ⚠ Nail Files 3 boxes — unverif. │
                                  │ ⚠ Ballet Pink 10u — damage noted│
                                  │    (supervisor reviews lot)     │
                                  │                                  │
                                  │ [Submit delivery]               │
                                  └─────────────────────────────────┘

Operator taps [Submit delivery].  [AUTO] Status set:
                                  - Routine lines → APPROVED_PUTAWAY
                                  - Exception lines → PENDING_SUPERVISOR
                                  [AUTO] Supervisor notification sent
                                  listing only exception lines.

                                  ROUTINE LINES ARE NOW AVAILABLE
                                  FOR PUTAWAY IMMEDIATELY.
                                  No waiting for supervisor.
```

#### Supervisor exception review (desktop or tablet):

```
[SCREEN S9] Supervisor Exceptions Panel.
┌─────────────────────────────────────────────────────────────┐
│  MP WMS  |  Exceptions Requiring Approval                   │
│  DN-2026-0042  |  NeoNail UK  |  3 Nov 10:34                │
│  Received by: Dean                                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ⚠ BASE COAT  —  Shortage                                  │
│  Received: 20  |  Expected: 24  |  Short: 4               │
│  [Approve shortage — raise credit note]                     │
│  [Dispute with supplier]  [Recount requested]              │
│                                                             │
│  ⚠ BALLET PINK × 2  —  Damaged                            │
│  Batch E2026-012  |  Action: Quarantine                    │
│  [Approve quarantine]  [Approve return to supplier]        │
│  [Approve disposal]                                         │
│                                                             │
│  ⚠ BALLET PINK × 10  —  Review (damage noted on delivery) │
│  Batch E2026-012  |  Condition: Good                       │
│  [Approve for putaway]  [Hold for inspection]              │
│                                                             │
│  ⚠ NAIL FILES × 3 BOXES  —  Unverified conversion         │
│  Pack count unknown. Cannot release as saleable.           │
│  [Assign count task to operator]  [Hold indefinitely]      │
│                                                             │
│  Approved by: [Supervisor name ▾]  Password: [____]        │
│  [Save all approvals]                                       │
└─────────────────────────────────────────────────────────────┘
```

**Each exception line is approved individually.** Approving the shortage does not approve the damaged goods. Each action is recorded with the supervisor's identity and timestamp.

**If the supervisor is unavailable:** Any admin-role user designated as a deputy (see BD-06) can approve. The approval record identifies the approver and their role clearly. This is not a workaround — it is the defined fallback.

---

## Section 2 — Unknown Product Flow

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Operator scans a barcode.         WMS: barcode not found.
                                  [SCREEN S8] Unknown barcode.
                                  ┌─────────────────────────────────┐
                                  │ ⚠ Barcode not recognised        │
                                  │ 5901234599999                   │
                                  │                                  │
                                  │ [Search by name or SKU]        │
                                  │ [Propose new product — supervisor]│
                                  │ [Quarantine — cannot identify]  │
                                  └─────────────────────────────────┘

Option A — Search:
Operator types partial name.      WMS shows matching products.
Selects correct product.          WMS records: manually_identified=true
                                  on this line. Proceeds to quantity
                                  and batch entry.

Option B — Propose new product:
Operator enters: name, SKU,       WMS creates product record with
UOM, what was on the barcode.     status: PENDING_REVIEW.
                                  Line flagged: EXCEPTION_UNKNOWN_PRODUCT.
                                  Supervisor notification sent.
                                  Stock held in INBOUND zone — cannot
                                  be put away until supervisor approves
                                  the new product.

Option C — Quarantine:
Operator physically sets items    WMS creates quarantine record:
aside in Q zone.                  product description (free text),
                                  barcode scanned, quantity, location=Q.
                                  Status: QUARANTINE_UNKNOWN.
                                  Supervisor notified.
```

---

## Section 3 — Exception-Based Approval Matrix (C-01 detailed)

**Correction C-01 summary table — who approves what:**

| Exception | Who can approve | Who is notified | Audit record |
|---|---|---|---|
| Shortage < 10% of line | Senior operator (if configured) or supervisor | Supervisor | discrepancy record + approver |
| Shortage ≥ 10% of line | Supervisor only | Supervisor + manager | discrepancy record + supervisor sign-off |
| Overage any amount | Supervisor | Supervisor | overage record + decision |
| Damaged goods — quarantine | Operator records; supervisor approves disposition | Supervisor | quarantine record + approval |
| Unknown product — propose | Supervisor approves product creation | Supervisor | product creation record + approval |
| No manufacturer batch (cosmetic) | Supervisor only | Supervisor | hold record + supervisor decision |
| Unverified pack conversion | Supervisor assigns count task | Supervisor | task record + count result |
| Near-expiry approval | Supervisor | Supervisor | expiry warning + approval |
| Expired stock | Supervisor — no release option | Supervisor + manager | quarantine/rejection record |
| Vehicle damage | Supervisor reviews all lines | Supervisor + manager | vehicle condition record |
| FEFO override | Supervisor with reason | Supervisor | override record + reason |

**Routine receipts (no flags):** No supervisor action required. Operator submits, routine lines proceed directly to APPROVED_PUTAWAY.

---

## Section 4 — Stock Status States (C-02)

**Correction C-02 applied:** The original design created stock with `available` status before it was physically located. This is replaced with eight distinct states. Stock cannot be available for picking or Shopify until it is physically in an eligible storage location.

| State | Meaning | Can be picked? | Shopify visible? | Who sets it? |
|---|---|---|---|---|
| **RECEIVING** | Operator is currently entering this line | No | No | Operator (in progress) |
| **RECEIVED_UNVERIFIED** | Line submitted; conversion or batch pending | No | No | System on submission |
| **PENDING_SUPERVISOR** | Exception flag — waiting for supervisor action | No | No | System on exception detection |
| **APPROVED_PUTAWAY** | Supervisor approved (or routine — no approval needed). Physically in INBOUND zone. | No | No | System / supervisor |
| **IN_LOCATION** | Physically placed in an eligible storage bin. | No | No | System on putaway confirmation |
| **AVAILABLE** | In location, no holds, no reservations. | Yes | Yes (if Shopify sync enabled) | System after IN_LOCATION confirmed |
| **RESERVED** | Allocated to a specific order — not available for other orders. | Yes (for that order only) | Reflected in Shopify reserved qty | System on order allocation |
| **QUARANTINE** | Damaged, expired, investigation hold, or awaiting disposition. | No | No | Operator or supervisor |
| **DISPATCHED** | Picked, packed and handed to courier. | No | No (deducted) | System on dispatch confirmation |

**The transition from IN_LOCATION to AVAILABLE is automatic once putaway is confirmed AND the bin is an eligible storage location (not INBOUND, QUARANTINE or DISPATCH zone).**

**Double-counting prevention:**
- A stock entry is created once, at the moment of receiving confirmation.
- Quantity never increases without an explicit inbound transaction (receipt or adjustment).
- Partial putaway across multiple bins does not create new stock entries — it updates the location reference and splits the quantity.
- The total quantity in all stock entries for a batch must always equal the quantity on the original delivery line. Any discrepancy triggers a system alert.

---

## Section 5 — Packaging Units and Quantity Conversion (C-05)

**Correction C-05 applied:** Receiving must clearly distinguish the physical packaging unit from the saleable unit. Conversions are only applied when verified.

### Packaging hierarchy supported:

| Level | Examples | How recorded |
|---|---|---|
| Pallet | Full pallet of product | Pallet count; contents verified by scanning |
| Outer carton | Box of 24 bottles | Carton count; units-per-carton must be verified |
| Inner pack / sleeve | Strip of 12 nail file packs | Pack count; units-per-inner must be verified |
| Individual unit | Single bottle, single nail file pack | Scanned individually or counted |
| Loose | Unpackaged items | Counted individually |

### Verified vs unverified conversions:

A conversion (e.g. 1 outer carton = 24 bottles) is **verified** only when:
- The product record has a confirmed `pack_qty_confirmed = true` in the WMS, OR
- The supervisor physically counts and confirms it on this delivery.

**If conversion is unverified:**
- WMS records the packaging count only.
- WMS sets `quantity_saleable = NULL` and `conversion_pending = true`.
- Stock status: RECEIVED_UNVERIFIED.
- A supervisor task is created: "Count contents of [product] boxes and confirm units per box."
- Until that task is completed and approved, the stock has no saleable quantity and cannot be picked or shown in Shopify.

### Nail Files (SKU 4504) — explicit handling:

```
Operator receives 3 outer boxes of Nail Files.

WMS records:
  quantity_received_packaging = 3
  packaging_unit = "outer box"
  quantity_saleable = NULL
  conversion_pending = true

Supervisor task created:
  "Open Nail Files outer box and count individual packs.
   Current assumption: unknown (previous assumption 25 was unverified).
   Do not release stock until count confirmed."

Once supervisor or operator completes count under supervision:
  Physical count result: 30 packs per box (example)
  Supervisor approves: pack_qty = 30, pack_qty_confirmed = true
  WMS updates: quantity_saleable = 90 (3 × 30)
  Stock status transitions from RECEIVED_UNVERIFIED → APPROVED_PUTAWAY
```

**The system must never automatically calculate 3 boxes × 25 = 75 packs when the conversion is unverified.**

---

## Section 6 — Location and Bin Allocation (C-04 revised)

### 6.1 Location Numbering System (unchanged from Task 003)

```
Format: ZONE-AISLE-SHELF-POSITION

Example: A-02-C-04
  A  = Zone (Ambient)
  02 = Aisle number
  C  = Shelf level (A=bottom, B=mid, C=top)
  04 = Position from left

Zones:
  A = Ambient storage (standard)
  C = Cold/chilled (if applicable — see BD-05)
  I = Inbound (temporary holding — goods not yet put away)
  Q = Quarantine (physically separated — goods cannot be picked)
  R = Returns (inspection area)
  D = Dispatch (staging — packed orders awaiting collection)
```

### 6.2 Bin Allocation Decision Order (C-04)

**Correction C-04 applied:** The original priority order was proximity-based. The revised order places suitability checks first.

The WMS evaluates candidate bins in this order, eliminating unsuitable candidates at each step:

```
Step 1: Correct fulfilment client and warehouse
  → Eliminate any bins not belonging to the client's warehouse.
  → In a multi-client environment, bins must be client-exclusive
    or explicitly shared with both clients' agreement.

Step 2: Storage conditions
  → If product requires cold storage (BD-05): only show cold zone bins.
  → If product is ambient: only show ambient zone bins.
  → Never recommend a bin in the wrong temperature zone.

Step 3: Stock condition
  → If stock is quarantine or damaged: direct to Q zone only.
  → Never recommend a standard storage bin for quarantine stock.
  → APPROVED_PUTAWAY stock: never send to Q or D zones.

Step 4: Batch compatibility
  → Never recommend a bin that contains a different batch of the same SKU.
    (Different batches must be physically separated for FEFO compliance.)
  → Never recommend a bin that contains a different SKU unless it is a
    designated multi-SKU shelf, and then only if the operator confirms.

Step 5: Available bin capacity
  → If bin capacity is configured: only recommend bins with sufficient
    remaining capacity for the quantity being put away.
  → If capacity is not configured: skip this check (with a note in BD-03).

Step 6: Existing location for same SKU and same batch
  → If the same SKU and batch are already in bin X, recommend X first
    (consolidation). This reduces travel and simplifies picking.

Step 7: Nearest suitable empty or same-product bin
  → If no existing location for this batch: find the nearest bin
    meeting all the above criteria.
  → "Nearest" is determined by zone and aisle sequence in the WMS
    location database.

Step 8: Operator override
  → Operator may select a different bin from those meeting steps 1–5.
  → Selecting a bin that fails step 4 (batch mixing) requires supervisor
    approval and records the reason.
  → Selecting a bin that fails step 3 (quarantine mixing) is blocked.
    Cannot be overridden by operator.
```

**Sharing a shelf between SKUs or batches:**

Multiple SKUs or batches may occupy positions on the same physical shelf, provided:
- Each position has a unique barcode label.
- Each position is recorded as a separate bin in the WMS.
- The WMS knows exactly which SKU and batch is in which position.
- The operator confirms the correct bin by scanning the position barcode — not just the shelf barcode.

Scanning a shelf barcode when positions exist on that shelf is rejected: "Scan the specific position label, not the shelf label."

### 6.3 System Recommendation Message

```
[SCREEN P2] Recommended location:
┌────────────────────────────────┐
│  PUT AWAY                      │
│  Gel Polish Red × 48           │
│  Mfr Batch: A2026-047          │
│  Exp: 31/12/2027               │
│  ─────────────────────         │
│  Suggested bin:                │
│  ┌──────────────────────────┐  │
│  │      A - 02 - C - 04    │  │
│  │   Reason: same SKU/batch │  │
│  │   Capacity: 12 remaining │  │
│  └──────────────────────────┘  │
│                                │
│  Quantity to place here: [48]  │
│  (adjust for partial putaway)  │
│                                │
│  [🔍 Scan bin to confirm]     │
│  [Choose a different bin]      │
└────────────────────────────────┘
```

---

### 6.4 Partial Putaway Across Multiple Bins (C-06)

**Correction C-06 applied:** A single delivery line or batch may need to be split across several bins. The WMS must track quantity placed in each bin and show the remaining quantity still to be placed.

**Example:** 48 bottles of Gel Polish Classic Red, Batch A2026-047. Only 20 fit in the first bin.

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Operator arrives at A-02-C-04.    [SCREEN P2] Recommended bin.
Scans bin barcode.                [SCREEN P3] Bin confirmed.
                                  ┌─────────────────────────────────┐
                                  │ ✓ BIN CONFIRMED: A-02-C-04      │
                                  │                                  │
                                  │ Total to put away:  48 bottles  │
                                  │ Already placed:      0 bottles  │
                                  │ Remaining:          48 bottles  │
                                  │                                  │
                                  │ How many are you placing HERE?  │
                                  │         [20]                    │
                                  │   [−]              [+]          │
                                  └─────────────────────────────────┘

Operator places 20 bottles
in bin A-02-C-04.                 Operator enters: 20.

                                  [SCREEN P4a] Partial putaway.
                                  ┌─────────────────────────────────┐
                                  │ ✓ 20 bottles placed in          │
                                  │   A-02-C-04                     │
                                  │                                  │
                                  │ Remaining to place: 28 bottles  │
                                  │ Batch: A2026-047                │
                                  │                                  │
                                  │ Next suggested bin:             │
                                  │   A-02-C-05  (capacity: 24)     │
                                  │                                  │
                                  │ [Continue to next bin]          │
                                  │ [Stop — resume later]           │
                                  └─────────────────────────────────┘

Operator continues to A-02-C-05.  Operator scans A-02-C-05.
Places 20 bottles.                Enters: 20.
                                  Remaining: 8 bottles.

Operator continues to A-02-C-06.  Scans A-02-C-06. Places 8.
                                  Remaining: 0.

                                  [SCREEN P5] Putaway complete.
                                  ┌─────────────────────────────────┐
                                  │ ✓ ALL PLACED                    │
                                  │ Gel Polish Red  Batch A2026-047 │
                                  │                                  │
                                  │ A-02-C-04: 20 bottles           │
                                  │ A-02-C-05: 20 bottles           │
                                  │ A-02-C-06: 8 bottles            │
                                  │ Total: 48 bottles ✓             │
                                  │                                  │
                                  │ [Next item →]                   │
                                  └─────────────────────────────────┘
```

**WMS rules for partial putaway:**
- Quantity placed in each bin is recorded immediately on confirmation — not batched.
- Total quantity placed across all bins can never exceed quantity approved for putaway.
- If operator tries to place more than the remaining amount: "You are trying to place 30 but only 8 remain. Please recount."
- Stock status transitions from APPROVED_PUTAWAY → IN_LOCATION per bin as each is confirmed.
- A delivery line is only fully complete when remaining quantity = 0.
- Partial putaway can be stopped and resumed. WMS shows the delivery as "putaway in progress" with remaining quantities.

**Stop and resume:**
```
Operator selects [Stop — resume later].

[SCREEN] Pause putaway.
┌────────────────────────────────┐
│  Putaway paused.               │
│                                │
│  Remaining items saved:        │
│  Gel Polish Red: 28 bottles    │
│  Base Coat: 20 bottles         │
│  Top Coat: 24 bottles          │
│  Ballet Pink: 10 bottles       │
│                                │
│  Resume from any device using  │
│  delivery DN-2026-0042.        │
│                                │
│  [Return to menu]              │
└────────────────────────────────┘
```

---

## Section 7 — Mistakes, Interruptions and Recovery (C-07)

**Correction C-07 applied:** Expanded recovery for 11 failure types. All post-confirmation corrections preserve the audit trail — no silent overwrites.

### 7.1 Before Confirmation (In-Session Corrections)

These corrections do not require supervisor approval and leave no audit record because the transaction has not yet been committed:

| Mistake | How to correct |
|---|---|
| Wrong barcode scanned | Tap back arrow. Re-scan correct product. Previous scan discarded. |
| Wrong quantity typed | Edit quantity field before tapping Next. |
| Wrong packaging type selected | Tap back. Reselect correct packaging type. |
| Wrong batch number entered | Tap back to batch screen. Re-enter. |
| Wrong condition selected | Tap back. Reselect. |

**The system commits a line only when the operator taps "Confirm this line." Before that point, all inputs are in session memory only.**

### 7.2 After Line Confirmation (Pre-Submission Corrections)

A confirmed line is saved to the database but the delivery is not yet submitted. The operator can edit it:

```
[SCREEN] Review all lines.
Each line shows: product, qty, batch, condition.
[Edit] button on each line.

Tapping [Edit] on a confirmed line:
- Opens the line for modification.
- WMS records: original values + edit time + editing operator.
- Operator corrects the line and re-confirms.
- Audit: "Line 3 edited before submission by [operator] at [time]. 
  Original: 20 units. Revised: 22 units."
```

### 7.3 After Submission (Post-Confirmation Corrections)

Once the delivery is submitted, lines cannot be silently edited. All corrections create an explicit correction transaction:

| Scenario | Correction process | Who approves | Audit record |
|---|---|---|---|
| Quantity wrong after submission | Supervisor rejects the line. Operator re-enters corrected quantity. Resubmitted line linked to original. | Supervisor | Rejection + resubmission, both linked to delivery |
| Item placed in wrong bin during putaway | Operator or supervisor creates a bin transfer: "Move X units of [batch] from A-02-C-04 to A-02-C-07." Transfer recorded as a stock movement. Physical stock moved. | Operator can initiate; supervisor confirms if moving > [threshold] units | Stock movement record: transfer type, from/to bins, quantity, who moved, who approved |
| Quantity placed in wrong bin amount | Same as above — bin transfer. |  |  |
| Duplicate line submitted (same product, same batch, same delivery) | WMS detects duplicate on submission: "This batch is already recorded on this delivery. Did you scan it twice?" Operator cancels duplicate or confirms it is additional stock. | Operator confirms; supervisor notified | Duplicate detection flag on record |
| Network failure mid-receiving | Each confirmed line is saved to the database immediately on confirmation, not held until submission. Loss of connection during a confirmation attempt: WMS retries automatically. On reconnect, WMS checks whether the transaction was committed before showing the next screen. | N/A — automatic | Last committed state preserved |
| Network failure during putaway confirmation | Same pattern — each bin confirmation is written immediately. On reconnect, WMS shows the quantity still remaining. | N/A — automatic | Each bin placement recorded on confirmation |
| Scanner fails mid-pick | Operator logs in on a different device (another scanner, tablet or desktop). Delivery or putaway record is available from any device. Remaining lines shown. | N/A | Session handover visible in delivery record |
| Two operators on the same delivery | Supported. WMS shows which lines each operator is working on. If both operators try to confirm the same line simultaneously, the second receives: "This line is being edited by [operator]. Please wait or contact them." | N/A | Both operators' actions logged on the delivery |
| Interrupted delivery (operator leaves mid-receiving) | WMS shows delivery as IN_PROGRESS. Any authorised operator can resume. Delivery record shows original operator and all contributors. | N/A | All operators' actions visible in delivery history |
| Database operation fails | WMS shows error message. Does not advance to the next screen. Operator is not told a stock movement was recorded if it was not. Operator must retry. Idempotency key prevents duplicate writes on retry. | N/A — system handles | Failed attempts not committed; successful retry recorded once |
| Incomplete putaway at end of shift | WMS shows delivery as PUTAWAY_IN_PROGRESS. Stock in APPROVED_PUTAWAY state remains in the INBOUND zone physically. Next operator resumes. | N/A | Remaining quantities visible to all |

### 7.4 Audit Trail Principle

**No stock quantity is ever silently overwritten.** Every correction is a new transaction referencing the original. The audit trail always shows:
- What the original transaction recorded.
- What was wrong.
- Who identified the error and when.
- What correction was made.
- Who approved the correction.

---

## Section 8 — Stock Ledger Design (C-08)

**Correction C-08 applied:** Data structures revised to align with the corrected physical operation, including immutable movement records, eight stock states, balance calculation rules and duplicate prevention.

### 8.1 Proposed Data Tables

> **Design only. None of these exist in the current codebase. Approval required before implementation.**

```
deliveries
  id, fulfilment_client_id, warehouse_id, supplier_id,
  delivery_note_ref, arrived_at, status:
    (IN_PROGRESS | SUBMITTED | PUTAWAY_IN_PROGRESS | COMPLETE),
  idempotency_key (UUID generated on delivery creation — prevents
  duplicate delivery from double-tap),
  notes, created_by, created_at, updated_at

delivery_lines
  id, delivery_id, product_id, batch_id,
  packaging_unit (pallet|outer_carton|inner_pack|individual|loose),
  quantity_received_packaging, packaging_qty_per_saleable_unit,
  conversion_verified (bool),
  quantity_saleable (null if unverified),
  quantity_expected, condition (good|mixed|damaged|rejected),
  quantity_good, quantity_damaged,
  discrepancy_type (none|shortage|overage),
  discrepancy_notes, manually_identified (bool),
  status (RECEIVING|RECEIVED_UNVERIFIED|PENDING_SUPERVISOR|
          APPROVED_PUTAWAY|PUTAWAY_IN_PROGRESS|COMPLETE|
          REJECTED|QUARANTINE_PENDING),
  exception_type (nullable), exception_notes,
  operator_id, submitted_at

exception_approvals
  id, delivery_line_id, exception_type, approved_by, approved_at,
  decision, decision_notes
  — immutable: one record per decision. Corrections create new records.

batches
  id, fulfilment_client_id, product_id,
  manufacturer_batch_number (nullable — absent means batch not identified),
  batch_source (manufacturer|unidentifiable),
  no_batch_applicable (bool — only true if configured by admin for this product),
  expiry_date (nullable),
  no_expiry_confirmed (bool),
  internal_tracking_ref (always present — MP Transport warehouse reference only,
                          never presented as manufacturer batch),
  received_at, notes

stock_entries
  id, fulfilment_client_id, warehouse_id, batch_id, location_id (nullable
  until putaway), quantity, status:
    (RECEIVED_UNVERIFIED|PENDING_SUPERVISOR|APPROVED_PUTAWAY|
     IN_LOCATION|AVAILABLE|RESERVED|QUARANTINE|DISPATCHED),
  version (optimistic lock counter for concurrent updates)
  — quantity field is the current balance; always reconcilable
    against sum of stock_movements for this entry.

stock_movements
  id, stock_entry_id, movement_type:
    (RECEIPT|PUTAWAY|TRANSFER|RESERVATION|RESERVATION_RELEASE|
     PICK|PACK|DISPATCH|ADJUSTMENT|QUARANTINE_IN|QUARANTINE_OUT|
     RETURN_IN|DISPOSAL|WRITE_OFF),
  quantity_change (positive = in, negative = out),
  from_location_id (nullable), to_location_id (nullable),
  reference_type, reference_id,
  performed_by, approved_by (nullable — required for adjustments),
  performed_at, notes, idempotency_key
  — IMMUTABLE: records are never updated or deleted.
  — Corrections create a new record that references the original.

quarantine_records
  id, stock_entry_id, reason:
    (DAMAGED|EXPIRED|NO_BATCH|UNKNOWN_PRODUCT|INVESTIGATION|NEAR_EXPIRY),
  quantity, status:
    (OPEN|APPROVED_QUARANTINE|RESOLVED_RETURN|RESOLVED_DISPOSE|
     RESOLVED_BACK_TO_STOCK),
  raised_by, raised_at, approved_by, resolved_by, resolved_at, notes

putaway_tasks
  id, delivery_line_id, batch_id, total_quantity, placed_quantity,
  remaining_quantity, status (PENDING|IN_PROGRESS|COMPLETE|PAUSED),
  putaway_bins (JSON array of {bin_id, quantity, confirmed_at, confirmed_by}),
  started_by, paused_at, completed_by, completed_at
```

### 8.2 Balance Calculation — No Double-Counting (C-08)

**Available quantity for a product** = sum of `stock_entries.quantity` where:
- `status = AVAILABLE`
- `batch.expiry_date > today` (or `no_expiry_confirmed = true`)
- `location.zone NOT IN (Q, I, D, R)`

**Reserved quantity** = sum of `stock_entries.quantity` where `status = RESERVED`.

**Total on hand** = Available + Reserved + IN_LOCATION (not yet released to AVAILABLE).

**Shopify inventory level** = Available only (never Reserved or IN_LOCATION).

**Validation check (runs nightly or on demand):**
For each `stock_entry`, the current `quantity` must equal the sum of all `stock_movements.quantity_change` for that entry. Any discrepancy raises an alert for supervisor investigation.

### 8.3 Concurrency and Transaction Safety

- `stock_entries.version` is an optimistic lock counter. Any update to a stock entry increments the version. If two operators try to update the same entry simultaneously, the second write detects the version mismatch and retries or raises an error.
- `idempotency_key` on `stock_movements` prevents a duplicate movement being written if a network retry causes the same request to arrive twice.
- All stock movements within a single transaction (e.g. putaway: deduct from APPROVED_PUTAWAY, add to IN_LOCATION) are wrapped in a database transaction. If any step fails, all steps roll back. No partial movements.

---

## Section 9 — Physical Control of Goods Awaiting Action (C-09)

**Correction C-09 applied:** The original design defined electronic stock states but did not specify where goods are physically held in each state, how they are labelled, or what happens when holding areas are full.

### 9.1 Physical Holding Zones

| Electronic status | Physical location | Zone code | Label colour |
|---|---|---|---|
| IN_PROGRESS / RECEIVED_UNVERIFIED | INBOUND holding area | I zone | No label — goods in receiving process |
| APPROVED_PUTAWAY | INBOUND holding area — approved section | I zone | Green label: "APPROVED — PUT AWAY" |
| PENDING_SUPERVISOR | INBOUND holding area — exceptions section | I zone | Amber label: "HOLD — SUPERVISOR REQUIRED" |
| QUARANTINE | Quarantine zone — physically separated | Q zone | Red label: "QUARANTINE — DO NOT MOVE" |
| RETURNS | Returns inspection area | R zone | Orange label: "RETURNS — INSPECT BEFORE TOUCHING" |
| DISPATCH staging | Dispatch area | D zone | Blue label: "DISPATCH — DO NOT RESTOCK" |

**Labels are printed by the WMS on a label printer connected to the receiving station.** Labels are applied to outer cartons or individual items at the time the status is set. A label contains:
- Status (human-readable and colour-coded).
- Product name and SKU.
- Batch reference (internal tracking ref — not the manufacturer batch label).
- Date and operator name.
- WMS-generated barcode for scanning during putaway or investigation.

### 9.2 Why Physical Labels Are Essential

An operator walking through the INBOUND zone must be able to tell at a glance:
- Which items they should put away (green label).
- Which items they must not touch (amber or red label).

Without physical labels, an operator may accidentally put away stock that is pending supervisor approval, or move quarantine stock to a picking location.

**The physical label is the primary control. The electronic status is the record. Both must agree.**

### 9.3 Preventing Accidental Picks

Stock in the INBOUND (I) zone is not available for picking by design (electronic status is not AVAILABLE). However:

- The I zone must be physically separated from standard picking aisles.
- If a picker enters the I zone, they will find no pick tasks directing them there.
- If a picker scans an item in the I zone, the WMS shows: "This item is in the INBOUND zone and not available for picking. Do not move it."

### 9.4 Quarantine Zone Physical Controls

- The Q zone must be physically inaccessible to pickers — a locked area or clearly marked "NO PICKING" zone is required.
- Quarantine stock must never share a shelf with standard picking stock.
- The WMS will not generate a pick task for any item in the Q zone.
- If an operator scans a quarantine item, the WMS shows: "QUARANTINE — DO NOT PICK. Supervisor must authorise any movement."

### 9.5 When the INBOUND Zone Is Full

If the INBOUND zone reaches capacity:

1. **WMS alert:** Supervisor and warehouse manager notified: "INBOUND zone approaching capacity. X items awaiting putaway."
2. **Priority putaway:** Supervisor reviews APPROVED_PUTAWAY items and instructs operator to prioritise putaway immediately.
3. **Emergency overflow:** If a delivery must be accepted and no space is available, supervisor designates a temporary overflow position. WMS records the overflow location as a temporary I zone extension.
4. **Refuse delivery:** If no space is available and no overflow is safe, supervisor may refuse to accept the delivery. WMS records refusal reason and supplier is contacted.

**A full INBOUND zone is an operational failure. It means putaway is not keeping pace with receiving. This should be flagged as a KPI issue, not resolved by improvising.**

---

## Section 10 — Revised Screen Concepts (C-10)

All screens updated to reflect Corrections 1–9. New screens added for partial putaway, correction workflows and packaging quantity entry.

### Receiving Screens (Handheld — portrait)

**S1 — Home**
```
┌────────────────────────────┐
│  MP WMS  ·  RECEIVING      │
│  ─────────────────────     │
│  [+ New Delivery]          │
│  [Resume Delivery ›]       │
│                            │
│  Awaiting putaway: 2       │
│  [Start putaway ›]         │
│                            │
│  Exceptions for review: 3  │
│  [View exceptions ›]       │
└────────────────────────────┘
```

**S2 — Delivery Header**
```
┌────────────────────────────┐
│  ← New Delivery            │
│  ─────────────────────     │
│  Supplier:                 │
│  [NeoNail UK          ▾]   │
│                            │
│  Delivery Note No.:        │
│  [🔍 Scan or type]        │
│  [___DN-2026-0042_______]  │
│                            │
│  Vehicle condition:        │
│  ● All good                │
│  ○ Damage / seals broken   │
│                            │
│  [Continue →]              │
└────────────────────────────┘
```

**S3 — Scan Product**
```
┌────────────────────────────┐
│  ← DN-2026-0042  Line 1   │
│  ─────────────────────     │
│                            │
│  What are you scanning?    │
│  ○ Individual unit         │
│  ● Outer carton            │
│  ○ Mixed carton            │
│  ○ Pallet                  │
│                            │
│  ┌──────────────────────┐  │
│  │    SCAN BARCODE      │  │
│  │    or enter SKU      │  │
│  └──────────────────────┘  │
│                            │
│  [⚠ No barcode on item]   │
└────────────────────────────┘
```

**S4a — Quantity (individual units, verified)**
```
┌────────────────────────────┐
│  ✓ Gel Polish Classic Red  │
│  SKU: GP-RED-001  · each   │
│  ─────────────────────     │
│                            │
│  Quantity received:        │
│  ┌──────────────────────┐  │
│  │         48           │  │
│  │   [−]        [+]     │  │
│  └──────────────────────┘  │
│                            │
│  [Next: Batch info →]      │
└────────────────────────────┘
```

**S4b — Quantity (unverified packaging unit)**
```
┌────────────────────────────┐
│  ✓ Nail Files  SKU: 4504   │
│  ─────────────────────     │
│                            │
│  ⚠ PACK CONVERSION         │
│     UNVERIFIED             │
│                            │
│  Record physical boxes:    │
│  ┌──────────────────────┐  │
│  │          3           │  │
│  │   [−]        [+]     │  │
│  └──────────────────────┘  │
│                            │
│  Units per box: UNKNOWN    │
│  Saleable qty: TBC         │
│                            │
│  [Record 3 boxes →]        │
└────────────────────────────┘
```

**S5a — Manufacturer Batch (cosmetic product)**
```
┌────────────────────────────┐
│  BATCH INFORMATION         │
│  Gel Polish Classic Red    │
│  ─────────────────────     │
│                            │
│  Mfr batch number:        │
│  [🔍 Scan label or type]  │
│  [_____________________]   │
│                            │
│  Expiry / best before:     │
│  [DD] [MM] [YYYY]          │
│                            │
│  [No batch visible]        │
│  (places on HOLD)          │
│                            │
│  [Next →]                  │
└────────────────────────────┘
```

**S5b — No Batch Found on Cosmetic**
```
┌────────────────────────────┐
│  ⚠ NO MANUFACTURER BATCH   │
│  ─────────────────────     │
│  Gel Polish Classic Red    │
│                            │
│  Cosmetics must have a     │
│  manufacturer batch for    │
│  traceability.             │
│                            │
│  [Check packaging again]   │
│  [Place on HOLD —          │
│   supervisor will review]  │
│                            │
│  DO NOT select "no batch"  │
│  for cosmetic products     │
│  without supervisor        │
│  approval.                 │
└────────────────────────────┘
```

**S6 — Condition**
```
┌────────────────────────────┐
│  CONDITION                 │
│  Gel Polish Ballet Pink    │
│  × 12  ·  Batch E2026-012  │
│  ─────────────────────     │
│                            │
│  ● All items good          │
│  ○ Some items damaged      │
│  ○ All damaged / reject    │
│                            │
│  [Confirm this line ✓]     │
│  [← Go back to quantity]   │
└────────────────────────────┘
```

**S6b — Damaged Split**
```
┌────────────────────────────┐
│  DAMAGED ITEMS             │
│  Ballet Pink × 12          │
│  ─────────────────────     │
│  Damaged: [2]  Good: [10]  │
│                            │
│  Damaged items:            │
│  ● Quarantine (supervisor) │
│  ○ Return to supplier      │
│  ○ Dispose immediately     │
│                            │
│  Photo (optional):         │
│  [📷 Take photo]           │
│                            │
│  [Confirm ✓]               │
└────────────────────────────┘
```

**S7 — Line Confirmed**
```
┌────────────────────────────┐
│  ✓ LINE SAVED              │
│  ─────────────────────     │
│  Gel Polish Classic Red    │
│  48 × each                 │
│  Batch: A2026-047          │
│  Exp: 31/12/2027           │
│  Condition: Good           │
│  Status: Will proceed to   │
│           putaway          │
│                            │
│  Lines so far: 1           │
│                            │
│  [Scan next product]       │
│  [Review / edit lines]     │
│  [Finish & submit]         │
└────────────────────────────┘
```

**S8 — No Barcode**
```
┌────────────────────────────┐
│  ⚠ BARCODE NOT RECOGNISED  │
│  5901234599999             │
│  ─────────────────────     │
│  [Search by name or SKU]   │
│                            │
│  [Propose new product]     │
│  (supervisor will approve) │
│                            │
│  [Quarantine — cannot      │
│   identify this item]      │
└────────────────────────────┘
```

**S7-SUBMIT — Delivery Summary Before Submit**
```
┌────────────────────────────┐
│  DELIVERY SUMMARY          │
│  DN-2026-0042              │
│  ─────────────────────     │
│  ROUTINE — putaway now:    │
│  ✓ Gel Polish Red  48u     │
│  ✓ Gel Polish Red  12u     │
│  ✓ Top Coat        24u     │
│                            │
│  EXCEPTIONS — supervisor:  │
│  ⚠ Base Coat — shortage    │
│  ⚠ Ballet Pink — damaged   │
│  ⚠ Nail Files — unverified │
│  ⚠ Ballet Pink 10u — hold  │
│                            │
│  [Submit delivery]         │
│  [← Edit a line]           │
└────────────────────────────┘
```

### Putaway Screens (Handheld)

**P1 — Putaway Home**
```
┌────────────────────────────┐
│  PUTAWAY                   │
│  DN-2026-0042  3 Nov       │
│  ─────────────────────     │
│  Ready for putaway:        │
│                            │
│  Gel Polish Red   48u  ›   │
│  Gel Polish Red   12u  ›   │
│  Top Coat         24u  ›   │
│                            │
│  On hold (exceptions):     │
│  Base Coat        20u      │
│  Ballet Pink      10u      │
│  Nail Files       3 boxes  │
│                            │
│  [Start putaway]           │
└────────────────────────────┘
```

**P2 — Recommended Bin**
```
┌────────────────────────────┐
│  PUT AWAY                  │
│  Gel Polish Red × 48       │
│  Batch: A2026-047          │
│  ─────────────────────     │
│  Recommended bin:          │
│  ┌──────────────────────┐  │
│  │    A - 02 - C - 04   │  │
│  │  Same SKU + batch    │  │
│  │  Space: 20 bottles   │  │
│  └──────────────────────┘  │
│                            │
│  Placing here: [20]        │
│  (of 48 total)             │
│                            │
│  [🔍 Scan bin barcode]    │
│  [Choose different bin]    │
└────────────────────────────┘
```

**P3 — Bin Confirmed (partial)**
```
┌────────────────────────────┐
│  ✓ A-02-C-04 confirmed     │
│  ─────────────────────     │
│  Placing:                  │
│  Gel Polish Red × 20       │
│  Batch: A2026-047          │
│                            │
│  After this:               │
│  Remaining: 28 bottles     │
│                            │
│  [Confirm placement ✓]     │
│  [Wrong bin — rescan]      │
└────────────────────────────┘
```

**P4a — Partial Complete, Next Bin**
```
┌────────────────────────────┐
│  ✓ 20 placed in A-02-C-04  │
│  ─────────────────────     │
│  Remaining: 28 bottles     │
│  Batch: A2026-047          │
│                            │
│  Next bin:                 │
│  ┌──────────────────────┐  │
│  │    A - 02 - C - 05   │  │
│  │  Empty  ·  Cap: 24   │  │
│  └──────────────────────┘  │
│                            │
│  [Continue to next bin]    │
│  [Stop — resume later]     │
└────────────────────────────┘
```

**P5 — Putaway Complete for this Item**
```
┌────────────────────────────┐
│  ✓ ALL PLACED              │
│  Gel Polish Red  Batch A   │
│  ─────────────────────     │
│  A-02-C-04  ×20            │
│  A-02-C-05  ×20            │
│  A-02-C-06  ×8             │
│  Total: 48 ✓               │
│                            │
│  [Next item →]             │
│  [View all putaway]        │
│  [Return to menu]          │
└────────────────────────────┘
```

### Correction Screen (Post-Confirmation)

**CX1 — Bin Transfer (wrong bin correction)**
```
┌────────────────────────────┐
│  CORRECT BIN               │
│  ─────────────────────     │
│  Move stock to new bin     │
│                            │
│  Product: Gel Polish Red   │
│  Batch: A2026-047          │
│  Qty to move: [20]         │
│                            │
│  FROM: [🔍 Scan old bin]  │
│  A-02-C-04 ✓               │
│                            │
│  TO:   [🔍 Scan new bin]  │
│  A-03-B-01 ✓               │
│                            │
│  Reason: [Wrong aisle]     │
│                            │
│  [Request supervisor ✓]    │
└────────────────────────────┘
```

---

## Section 11 — Operational Test Scenarios and Acceptance Criteria

Each scenario can be used to verify the receiving and putaway module once built. These are not currently testable in the live system.

| # | Scenario | Pass criteria |
|---|---|---|
| T-01 | Routine delivery, all lines good, known products, batch recorded | All lines reach AVAILABLE status after putaway. No supervisor approval needed. Audit shows operator and timestamps. |
| T-02 | Same SKU in two batches | Two separate batch records. FEFO order correct (earlier expiry first). Each batch in a separate bin. |
| T-03 | Mixed carton (two products in one carton) | Two separate delivery lines created from one carton scan. Each product recorded individually. |
| T-04 | Nail Files (SKU 4504) — 3 boxes, unverified conversion | Quantity recorded as 3 boxes. Saleable qty = null. Supervisor task created. Stock status RECEIVED_UNVERIFIED until count confirmed. |
| T-05 | No manufacturer batch on Gel Polish | Line status PENDING_SUPERVISOR. Stock not available for picking. Supervisor can resolve or quarantine. |
| T-06 | 2 damaged bottles | 10 good proceed normally. 2 quarantined. Supervisor approves quarantine action. Credit note flag raised. |
| T-07 | Shortage: 20 received, 24 expected | Discrepancy recorded. Supervisor notified. 20 units proceed through putaway. Credit note flag raised. |
| T-08 | Unknown barcode | Operator searches, proposes new product (held for supervisor) or quarantines. Unknown product never proceeds to AVAILABLE without supervisor approval. |
| T-09 | 48 bottles split across 3 bins during putaway | WMS tracks remaining quantity. Total placed = 48. Three stock entries created (one per bin). All same batch. All AVAILABLE on completion. |
| T-10 | Network failure mid-receiving | On reconnect, last confirmed line is preserved. No duplicate created. Remaining lines shown correctly. |
| T-11 | Operator places stock in wrong bin | Bin transfer creates stock movement record. Original placement recorded. Correction references original. Supervisor approval recorded. |
| T-12 | Duplicate submission attempt | WMS detects idempotency key match. Second submission rejected silently. Only one stock entry created. |

---

## Section 12 — Outstanding Business Decisions

Business decisions from Task 003 are retained and updated. Provisional design assumptions are noted but **not treated as final approvals**.

### BD-01: Mandatory batch tracking — which products?

**Provisional assumption used in this design:** Mandatory for all cosmetic and gel products. Not applicable for accessories when configured by admin.

**Remaining decision:** Paul/Craig must confirm which product categories require mandatory batch tracking and configure the product-type settings in the WMS before the first receiving session.

| Option | Consequence |
|---|---|
| Mandatory for all products | Maximum traceability. More operator steps. Lower recall risk. |
| Mandatory for cosmetics/gels only | Pragmatic. Accessories and tools receive internal tracking only. |
| Optional for all | Not recommended. Traceability compromised. Not suitable for cosmetics distribution. |

**Decision required before:** First goods receipt session.

---

### BD-02: Near-expiry thresholds

**Provisional assumption:** Advisory at 90 days; supervisor approval required at 30 days; block (quarantine required) for expired stock.

**Remaining decision:** Confirm or revise the thresholds. Different thresholds may apply to different product categories (e.g. gels vs accessories).

---

### BD-03: Bin capacity tracking

**Provisional assumption:** Supported but optional per bin. Bins without a configured capacity are unlimited.

**Remaining decision:** Will MP Transport configure capacity per bin from the start, or defer this? Deferring means no capacity warnings and operators may overfill bins.

---

### BD-04: Purchase order matching

**Provisional assumption:** Delivery note reference only. PO matching deferred.

**Remaining decision:** If an external accounting system (Xero, Sage, etc.) is used for purchasing, PO matching can be added. This is not required for the initial go-live but should be planned.

---

### BD-05: Cold storage

**Provisional assumption:** Ambient storage only.

**Remaining decision:** Are any NeoNail or Fuzion Gel products required to be stored at a controlled temperature? If yes, the zone types and product category settings must be configured before receiving begins.

---

### BD-06: Supervisor deputy for exception approval

**Provisional assumption:** Any admin-role user can act as deputy when the designated supervisor is unavailable.

**Remaining decision:** Paul/Craig must name the supervisor and at least one deputy in the WMS user settings before go-live.

---

### BD-07: Photo capture

**Provisional assumption:** Photo attachment available for exceptions. Optional for routine lines.

**Remaining decision:** Does the scanning device (to be selected) have a camera? If not, photo capture cannot be used and damage evidence must be noted in text only.

---

### BD-08: MP Transport internal tracking reference format

**Provisional assumption:** `MP-{YYYYMMDD}-{delivery_line_number}` — e.g. `MP-20261103-006`.

**Remaining decision:** Is this format acceptable or does MP Transport use an existing internal numbering convention?

---

### BD-09: Nail Files (SKU 4504) first receiving

**Provisional assumption:** Receive as 3 boxes (packaging unit). Saleable qty pending physical count. No assumed conversion.

**Action required:** Before the first real delivery is received, Paul must physically count the contents of one Nail Files outer box and confirm the pack count. Once confirmed, the WMS product record is updated with `pack_qty_confirmed = true` and the verified count. This unlocks saleable inventory for that batch.

---

### BD-10 (new): Shortage approval threshold

**Question:** Can a senior operator approve a shortage independently, or does every shortage require a supervisor?

**Recommended option:** Shortages within 5% of line quantity can be approved by a senior operator. Shortages above 5% require supervisor.

**Alternative:** All shortages require supervisor (simpler rule, causes more delays).

**Remaining decision:** Paul/Craig to set the threshold.

---

### BD-11 (new): Which roles can do what in receiving?

**Question:** The current WMS has three roles: admin, supervisor, operator. The receiving workflow needs to clarify which actions each role can take.

**Recommended matrix:**

| Action | Operator | Supervisor | Admin |
|---|---|---|---|
| Start a new delivery | ✅ | ✅ | ✅ |
| Scan and record routine lines | ✅ | ✅ | ✅ |
| Submit a delivery | ✅ | ✅ | ✅ |
| Approve routine lines (no exceptions) | ✅ (auto) | ✅ | ✅ |
| Approve shortage | ❌ | ✅ | ✅ |
| Approve damage quarantine | ❌ | ✅ | ✅ |
| Approve unknown product | ❌ | ✅ | ✅ |
| Approve near-expiry receipt | ❌ | ✅ | ✅ |
| Configure batch tracking per product | ❌ | ❌ | ✅ |
| Verify pack conversion count | ❌ | ✅ | ✅ |
| Perform bin transfer (correction) | ✅ (initiate) | ✅ (approve) | ✅ |
| Perform putaway | ✅ | ✅ | ✅ |

**Remaining decision:** Paul/Craig to confirm this matrix or revise it.

---

## Section 13 — Implementation Sequence

Based on operational dependencies, the recommended build order is:

1. **Location management UI** — create warehouses, zones, and bins before any receiving can occur. Without bin records, putaway cannot be confirmed.

2. **Batch model and product pack-qty verification** — add `batches` table and verify Nail Files 4504 pack count before importing the real catalogue.

3. **Delivery header and line receiving (no exceptions)** — build the happy-path receiving flow first. Allows receiving of straightforward deliveries and getting the stock ledger operational.

4. **Exception handling in receiving** — add shortage, damage, unknown product and batch hold workflows.

5. **Putaway (full quantity, single bin)** — basic putaway confirming a bin for a complete line.

6. **Partial putaway across multiple bins** — once basic putaway is verified.

7. **Supervisor exception approval UI** — can be built in parallel with steps 4–6.

8. **Stock status and availability logic** — transitions between states, AVAILABLE flag, Shopify visibility gate.

9. **Physical label printing** — requires label printer hardware decision (BD-07 adjacent).

10. **Reporting and stock-take tools** — after the above is stable.

---

*This document is a design revision only. No application code, database structure or production settings have been changed.*  
*All screen concepts are design proposals. None are implemented.*  
*Approval required from Paul and Craig before Phase 3 development begins.*  
*Produced 3 November 2026 — MP Transport WMS Project*
