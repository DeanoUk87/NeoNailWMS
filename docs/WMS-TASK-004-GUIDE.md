# WMS Task 004 — Beginner's Guide: Creating a Warehouse Location and Assigning a User

**Who this is for:** Anyone setting up the WMS for the first time.  
**Before you start:** Log in as an admin user at https://nnuk.wms.mp-transport.co.uk

---

## Part 1 — Create a Warehouse

1. In the left sidebar, click **Warehouses & Locations**.
2. Click the **New Warehouse** button (top right).
3. Fill in:
   - **Warehouse Name** — e.g. `MP Transport — Main Warehouse`
   - **Address** — optional (e.g. `Unit 5, Industrial Estate, Wolverhampton`)
   - **Notes** — optional
   - Leave **Active** ticked.
4. Click **Save Warehouse**.

The warehouse now appears in the list.

---

## Part 2 — Add Locations (Bins and Shelves)

1. In the warehouse list, click the location count link (e.g. "0 locations") next to your warehouse.
2. Click **New Location**.
3. Fill in the location details:

   | Field | What to enter | Example |
   |---|---|---|
   | Zone | Select from the dropdown | `A — Ambient Storage` |
   | Location Type | The physical type | `bin` |
   | Section / Rack | The rack or aisle number | `01` |
   | Shelf Level | The shelf number within the rack | `02` |
   | Bin / Position | The position on the shelf | `03` |

4. As you fill in Section, Shelf and Bin, the **Barcode / Reference** field auto-fills. For the example above it would show `A01-S02-B03`.

   > You can edit the barcode manually if needed. It must be unique within the warehouse.

5. Enter a **Display Name** — this is the human-readable label shown on screen: e.g. `Ambient Rack 1 Shelf 2 Bin 3`.

6. If you know the maximum number of units this bin can hold, enter it in **Maximum Capacity**. Leave blank if you don't know yet.

7. Click **Save Location**.

**Repeat for each bin, shelf or zone you need.** Common zones to create first:

| Zone | Code | Purpose |
|---|---|---|
| Ambient Storage | A | Main picking stock |
| Inbound Holding | I | Stock just delivered, awaiting putaway |
| Quarantine | Q | Damaged or suspect stock |
| Dispatch Staging | D | Packed parcels awaiting courier |

---

## Part 3 — What the Barcode Means

The barcode `A01-S02-B03` breaks down as:

```
A   = Zone (Ambient)
01  = Section/Rack number 1
S02 = Shelf 2
B03 = Bin position 3
```

This barcode is printed on the bin label. When an operator scans a bin with the MUNBYN AS01P scanner, the WMS reads this code and knows exactly which bin is being accessed.

> **Note:** Label printing is not yet connected in the WMS. The barcode values are stored and ready. When printer integration is added, clicking a "Print Label" button will send the label directly to the Zebra ZD421t.

---

## Part 4 — Reprint a Damaged Label (future, once printing is set up)

If a bin label becomes damaged or unreadable:

1. Find the location in the WMS (search by barcode or name).
2. Click **Edit** next to the location.
3. Click **Reprint Label** (once printing is connected).

**Important:** This reprints the same barcode — it does not create a new location. The bin's history and stock records are unaffected.

---

## Part 5 — Create a New User

1. In the left sidebar, click **User Management** (visible to admins only).
2. Click **New User**.
3. Fill in:

   | Field | Notes |
   |---|---|
   | Full Name | e.g. `Jane Smith` |
   | Email Address | e.g. `jane@mp-transport.co.uk` |
   | Role | See role descriptions below |
   | Initial Password | Minimum 8 characters. The user should change this on first login. |

4. Click **Create User**.

The user can now log in at https://nnuk.wms.mp-transport.co.uk/login.

### Role Descriptions

| Role | What they can do |
|---|---|
| **Operator** | View products, receive picking instructions. Cannot edit products, warehouses or users. |
| **Supervisor** | Everything an Operator can do, plus approve exceptions (damaged stock, discrepancies), view reports. |
| **Admin** | Full access including user management, warehouse setup, product editing and CSV import. |

---

## Part 6 — Change a User's Role or Deactivate an Account

1. Click **User Management** in the sidebar.
2. Find the user and click **Edit**.
3. Change the **Role** dropdown and click **Save Changes**.

   > **Note:** An admin cannot change their own role. This prevents accidentally locking yourself out.

4. To deactivate an account (e.g. when a staff member leaves), untick **Account active** and click **Save Changes**. The user can no longer log in. Their history is preserved.

---

## Part 7 — Send a Password Reset

If a user forgets their password:

1. Click **User Management** → find the user → click **Edit**.
2. Click **Send Password Reset Email**.
3. The user receives a secure link to set a new password. No password is revealed to anyone.

---

## Limitations and Outstanding Issues

| Item | Status |
|---|---|
| Label printing | Not connected. Barcode values are stored. Requires Zebra Browser Print setup (see WMS-PRINTER-LABEL-ASSESSMENT.md). |
| Bin capacity enforcement | Stored but not yet enforced during putaway (Phase 3 feature). |
| Zone reclassification guard | UI allows zone changes freely. The safety check (block if stock present) is designed but not yet wired (Phase 3 feature). |
| Receiving / putaway | Not built. This is Phase 3. |
| Stock quantities | Not tracked yet. Products are catalogued but no quantities exist. |
| Shopify inventory sync | Not built. Phase 5. |
| Supplier management | Not built. Phase 3. |
| Self-registration | Permanently disabled. All users are provisioned by an admin. |
