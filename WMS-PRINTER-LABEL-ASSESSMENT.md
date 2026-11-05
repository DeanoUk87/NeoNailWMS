# WMS Printer and Label Assessment

**Project:** MP Transport Warehouse Management System  
**Date:** 5 November 2026  
**Type:** Equipment assessment and consumables specification  
**Code changes:** NOT AUTHORISED — design and assessment only

---

## Equipment Summary

| Device | Purpose | Status |
|---|---|---|
| Zebra ZD421t (thermal-transfer) | Warehouse location labels (bin, shelf, divider) | New — to be purchased |
| Existing Zebra (model TBC) | Courier shipping labels | Already in use |
| Windows laptop | Print management and WMS administration | Existing |
| MUNBYN AS01P Android handheld scanner | Scanning barcodes in the warehouse | Existing |

---

## 1. Bin and Shelf Label Specification

### Are 75 × 30 mm Labels Suitable?

**Yes — 75 × 30 mm is well-suited for Barton Topstore shelf bins.**

The Topstore bins have a front label holder/lip, typically 50–80 mm wide. A 75 × 30 mm label fits comfortably with margin to spare, is readable at arm's length, and accommodates a Code 128 barcode alongside a human-readable location reference.

Comparison:

| Size | Verdict | Notes |
|---|---|---|
| 50 × 25 mm | Marginal | Barcode becomes small; human-readable text cramped at warehouse reading distance |
| **75 × 30 mm** | **Recommended** | Good barcode height; clear text; fits Topstore bin lip |
| 100 × 30 mm | Also suitable | More space but wastes label material for simple location codes |

**Recommended label size: 75 × 30 mm.**

### Label Content

Each label must show:

```
┌──────────────────────────────────────┐  (75 mm wide)
│  ║║║║║║║║║║║║║║║║║║║║║║║║║║║║║║║║  │
│  ║ Code 128 barcode (encodes A01-S02-B03) ║  │
│                                      │
│      A01 - S02 - B03                 │  (human-readable)
│      Ambient · Shelf 2 · Bin 3       │  (optional descriptor line)
└──────────────────────────────────────┘  (30 mm tall)
```

**Location reference format:** `ZZN-SNN-BNN`

```
ZZ = Zone code (1–2 letters): A = Ambient, Q = Quarantine, I = Inbound, D = Dispatch, R = Returns
N  = Number (zero-padded to 2 digits)
S  = Shelf number within the aisle/rack section
B  = Bin/position number within the shelf

Example: A01-S02-B03
  Ambient zone, Rack/Section 1, Shelf 2, Bin position 3
```

This format encodes into a compact Code 128 barcode, is readable without magnification, and is memorable for warehouse staff.

### Barcode Format

**Use Code 128 (Auto mode).**

| Format | Recommendation | Reason |
|---|---|---|
| Code 128 | ✅ Recommended | Compact, high-density, alphanumeric, universally supported by Zebra printers and the MUNBYN AS01P scanner |
| QR Code | Acceptable alternative | Scannable at any angle; works better in low-light; requires more label area for same data |
| Code 39 | Not recommended | Less compact; uppercase only; more scan errors |
| EAN/UPC | Not applicable | For products only, not locations |

A Code 128 barcode encoding `A01-S02-B03` (11 characters) requires approximately 20–25 mm of width at standard density and an 8 mm quiet zone on each side. On a 75 mm label this leaves ~20 mm for the quiet zones and human-readable text — fits comfortably.

Minimum recommended barcode height: 10 mm (this gives good scan reliability on the MUNBYN AS01P at the distances typical in a warehouse aisle).

### Label Material and Ribbon

This is for **permanent warehouse labels** on plastic bins and metal shelving. The environment is indoor, ambient temperature, low humidity. Labels must resist handling and occasional cleaning spray.

**Label material:** Polypropylene (PP) or polyester (PET) — not standard paper.

- Paper labels degrade when exposed to moisture or cleaning products.
- Polypropylene resists moisture, is durable for 2–3 years under normal conditions.
- Polyester is harder-wearing and lasts 5+ years but costs more.
- For Topstore bins in a dry warehouse: polypropylene is the practical choice.

**Ribbon type:** Wax/resin blend or full resin.

The ZD421t is a thermal-transfer printer. For polypropylene or polyester labels, a standard wax ribbon will not adhere properly — it smears or flakes off. A wax/resin blend or full resin ribbon is required.

| Ribbon type | For polypropylene | For polyester | Cost |
|---|---|---|---|
| Wax | ❌ Not suitable | ❌ Not suitable | Low |
| Wax/resin blend | ✅ Suitable | ✅ Acceptable | Medium |
| Full resin | ✅ Suitable | ✅ Best | Higher |

**Recommendation for Topstore bin labels: polypropylene label stock + wax/resin blend ribbon.**

---

## 2. Consumables Shopping List

Based on the Zebra ZD421t with 75 × 30 mm labels:

### Labels

| Item | Specification | Notes |
|---|---|---|
| Label material | Polypropylene, 75 mm × 30 mm, core 25 mm (1") or 38 mm (1.5") — confirm ZD421t core size | White face, permanent adhesive |
| Roll size | 500–1,000 labels per roll | A warehouse of 200–500 bins needs approximately 200–500 labels initially plus spares |
| Quantity to order | 2–3 rolls for initial setup + 1 roll spares | Print test labels first; verify fit on Topstore bins before bulk ordering |

**Example compatible label stock:** Zebra Z-Ultimate 3000T polypropylene (Zebra part ZipShip or equivalent), or compatible third-party stock. Confirm the exact ZD421t media width capacity (the ZD421t supports 25–104 mm width media).

### Ribbons

| Item | Specification | Notes |
|---|---|---|
| Ribbon type | Wax/resin blend (or full resin) | For polypropylene labels |
| Ribbon width | Match label width: 83 mm (ribbon must be slightly wider than label) | Standard ZD421t compatible width |
| Ribbon length | 300–450 m per roll | Typical ZD421t ribbon length |
| Quantity | 2 ribbons for initial run | Ribbon life depends on print volume |

**Example compatible ribbon:** Zebra 3200 Wax/Resin (Zebra part 05095GS08307 or similar), or Zebra 5095 Resin for a more durable print.

### ZD421t Printer Configuration

| Setting | Value |
|---|---|
| Print method | Thermal transfer |
| Media type | Label (non-continuous) |
| Label width | 75 mm |
| Label height | 30 mm |
| Print speed | 4–6 inches/second (default) |
| Darkness | 15–18 (adjust until barcode scans cleanly) |
| Barcode type | Code 128 |
| Printer language | ZPL (Zebra Programming Language) — the WMS will generate ZPL templates |

---

## 3. Divider Compartments — Shared Bin vs Separate Location

The Topstore bins can be divided into 2–4 compartments using removable dividers.

**Question: does each compartment need its own barcode?**

**Answer: Yes, if different batches or SKUs will occupy the same bin.**

The WMS tracks stock at the location level. If two compartments of the same physical bin hold two different batches of the same product (e.g. Gel Polish Classic Red Batch A in left compartment, Batch B in right compartment), and the bin has only one barcode, the WMS cannot distinguish which batch is in which compartment. FEFO enforcement would then depend entirely on the operator picking the correct batch without system guidance — which defeats the purpose of the WMS.

**Two scenarios:**

| Scenario | WMS approach | Label requirement |
|---|---|---|
| Each compartment holds one SKU/batch only | Label the bin as a whole. Location = bin. | One label per bin. |
| Compartments hold different batches or SKUs | Label each compartment separately. Location = compartment. | One label per compartment. |

**Recommendation for NeoNail cosmetics:** Label each compartment separately. Cosmetic products require FEFO by batch. Sharing an unlabelled compartment between two batches introduces the same risk as mixing batches in an unlabelled bin — the system cannot enforce the correct pick order.

**Practical labelling for Topstore bins with dividers:**

```
┌──────────────┬──────────────┐
│  A01-S02-B03 │  A01-S02-B04 │   ← Two compartments, two location codes
│  [barcode]   │  [barcode]   │
└──────────────┴──────────────┘
```

Each divider position gets an adjacent sequential bin number. The labels are 75 × 30 mm, one per slot. When a divider is removed to merge two compartments into one larger bin, the second location code is deactivated in the WMS (not deleted) and the stock is transferred to the first location code.

---

## 4. Creating, Printing and Reprinting Location Barcodes

### Creating a New Location

1. Admin opens **Locations** in the WMS and taps/clicks **New Location**.
2. Enters: warehouse, zone, name (e.g. `A01-S02-B03`), location type (shelf/bin/bay/divider).
3. The WMS auto-generates the location barcode value from the name, or allows a custom barcode string.
4. Saves the record. The location now exists in the WMS with a unique barcode.
5. Admin selects **Print Label** — the WMS sends a ZPL print job to the ZD421t.
6. Label printed. Applied to the bin.

The location does not become operational until the label is physically applied and the barcode scans correctly on the MUNBYN AS01P.

### Reprinting a Damaged Label

Damaged labels must be reprinted without creating a new location record.

1. Admin finds the existing location in the WMS (by name or barcode search).
2. Selects **Reprint Label** — the same ZPL template is sent to the ZD421t with the same barcode value.
3. No new record is created. The location ID, barcode value and all stock associations remain unchanged.
4. The old label is removed and the new label applied.

**The barcode value encodes the location's unique ID, not just its display name.** If the display name is changed (e.g. a renaming exercise), the barcode value and the physical label are also reprinted — but the location's database ID and all stock history are preserved. This prevents the system from "losing" stock when labels are renamed.

---

## 5. Automatic Printer Selection on Windows Laptop

### Objective

Staff should not need to choose a printer. The WMS should automatically route:
- **Bin/location labels → Zebra ZD421t**
- **Shipping labels → Existing Zebra shipping printer**

### Recommended Approach: Two Named Windows Printers

Install both Zebra printers on the Windows laptop using the Zebra ZDesigner or ZPL-compatible drivers. Each printer is given a distinct name in Windows:

- `WMS-Location-Labels` → Zebra ZD421t
- `WMS-Shipping-Labels` → Existing Zebra

The WMS (running in the browser) cannot directly access local printers from a web page — that is a browser security restriction. Two approaches are available:

**Option A: Local Print Service (Recommended)**

Install a lightweight local print service on the Windows laptop. Options:

| Service | Description | Cost |
|---|---|---|
| **Zebra Browser Print** | Free Zebra utility. Allows web pages to send ZPL to any locally installed Zebra printer via a local HTTP server on the laptop. No monthly fee. | Free |
| **QZ Tray** | Open-source Java app. Allows browser-based apps to print to local printers. More flexible but requires Java runtime. | Free (open-source) |
| **PrintNode** | Cloud print relay. Browser app sends jobs to PrintNode servers; they relay to local printers. No local software beyond a small agent. | Subscription (~£5–10/month) |

**Recommendation: Zebra Browser Print.**

It is free, designed specifically for Zebra printers, runs as a background service on Windows, and exposes a local API that the WMS JavaScript can call directly. Configuration:

1. Install Zebra Browser Print on the Windows laptop.
2. Configure it to know that `WMS-Location-Labels` = ZD421t and `WMS-Shipping-Labels` = existing Zebra.
3. The WMS, when generating a location label, calls `http://localhost:9100/write` (Zebra Browser Print's local API) with the ZPL template pre-configured for the ZD421t.
4. When generating a shipping label, the WMS calls the same API with the shipping printer's name and label template.

Staff see a "Print label" button. They click it. The correct printer receives the job. No printer selection dialogue appears.

**Option B: Direct IP Print (Simpler but less flexible)**

Both Zebra printers are connected via USB or network and assigned static local IP addresses or USB port identifiers. The WMS sends raw ZPL directly to the printer's IP:port (default ZPL port: 9100). 

This works but requires the WMS server (cPanel hosting) to have network access to the printers — which it does not in a cloud/hosted setup. This option only works if the WMS is running locally on the same LAN as the printers, which is not the current architecture.

**For the current architecture (WMS on cPanel hosting, accessed remotely):** Zebra Browser Print running on the Windows laptop is the correct approach.

### Browser Print API — Brief Technical Note

Zebra Browser Print exposes a local REST API on `http://localhost:9100`. The WMS sends a POST request with the ZPL content and the target printer name. The service routes the job to the named printer. No firewall rules are needed (it is local to the laptop). The user must have Zebra Browser Print running when printing is needed; it can be set to start automatically with Windows.

---

## 6. WMS Functionality Assessment

| Function | Current WMS status | Development needed |
|---|---|---|
| Location record creation (data model) | Schema exists (`locations` table) | Yes — no UI for creating/editing locations |
| Location barcode field | `barcode` column exists on `locations` | Yes — UI field, barcode validation, uniqueness check |
| Label print (ZPL generation) | Not implemented | Yes — ZPL template engine + Zebra Browser Print integration |
| Automatic printer routing | Not implemented | Yes — printer name configuration in WMS settings + Browser Print API calls |
| Reprint without duplicate location | Not implemented | Yes — "Reprint" action on existing location record |
| Scanner recognition of location barcode | Not implemented | Yes — scanner input handling in receiving/putaway screens |
| Shipping label printing | Not in scope for WMS (existing Zebra in use) | No — existing workflow continues |

**Information needed about the existing Zebra shipping printer:**

1. Model number (e.g. Zebra ZD420, ZD620, ZT410).
2. How it currently receives print jobs — USB, network, Bluetooth, or via a shipping platform (e.g. Sendcloud, Shipstation)?
3. Current label size for shipping labels.
4. Whether Zebra Browser Print is already installed on the laptop.

This information is needed before designing the automatic printer routing, to confirm that the existing printer can be driven via the same Browser Print service without disrupting the current shipping workflow.

---

## 7. Recommended Consumables Order

Before placing a final order, print 5–10 test labels on each material type and scan them on the MUNBYN AS01P to verify readability.

| Item | Specification | Qty to order |
|---|---|---|
| Label stock | Polypropylene, 75 × 30 mm, permanent adhesive, 25 mm core (verify ZD421t core size), white | 2 rolls (500–1,000 labels/roll) + 1 spare |
| Ribbon | Wax/resin blend, 83 mm × 300 m, compatible with ZD421t | 2 rolls |

**Do not order bulk label stock until:**
- The ZD421t is installed, driver loaded, and a test print confirmed.
- The label size has been physically placed on a Topstore bin and confirmed as the correct fit.
- The barcode has been scanned on the MUNBYN AS01P at typical working distance.
- The model number of the existing Zebra shipping printer is confirmed (to verify no ribbon/media sharing conflict).

---

## 8. Summary of Recommendations

| Decision | Recommendation |
|---|---|
| Label size | 75 × 30 mm |
| Barcode format | Code 128 |
| Label material | Polypropylene (white, permanent adhesive) |
| Ribbon type | Wax/resin blend |
| Compartment labelling | One barcode per compartment when different batches/SKUs will coexist |
| Printer routing | Zebra Browser Print (free) on Windows laptop |
| Label generation | ZPL templates generated by WMS, sent via Browser Print API |
| Reprint approach | Reprint action on existing location record — no new record created |
| Shipping printer | Confirm model and connection method before integrating |

---

*Design and assessment only. No code changes, purchases or printer configuration changes are authorised until this document is reviewed and approved.*  
*Produced 5 November 2026 — MP Transport WMS Project*
