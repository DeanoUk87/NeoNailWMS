# WMS TASK 003 — Goods Receiving & Putaway Operational Blueprint

**Project:** MP Transport Warehouse Management System  
**Date:** 3 November 2026  
**Status:** Design only — no code changes authorised  
**Prerequisite reading:** WMS-TASK-002-OPERATIONS-GAP-ANALYSIS.md  
**STOP point:** This document must be approved by Craig and Paul before any development begins.

---

## How to Read This Document

This blueprint describes what the warehouse operation should look like once the receiving and putaway modules are built. It is written for two audiences:

- **Paul / Craig** — to approve the process design and answer the business decisions in Section 8.
- **The development team** — to understand what needs to be built, in what order, and why.

Throughout this document:
- **Operator** = the person physically handling stock in the warehouse.
- **Supervisor / Manager** = the person who approves exceptions and reviews receipts.
- **WMS** = the warehouse management software running on the screen or scanner.
- **[SCREEN]** = a proposed screen that does not yet exist.
- **[SCAN]** = a scanner action (beep on success, error tone on failure).

---

## Scenario Being Designed

A delivery vehicle arrives at the MP Transport warehouse carrying a pallet for NeoNail UK. The pallet contains:

- Gel Polish Classic Red × 48 individual bottles (no outer carton, all with EAN barcodes)
- Base Coat 15ml × 24 bottles in a mixed carton with Top Coat 15ml × 24 bottles
- Nail Files (SKU 4504) × 3 boxes, each box containing an unknown number of packs — no EAN, internal label only
- Gel Polish Ballet Pink × 12 bottles — 2 bottles visibly damaged
- A second batch of Gel Polish Classic Red × 12 bottles with a different expiry date
- Delivered quantity of Base Coat = 20, but delivery note says 24 (4 short)

This scenario exercises every condition the receiving workflow must handle.

---

## Section 1 — Complete Operational Workflow

### Phase 1A: Delivery Arrival and Document Check

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Delivery vehicle arrives.
Operator checks vehicle and
delivery note against expected
delivery (if a PO exists).         [SCREEN] Open Goods Receiving.
                                   Operator taps "New Delivery".
                                   WMS shows: supplier name field,
                                   delivery note number field,
                                   expected delivery list (if PO exists).

Operator enters or scans:
- Supplier name (or selects)
- Delivery note number              WMS records: delivery_note_ref,
- Arrival date/time (auto)          supplier_id, arrived_at (automatic).
- Number of cartons/pallets

Operator photographs delivery       WMS: optional photo attachment.
note and pallet labels.             (Phase 2 feature — see BD-07)
```

**Checks at this stage:**
- WMS confirms supplier matches an approved supplier on file (if PO matching enabled — see BD-04).
- If no matching expected delivery exists, WMS flags it as an unplanned delivery — supervisor notified.

**If this step fails:**
- No delivery note number → proceed as "unplanned delivery"; supervisor must review before stock is released.
- Vehicle sealed / damaged on arrival → operator marks delivery "condition: vehicle damage suspected"; supervisor decides whether to accept or reject.

---

### Phase 1B: Line-by-Line Product Receiving

This is the core of the workflow. The operator processes each product on the pallet one product at a time.

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Operator picks up first item.      [SCREEN] Receive Lines screen.
                                   Large central area: "Scan a barcode
                                   or enter SKU to begin."

Operator scans barcode             [SCAN] Scanner reads EAN/scan code.
on the product.                    WMS looks up product by:
                                   1. manufacturer_barcode
                                   2. scan_code (internal label)
                                   3. If not found → "Unknown product"
                                      flow (Section 1D)

                                   If found:
                                   [SCREEN] Product confirmed screen:
                                   ┌─────────────────────────────────┐
                                   │ ✓ Gel Polish — Classic Red       │
                                   │ SKU: GP-RED-001                  │
                                   │ UOM: each                        │
                                   │ Enter quantity received:  [___]  │
                                   └─────────────────────────────────┘

Operator counts the units
and types the quantity.            Operator types: 48
                                   (or uses +/- buttons for small qtys)

                                   WMS check: if quantity > 2× expected
                                   quantity, warns: "Quantity seems high
                                   — please recount."
```

---

### Phase 1C: Batch and Expiry Entry (per product line)

Immediately after quantity, the WMS asks for batch information.

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
                                   [SCREEN] Batch Information:
                                   ┌─────────────────────────────────┐
                                   │ Gel Polish — Classic Red × 48   │
                                   │                                  │
                                   │ Batch number:   [___________]   │
                                   │ Expiry date:    [DD/MM/YYYY]    │
                                   │                                  │
                                   │ [No expiry date on this item]   │
                                   │ [Scan batch label]              │
                                   └─────────────────────────────────┘

Operator reads batch number        Operator types batch number from
from outer carton or bottle.       the bottle / carton.
Operator reads expiry date.        Operator enters expiry date.

                                   WMS checks:
                                   - Expiry date must be in the future
                                   - Expiry date must be > 30 days away
                                     (configurable — see BD-02)
                                   - If expiry < 30 days: WARN
                                     "Near-expiry stock — supervisor
                                     notification sent."
                                   - If expiry in past: BLOCK
                                     "Expired stock cannot be received.
                                     Mark as quarantine."
```

**What if the same SKU arrives in two batches?**

```
                                   [SCREEN] "A different batch of
                                   Gel Polish Classic Red has already
                                   been received in this delivery.
                                   Do you want to add a second batch?"
                                   [Yes — add new batch] [No — same batch]

                                   If Yes: second batch line created.
                                   Each batch is tracked separately
                                   through to putaway and picking.
```

**Products without an expiry date (e.g. accessories):**

Operator taps "No expiry date on this item." WMS records `expiry_date = null` and sets a `no_expiry_confirmed = true` flag. This prevents the system from ever chasing an expiry date for this product type again.

---

### Phase 1D: Unknown Product Flow

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Operator scans barcode.            WMS: "Barcode XXXX not recognised."
                                   ┌─────────────────────────────────┐
                                   │ ⚠ Unknown barcode               │
                                   │ 5901234599999                   │
                                   │                                  │
                                   │ Options:                         │
                                   │ [Search by SKU or name]         │
                                   │ [Create new product]            │
                                   │ [Quarantine — cannot identify]  │
                                   └─────────────────────────────────┘

Option A — Search:
Operator types partial name or     WMS shows matching products.
SKU. Selects correct product.      Operator selects → proceeds to
                                   quantity and batch entry.
                                   WMS records: "identified manually"
                                   flag on this line.

Option B — Create new product:
Operator enters name, SKU,         WMS creates product with
UOM, barcode.                      status = "pending review".
                                   Supervisor notified.
                                   Stock received but held as
                                   "unconfirmed product" until
                                   supervisor approves.

Option C — Quarantine:
Operator sets aside the items.     WMS creates a quarantine line:
                                   description, quantity, photo (opt).
                                   Supervisor notified immediately.
                                   Stock cannot be put away until
                                   supervisor resolves.
```

---

### Phase 1E: Condition Flagging (per line)

After batch entry, the operator records condition.

```
                                   [SCREEN] Condition:
                                   ┌─────────────────────────────────┐
                                   │ Gel Polish — Ballet Pink × 12   │
                                   │                                  │
                                   │ Condition:                       │
                                   │ ● All good                      │
                                   │ ○ Some damaged                  │
                                   │ ○ All damaged / reject           │
                                   │                                  │
                                   │ [All good selected]             │
                                   └─────────────────────────────────┘

Operator notices 2 damaged         Operator selects "Some damaged".
bottles of Ballet Pink.
                                   [SCREEN] Damaged quantity prompt:
                                   ┌─────────────────────────────────┐
                                   │ How many are damaged?           │
                                   │ Damaged: [2]  Good: [10]        │
                                   │                                  │
                                   │ Damaged items: [Quarantine]     │
                                   │                [Return to supplier]│
                                   │                [Dispose]        │
                                   └─────────────────────────────────┘

                                   WMS records:
                                   - 10 × Ballet Pink (good) → receiving
                                   - 2 × Ballet Pink (damaged) → quarantine
                                   Supervisor notified.
                                   Credit note flag raised (optional).
```

---

### Phase 1F: Quantity Discrepancy

```
PHYSICAL                          WMS
─────────────────────────────     ─────────────────────────────────────────
Operator counts Base Coat:
only 20 bottles present.          Operator enters quantity: 20.
Delivery note says 24.
                                   If a PO or expected delivery exists:
                                   WMS: "Delivery note shows 24.
                                   You have entered 20.
                                   Short by 4 units.
                                   ┌─────────────────────────────────┐
                                   │ ⚠ Quantity discrepancy          │
                                   │ Expected: 24  Received: 20      │
                                   │                                  │
                                   │ [Confirm 20 — accept short]     │
                                   │ [Recount]                       │
                                   │ [Mark as dispute]               │
                                   └─────────────────────────────────┘

Operator recounts.                 Same result. Selects "Confirm 20".
                                   WMS records: received_qty=20,
                                   expected_qty=24, discrepancy=-4,
                                   discrepancy_type="shortage".
                                   Supervisor notified.
                                   Credit note flag raised.
```

---

### Phase 1G: Receiving Summary and Supervisor Approval

Once all lines are processed:

```
                                   [SCREEN] Delivery Summary:
                                   ┌─────────────────────────────────┐
                                   │ Delivery: DN-2026-0042          │
                                   │ Supplier: NeoNail UK            │
                                   │ Received: 3 Nov 2026 10:34      │
                                   │                                  │
                                   │ Lines:                          │
                                   │ ✓ Gel Polish Red  Batch A  48u  │
                                   │ ✓ Gel Polish Red  Batch B  12u  │
                                   │ ✓ Base Coat  Batch C  20u (⚠-4) │
                                   │ ✓ Top Coat   Batch D  24u       │
                                   │ ⚠ Ballet Pink  10u + 2 damaged  │
                                   │ ? Nail Files (4504) 3 boxes     │
                                   │                                  │
                                   │ Exceptions: 3                   │
                                   │ [Submit for approval]           │
                                   └─────────────────────────────────┘

Operator submits.                  WMS notifies supervisor.
                                   Stock status: "received — pending
                                   approval". Cannot be put away yet.

Supervisor reviews on desktop      [SCREEN] Supervisor review panel:
or their own device.               - Line-by-line review
                                   - Can approve each line individually
                                   - Can query / reject a line
                                   - Can approve exceptions
                                   - Signs off with their credentials

                                   On approval:
                                   - Stock status → "approved — ready
                                     for putaway"
                                   - Stock quantities now exist in the
                                     system (but not yet allocated to bins)
                                   - Audit record created: who received,
                                     who approved, timestamps
```

---

## Section 2 — Scanner-Based Receiving Screen Sequence

The following shows the complete screen flow on a handheld device (portrait orientation, large touch targets).

### Screen S1 — Start

```
┌────────────────────────────┐
│  MP WMS                    │
│  ─────────────────────     │
│  GOODS RECEIVING           │
│                            │
│  [🔍 Scan delivery QR]    │
│                            │
│  ─── or ───                │
│                            │
│  [+ New Delivery]          │
│                            │
│  Recent: DN-2026-0041 ›   │
│          DN-2026-0039 ›   │
└────────────────────────────┘
```

### Screen S2 — Delivery Header

```
┌────────────────────────────┐
│  ← New Delivery            │
│  ─────────────────────     │
│  Supplier:                 │
│  [NeoNail UK          ▾]   │
│                            │
│  Delivery Note No.:        │
│  [_____________________]   │
│                            │
│  Vehicle / Driver ref:     │
│  [_____________________]   │
│  (optional)                │
│                            │
│  [Continue →]              │
└────────────────────────────┘
```

### Screen S3 — Scan Product

```
┌────────────────────────────┐
│  ← DN-2026-0042  Line 1   │
│  ─────────────────────     │
│                            │
│  ┌──────────────────────┐  │
│  │                      │  │
│  │   SCAN BARCODE       │  │
│  │   or enter SKU       │  │
│  │                      │  │
│  └──────────────────────┘  │
│                            │
│  [⌨ Type SKU / name]      │
│                            │
│  [⚠ No barcode on item]   │
│                            │
│  Lines so far: 0           │
└────────────────────────────┘
```

### Screen S4 — Product Confirmed

```
┌────────────────────────────┐
│  ✓ PRODUCT FOUND           │
│  ─────────────────────     │
│  Gel Polish                │
│  Classic Red               │
│                            │
│  SKU: GP-RED-001           │
│  UOM: each                 │
│                            │
│  Quantity received:        │
│  ┌──────────────────────┐  │
│  │         48           │  │
│  │   [−]        [+]     │  │
│  └──────────────────────┘  │
│                            │
│  [Next →]                  │
└────────────────────────────┘
```

### Screen S5 — Batch and Expiry

```
┌────────────────────────────┐
│  BATCH INFORMATION         │
│  Gel Polish Classic Red    │
│  ─────────────────────     │
│                            │
│  Batch number:             │
│  [🔍 Scan or type]        │
│  [_____________________]   │
│                            │
│  Expiry date:              │
│  [DD] / [MM] / [YYYY]      │
│                            │
│  ─── or ───                │
│  [☐ No expiry on this item]│
│                            │
│  [Next →]                  │
└────────────────────────────┘
```

### Screen S6 — Condition

```
┌────────────────────────────┐
│  CONDITION                 │
│  Gel Polish Classic Red    │
│  × 48, Batch A             │
│  ─────────────────────     │
│                            │
│  ● All items good          │
│  ○ Some items damaged      │
│  ○ All damaged / reject    │
│                            │
│  [Confirm this line ✓]     │
└────────────────────────────┘
```

### Screen S7 — Line Confirmed / Scan Next

```
┌────────────────────────────┐
│  ✓ LINE RECORDED           │
│  ─────────────────────     │
│                            │
│  Gel Polish Classic Red    │
│  48 × each  |  Batch A    │
│  Exp: 31/12/2027           │
│  Condition: Good           │
│                            │
│  Lines recorded: 1         │
│                            │
│  [Scan next product]       │
│  [Review all lines]        │
│  [Finish & submit]         │
└────────────────────────────┘
```

### Screen S8 — No Barcode Alternative

```
┌────────────────────────────┐
│  ⚠ NO BARCODE              │
│  ─────────────────────     │
│  Search by name or SKU:    │
│  [_____________________]   │
│                            │
│  Results:                  │
│  > Nail Files  SKU: 4504   │
│  > Nail Buffer  SKU: 4510  │
│                            │
│  [Create new product]      │
│  [Quarantine — unknown]    │
└────────────────────────────┘
```

### Screen S9 — Supervisor Dashboard (Desktop)

```
┌─────────────────────────────────────────────────────┐
│  MP WMS  |  Goods Receiving  |  Pending Approval    │
├─────────────────────────────────────────────────────┤
│  DN-2026-0042  |  NeoNail UK  |  3 Nov 10:34        │
│  Received by: Dean  |  6 lines  |  3 exceptions     │
│                                                     │
│  ┌─────────────────────────────────────────────┐   │
│  │ Line │ Product          │ Qty │ Batch │ OK? │   │
│  │  1   │ Gel Polish Red   │ 48  │ A     │ [✓] │   │
│  │  2   │ Gel Polish Red   │ 12  │ B     │ [✓] │   │
│  │  3   │ Base Coat        │ 20  │ C  ⚠ │ [✓] │   │
│  │  4   │ Top Coat         │ 24  │ D     │ [✓] │   │
│  │  5   │ Ballet Pink      │ 10  │ E  ⚠ │ [✓] │   │
│  │  6   │ Nail Files 4504  │ 3bx │ ?  ⚠ │ [✓] │   │
│  └─────────────────────────────────────────────┘   │
│                                                     │
│  Exceptions requiring action:                       │
│  ⚠ Base Coat: short 4 units — [Approve] [Query]    │
│  ⚠ Ballet Pink: 2 damaged — [Quarantine] [Return]  │
│  ⚠ Nail Files: batch unknown — [Approve] [Hold]    │
│                                                     │
│  [Approve all confirmed lines]  [Reject delivery]   │
└─────────────────────────────────────────────────────┘
```

---

## Section 3 — Batch and Expiry Management

### 3.1 How Batches Are Identified

Every quantity of stock received must be associated with a batch record. A batch is uniquely identified by:

- Product (SKU)
- Supplier batch number (printed on outer carton or bottle)
- Expiry date (or confirmed as no-expiry)
- Received date

The batch number is typically a code like `B2026001` or `LOT241115` printed on the outer carton. If a batch number is not printed, the operator generates one: `IMP-{YYYYMMDD}-{LINE}` (e.g. `IMP-20261103-003`). This internal batch number is recorded and printed on an internal sticker applied to the carton.

### 3.2 Multiple Batches of the Same SKU

This is the scenario with Gel Polish Classic Red arriving in two batches:

```
Received in delivery DN-2026-0042:
- GP-RED-001  Batch: A2026-047  Qty: 48  Expiry: 31/12/2027
- GP-RED-001  Batch: A2026-031  Qty: 12  Expiry: 30/06/2027

These are two separate batch records in the WMS.
They will be stored in separate bins (or with clear physical separation).
When picking, the system always selects Batch A2026-031 FIRST
(it expires sooner — FEFO rule).
```

### 3.3 FEFO (First Expired, First Out) Rule

When a pick list is generated for Gel Polish Classic Red:
1. WMS looks up all batch records for GP-RED-001 in storage.
2. Sorts by expiry date, oldest first.
3. Pick list instructs operator: "Go to bin A-03-02, take from Batch A2026-031."
4. If that bin is empty, WMS falls through to next-oldest batch.

**The operator cannot override FEFO without supervisor approval.** A forced override (e.g. a customer requesting a specific batch) is recorded with a reason and the supervisor's approval.

### 3.4 Products Without Expiry Dates

Some products (accessories, tools) may have no expiry date. The operator ticks "No expiry on this item" during receiving. WMS sets `no_expiry_confirmed = true`. The product is still batch-tracked (for recall purposes) but FEFO is replaced by FIFO (First In, First Out — oldest received batch goes out first).

### 3.5 Near-Expiry Stock

WMS should generate daily alerts for:
- Stock expiring within 90 days (configurable): **advisory alert**
- Stock expiring within 30 days: **warning — supervisor reviews**
- Stock with expiry < today: **blocked from picking** — automatic quarantine

### 3.6 Recall Traceability

Because every unit received is recorded against a batch number, a recall query can be answered:

> *"Which orders contained units from Batch A2026-031 of Gel Polish Classic Red?"*

The WMS traces: `batch → stock movements → pick lines → orders → despatch records → customer addresses`.

This traceability chain cannot work without batch tracking being in place from the first goods receipt.

---

## Section 4 — Location and Bin Allocation

### 4.1 Proposed Location Numbering System

A practical location system for a small-to-medium cosmetics warehouse uses a three-part code:

```
ZONE - AISLE - SHELF - POSITION

Example: A-02-C-04

Breakdown:
  A    = Zone A (e.g. ambient storage)
  02   = Aisle 2
  C    = Shelf level C (A=bottom, B=middle, C=top)
  04   = Position 4 from left on that shelf

Alternative compact format:
  A0204 (same information, shorter barcode)
```

**Zone types:**
- A = Ambient (standard shelf storage)
- C = Cold (if refrigeration is needed — note BD-05)
- Q = Quarantine (physically separate area)
- R = Returns (inspection area)
- D = Dispatch (staging area)
- I = Inbound (receiving area — temporary)

**A label on each bin contains:**
1. Human-readable code: `A-02-C-04`
2. Barcode (Code 128 or QR code) encoding the same code
3. Product name of any fixed-location product (optional — for narrow products)

**Bins should be physically labelled before the WMS is populated.** Labels are printed from the WMS location management screen once locations are created.

### 4.2 Location Types

| Type | Description | Example |
|---|---|---|
| Shelf | Standard shelf position | Loose bottles in a row |
| Bin | Divided shelf segment with physical dividers | Individual SKU per bin |
| Bay | Large floor-level pallet position | Full pallet or heavy items |
| Zone | An area with multiple shelves/bins | The quarantine area |

### 4.3 System Location Recommendation During Putaway

After receiving is approved, the operator starts putaway. For each approved batch, the WMS recommends a storage location using these rules (in order):

1. **Existing location for this SKU** — if Gel Polish Classic Red is already stored in A-02-C-04, send new stock there (same product consolidation).
2. **Existing location for this SKU, same batch** — send to same bin as existing batch of same number.
3. **Warning if mixing batches in same bin** — if Batch A is in A-02-C-04 and Batch B is about to go there, WMS warns: "This bin already contains a different batch. Recommend bin A-02-C-05."
4. **Nearest empty bin to current location** — if no prior location, system recommends nearest suitable empty bin.
5. **Operator can override and choose a different bin** — this is recorded, and supervisor is notified if the chosen bin already contains a different product.

### 4.4 Putaway Screen Sequence (Scanner)

```
Screen P1: Select batch to put away
┌────────────────────────────┐
│  PUTAWAY                   │
│  DN-2026-0042 approved ✓   │
│  ─────────────────────     │
│  Items awaiting putaway:   │
│  > Gel Polish Red  48+12u  │
│  > Base Coat       20u     │
│  > Top Coat        24u     │
│  > Ballet Pink     10u     │
│  > Nail Files      3 boxes │
│                            │
│  [Start putaway]           │
└────────────────────────────┘

Screen P2: Recommended location
┌────────────────────────────┐
│  PUT AWAY                  │
│  Gel Polish Red × 48       │
│  Batch A  Exp 31/12/2027   │
│  ─────────────────────     │
│  Suggested location:       │
│  ┌──────────────────────┐  │
│  │   A - 02 - C - 04   │  │
│  └──────────────────────┘  │
│                            │
│  [🔍 Scan bin to confirm] │
│  [Choose different bin]    │
└────────────────────────────┘

Screen P3: Confirm bin scan
(Operator walks to A-02-C-04 and scans the bin barcode)
┌────────────────────────────┐
│  ✓ BIN CONFIRMED           │
│  A-02-C-04                 │
│  ─────────────────────     │
│  Placing:                  │
│  Gel Polish Red × 48       │
│  Batch A  Exp 31/12/2027   │
│                            │
│  [Confirm putaway ✓]       │
│  [Wrong bin — rescan]      │
└────────────────────────────┘

Screen P4: Putaway confirmed
┌────────────────────────────┐
│  ✓ STOCK RECORDED          │
│  ─────────────────────     │
│  A-02-C-04                 │
│  GP-RED-001  ×48  Batch A  │
│                            │
│  [Next item →]             │
│  [View all putaway]        │
└────────────────────────────┘
```

### 4.5 Bin Full or Unsuitable

```
Operator scans bin A-02-C-04.

WMS check:
- Is bin at capacity? (if capacity tracking enabled — see BD-03)
  → If yes: "Bin A-02-C-04 is full. Recommend A-02-C-05."
- Does bin contain a different product?
  → If yes: "Bin A-02-C-04 contains Top Coat. 
    This is Gel Polish Red.
    [Continue anyway] [Choose different bin]"
  → "Continue anyway" requires supervisor approval and logs the mixed-bin event.
- Does bin contain same SKU but different batch?
  → If yes: "Bin A-02-C-04 already contains Gel Polish Red Batch B.
    FEFO risk: batches will be mixed.
    Recommend A-02-C-05 to keep batches separate."
```

---

## Section 5 — Exception Handling

### 5.1 Recovery Procedure Matrix

| Exception | Detection | Recovery | Who acts | Record created |
|---|---|---|---|---|
| Barcode not recognised | WMS shows "Unknown barcode" | Search by name, create product, or quarantine | Operator → supervisor for new product | Exception log entry |
| Wrong product scanned | Operator corrects before confirming | Re-scan or search | Operator | No record if corrected immediately |
| Wrong quantity entered | Operator corrects before confirming | Edit quantity field | Operator | No record if corrected immediately |
| Quantity confirmed wrong (after submission) | Supervisor review detects it | Supervisor rejects line → operator re-enters | Supervisor + operator | Rejected line + resubmission logged |
| Duplicate stock entry (same batch submitted twice) | WMS detects same batch already received in same delivery | Warning: "Batch B already recorded for this delivery. Did you mean to add more?" | Operator confirms or cancels | Duplicate flag on record |
| Damaged goods | Operator flags during condition step | Damaged qty to quarantine, good qty to stock | Operator + supervisor approval | Quarantine record + damage note |
| Unknown product cannot be identified | Operator selects quarantine | Quarantine record created, supervisor notified | Supervisor investigates | Quarantine entry |
| Expired stock received | WMS blocks when expiry date entered | Operator must quarantine or reject | Supervisor decides accept/return/dispose | Quarantine / rejection record |
| Near-expiry (within 30 days) | WMS warns | Supervisor notified, can proceed with approval | Supervisor approval | Warning + approval recorded |
| Quantity discrepancy against delivery note | WMS shows expected vs received | Operator confirms discrepancy, supervisor reviews | Supervisor flags for credit note | Discrepancy record |
| Network failure mid-receiving | Data loss risk | Session must be resumable — WMS saves each confirmed line immediately | Operator resumes from last saved line | Partial delivery record with resume flag |
| Scanner battery dies / scanner fails | Operator cannot scan | Fall back to desktop data entry (same WMS screens) | Operator moves to desktop/tablet | Same records — no data difference |
| Operator interrupted mid-delivery | Partial delivery record | WMS shows delivery as "in progress" — another operator can continue | Any authorised operator | Handover noted in delivery record |
| Supervisor unavailable for approval | Stock sits in "received — pending approval" | Delivery held; stock not available for picking. Designate a deputy (see BD-06) | Deputy supervisor | Approval record shows deputy |

### 5.2 No Silent Stock Changes

**Every stock movement — including partial receipts, quarantines and discrepancies — must create a record.** No quantity should be added or removed from the stock ledger without:
1. An identifiable transaction (who, what, when, why).
2. A reference to the source document (delivery note, adjustment authorisation, quarantine order).
3. Supervisor approval for any non-standard event.

This is enforced by the system only writing to the stock ledger on explicit confirmation, never automatically.

---

## Section 6 — Stock Ledger and Data Structures

> **These are proposed structures for development.** None of this exists in the current codebase. Approval is required before implementation.

### 6.1 Proposed Tables

```
deliveries
  id, fulfilment_client_id, supplier_id, delivery_note_ref,
  arrived_at, received_by (user_id), status (in_progress | pending_approval
  | approved | rejected | partial), approved_by, approved_at, notes

delivery_lines
  id, delivery_id, product_id, batch_id, quantity_expected,
  quantity_received, condition (good | mixed | all_damaged),
  quantity_damaged, discrepancy_type (none | shortage | overage),
  discrepancy_notes, manually_identified (bool), status (pending | approved | rejected)

batches
  id, fulfilment_client_id, product_id, batch_number, expiry_date,
  no_expiry_confirmed (bool), received_at, notes

stock_entries
  id, fulfilment_client_id, batch_id, location_id,
  quantity, status (available | quarantine | reserved | dispatched)

stock_movements
  id, stock_entry_id, movement_type (receipt | putaway | pick | adjustment
  | quarantine | transfer | disposal | return), quantity_change,
  reference_type (delivery | pick_list | adjustment | return),
  reference_id, performed_by (user_id), approved_by, performed_at, notes

quarantine_items
  id, fulfilment_client_id, product_id, batch_id, location_id,
  quantity, reason (damaged | expired | unknown | investigation),
  status (open | resolved | disposed | returned_to_supplier),
  raised_by, resolved_by, raised_at, resolved_at, notes
```

### 6.2 Stock Status Flow

```
RECEIVED (delivery_lines.status = pending)
  ↓ Supervisor approves
APPROVED — AWAITING PUTAWAY (stock_entries created, status = available,
                              but location_id = null)
  ↓ Operator scans bin and confirms putaway
IN LOCATION (stock_entries.location_id set, status = available)
  ↓ Order placed, stock reserved
RESERVED (stock_entries.status = reserved, linked to pick_list)
  ↓ Operator picks and confirms
PICKED → PACKED → DISPATCHED (stock_entries.status = dispatched,
                               quantity deducted via stock_movement)

Alternative paths:
APPROVED → QUARANTINE (damaged, expired, or investigation)
  ↓ Supervisor resolves
QUARANTINE → DISPOSAL / RETURN TO SUPPLIER / BACK TO AVAILABLE

AVAILABLE → ADJUSTMENT (supervisor-authorised write-up or write-down)
```

### 6.3 Audit Requirements

Every `stock_movements` record must be immutable — records cannot be updated or deleted, only superseded by a correcting movement. The audit trail must show:
- Original transaction
- Who approved it
- Any correction applied and why
- Supervisor who authorised the correction

---

## Section 7 — Visual Screen Concepts

> These are design concepts only. They represent the intended user experience. They are not implemented.

### 7.1 Overall Receiving Flow (Process Diagram)

```
DELIVERY ARRIVES
      │
      ▼
[S1] Open Goods Receiving
      │
      ▼
[S2] Enter delivery header
     (supplier, delivery note ref)
      │
      ▼
[S3] Scan first product barcode
      │
      ├─── Recognised ──────────────────────────────────┐
      │                                                  ▼
      │                                           [S4] Confirm product
      └─── Not recognised ──► [S8] Search/         quantity, batch,
                               Create/Quarantine   condition
                                                         │
                                                         ▼
                                                  [S7] Line recorded
                                                         │
                                               ┌─────────┴──────────┐
                                               │                    │
                                         Scan next          Finish delivery
                                         product                    │
                                                                     ▼
                                                           [S9 Desktop]
                                                           Supervisor review
                                                                     │
                                                          ┌──────────┴──────────┐
                                                          │                     │
                                                      Approved              Rejected/Query
                                                          │
                                                          ▼
                                                   Stock status:
                                                   Ready for putaway
                                                          │
                                                          ▼
                                                   [P1] Start putaway
                                                          │
                                                          ▼
                                              For each batch/product:
                                             [P2] WMS recommends bin
                                                          │
                                                          ▼
                                             [P3] Operator scans bin
                                                          │
                                                          ▼
                                             [P4] Putaway confirmed
                                                          │
                                                          ▼
                                                 Stock available
                                                  for orders ✓
```

### 7.2 Desktop Supervisor View — Key Information

The supervisor's receiving review screen must show, without scrolling:

1. **Who received it** — operator name and time
2. **What was received** — all lines with quantities and batches
3. **All exceptions** — highlighted in amber/red with required action
4. **Quick approve** — single button to approve all unexceptional lines
5. **Individual line approval** — for exceptions, approve each separately
6. **Discrepancy summary** — short deliveries and credit note flags

### 7.3 Handheld Operator View — Design Principles

1. **One task per screen** — never ask for multiple things at once.
2. **Large touch targets** — buttons minimum 44px × 44px (gloved hands).
3. **Confirmation before commit** — always show a "Confirm" step before writing to the database.
4. **Undo within the same session** — operator can go back and correct a line before submitting.
5. **Clear success/failure feedback** — green ✓ on success, red ✗ with explanation on failure.
6. **Readable in warehouse conditions** — high contrast, large font (minimum 16px body).
7. **Works without typing** — all common operations should be achievable by scanning and tapping.

---

## Section 8 — Business Decisions Required Before Development

### BD-01: Batch number mandatory or optional?

**Question:** Must every item received have a recorded batch number, or is batch recording optional for certain product categories?

**Recommended default:** Mandatory for all cosmetic and gel products. Optional (but recommended) for accessories and packaging materials.

**Consequence of making it optional:** If batch recording is optional, traceability and recall capability are compromised for items where operators choose to skip it. This may not meet regulatory requirements for cosmetics distribution.

**Paul/Craig to decide:** Mandatory for all products, or mandatory only for cosmetics/gels?

---

### BD-02: Near-expiry threshold — how many days?

**Question:** At what point should the system warn that received stock is near expiry?

**Recommended default:** Alert at 90 days, block receiving (require supervisor approval) at 30 days.

**Consequence of too-short threshold:** Stock arrives that will expire before it can be sold, but the system accepts it silently.

**Paul/Craig to decide:** What are the thresholds for warning and blocking?

---

### BD-03: Bin capacity tracking — yes or no?

**Question:** Should the WMS track maximum capacity per bin (e.g. a bin holds 24 bottles) and warn when it is full?

**Recommended default:** Yes, but capacity can be left unconfigured initially and added per bin over time.

**Consequence of not tracking:** The system cannot prevent overfilling a bin. Operators must rely on physical judgement.

**Paul/Craig to decide:** Will bin capacities be set up from the start, or deferred?

---

### BD-04: Purchase order matching — required?

**Question:** Should goods receipt be matched against purchase orders raised in an external system (accounting software), or is a delivery note reference sufficient?

**Recommended default:** Delivery note reference only initially. PO matching added later if needed.

**Consequence of no PO matching:** Goods can be received without any prior authorisation. Discrepancies may not be caught until invoice reconciliation.

**Paul/Craig to decide:** Is PO matching needed from the start?

---

### BD-05: Cold storage — does it apply?

**Question:** Do any NeoNail or Fuzion Gel products require temperature-controlled storage?

**Recommended default:** Assume ambient only unless confirmed otherwise.

**Consequence of incorrect assumption:** Products stored at wrong temperature may degrade. If cold storage is required, the location system needs a cold-zone type.

**Paul/Craig to decide:** Are any products temperature-sensitive?

---

### BD-06: Supervisor approval — who is the deputy?

**Question:** If the supervisor is unavailable, who is authorised to approve goods receipts?

**Recommended default:** Any admin-role user can act as deputy supervisor.

**Consequence of no deputy:** Stock sits in "pending approval" indefinitely and cannot be picked.

**Paul/Craig to decide:** Who is the designated deputy for goods receipt approval?

---

### BD-07: Photo attachment during receiving — required?

**Question:** Should operators be able to photograph delivery notes, damaged items and condition issues during receiving?

**Recommended default:** Yes — photo attachment for exceptions (damaged goods, disputes). Optional for routine receipts.

**Consequence of no photos:** Damage disputes with suppliers have no photographic evidence.

**Paul/Craig to decide:** Is photo capture needed, and does the scanning device have a camera?

---

### BD-08: Internal batch number format

**Question:** When a product arrives without a manufacturer batch number (e.g. Nail Files SKU 4504), what format should the WMS-generated internal batch number use?

**Recommended default:** `MP-{YYYYMMDD}-{delivery_line_number}` — e.g. `MP-20261103-006`

**Consequence:** This internal number is printed on a sticker applied to the carton. It must be long enough to be unique but short enough to scan reliably.

**Paul/Craig to decide:** Is this format acceptable, or does MP Transport have an existing batch numbering convention?

---

### BD-09: What happens to the 3 boxes of Nail Files (SKU 4504)?

**Question:** The delivery contains 3 boxes of Nail Files but the number of packs per box is unverified (the demo data says 25 but Paul has not confirmed this). During receiving, should the operator record:

- A. Quantity = 3 (boxes), UOM = box, pack_qty unknown
- B. Quantity = 75 (packs), assuming 25 per box — but unverified
- C. Quantity = unknown until physically counted — receiving blocked until count complete

**Recommended default:** Option A. Receive in the UOM you can count (boxes). Record a note: "Pack quantity unverified — physical count required before this stock can be allocated." A supervisor task is raised to complete the count.

**Paul/Craig to decide:** What should the operator enter for Nail Files before pack size is verified?

---

## Summary of Decisions Needed

| ID | Decision | Urgency |
|---|---|---|
| BD-01 | Batch number mandatory for which products? | HIGH — blocks schema design |
| BD-02 | Near-expiry warning and block thresholds | HIGH — needed for receiving logic |
| BD-03 | Bin capacity tracking from start? | MEDIUM — affects location setup |
| BD-04 | PO matching required? | MEDIUM — affects receiving flow |
| BD-05 | Cold storage products? | MEDIUM — affects location types |
| BD-06 | Supervisor deputy for approvals | HIGH — operational blocker if unanswered |
| BD-07 | Photo capture during receiving? | LOW — affects device choice |
| BD-08 | Internal batch number format | MEDIUM — needed before first receiving |
| BD-09 | Nail Files (4504) — how to receive? | HIGH — specific to immediate NeoNail operation |

---

*This document represents operational design only. No code has been written or modified.*  
*It must be reviewed and approved by Craig and Paul before Phase 3 development begins.*  
*Produced 3 November 2026 — MP Transport WMS Project*
