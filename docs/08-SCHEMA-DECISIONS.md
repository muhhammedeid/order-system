# Wholesale Order System — Schema Decisions

Technical/schema decisions that deviate from, or refine, `05-DATABASE-SCHEMA.md`. These are engineering choices, not business requirements.

## Revision R01/R02 — current order/product rules (supersedes conflicting statements below)

- Order statuses are `new`, `confirmed`, `partially_delivered`, `delivered`, `cancelled`. `exported` is not a lifecycle status; existing `exported` rows were normalized to `confirmed` (migration `2026_09_19_100300`). Export is an action and remains pending Revision R04.
- `new → confirmed` and `new → cancelled` are the only manual transitions. `confirmed` orders cannot be cancelled; delivery actions move them to `partially_delivered`/`delivered`.
- No order lifecycle step changes `available_quantity`. It is an Admin-maintained reference only; stock-based ordering restrictions and stock displays were removed.
- `order_items.delivered_quantity` starts at 0; remaining quantity is derived as `quantity - delivered_quantity`; `0 <= delivered_quantity <= quantity` is model- and database-enforced.
- `order_items.product_variant_id` is the operational variant reference for Admin editing and delivery tracking; snapshots remain historical.
- A `new` order is editable in Admin: product, variant, optional size and quantity. Unchanged variants keep their snapshots; changed/new items rebuild snapshots from trusted data; `total_quantity` is recalculated. `delivered_quantity` and `status` are never accepted from payloads.
- Sizing is optional per product via `products.size_enabled` (default `false`). When disabled, variant `size` is `NULL`; when enabled, size is required.
- Coloring is optional per product via `products.color_enabled` (default `true`, preserving existing behavior). When disabled, variant `color` is `NULL`; when enabled, color is required. The two flags are independent and all four combinations are supported.
- Variant uniqueness is `unique(product_id, color_key, size_key)` with generated `color_key = COALESCE(color, '')` and `size_key = COALESCE(size, '')`; the generated keys keep `NULL` dimensions unique, so a product with both dimensions disabled has exactly one meaningful variant. `order_items.color`/`order_items.size` are nullable snapshots and are never rewritten for historical orders.
- A public product must always have a price: `price_visibility = public` with a blank price is rejected by the `Product` model on save, so Admin, import, and any other write path share the same enforcement.
- Product/customer imports validate each row and never bypass the rules above (for example, an existing `request_price` product with no stored price cannot be imported as `public` without a price).

## P05-W01/W02 — Import semantics

- **Customer Code is the import identity key:** non-blank code updates the existing customer (provided non-empty fields) or creates one; blank code + phone matching an existing customer = invalid row (no phone-based merging); blank code + new phone = new customer without code. Phone stays indexed, never unique.
- **Product Code is the import matching key:** exists → update (existing slug preserved, never regenerated), else create (slug from name; collision = invalid row).
- **request_price price semantics (approved correction):** hidden ≠ absent. `request_price` may store an internal price; blank price on an existing product never erases a stored price; blank price on a NEW `request_price` product → NULL. Public products require a valid price (≥ 0) whenever they are saved; this is enforced by the model for every write path (Admin form, import, future server-side writes). A missing price on an existing public product leaves the stored price untouched (it can never legitimately be NULL anyway).
- **Per-row transactions:** each row is its own DB transaction; failures roll back fully (including newly-created categories) and are reported without blocking other rows. No whole-file transaction.
- **Header contract:** exact header names required; missing core headers reject the file. No fuzzy matching/aliases/mapping UI.
- Duplicate codes within a file: first occurrence processes; subsequent occurrences are invalid rows.
- Excel package: `maatwebsite/excel` v3.1, synchronous only (no queues).
- **P05-W03 Order Export is DEFERRED** until the real accounting-system import template is provided. `exported` was removed as an order status in R01 (export is an action); no order-export implementation exists and Revision R04 owns it. Order status is never mutated by exporting.

## P04-W02 — Order item variant reference (approved deviation, superseded by R01/R02)

The original `order_items` schema stores only `product_id` plus historical snapshots (code, name, color, size, price fields). Snapshot text (`product_id + color + size`) cannot be trusted as a live reference.

- `order_items.product_variant_id` (nullable, FK → `product_variants.id`, `nullOnDelete()`) was added as the **operational variant reference** for Admin editing and delivery tracking.
- Snapshot columns (`product_id`, `product_code`, `product_name`, `color`, `size`, quantity, prices, `price_visibility`) remain unchanged and historical — they are never replaced by relationships.
- Legacy rows with `NULL product_variant_id` remain viewable; confirmation performs no stock operation and requires no variant reference.
- `ProductVariant` rows referenced by an active order (`new`, `confirmed`, `partially_delivered`) cannot be deleted (application-level guard, no soft deletes/ledger); product deletion is also blocked when it would cascade such variants.
- Status transitions are enforced server-side in the `Order` model (`new → confirmed`, `new → cancelled`, delivery transitions); `status` is not mass-assignable, changes happen only through approved transition methods/actions.

## P03-W03 — Ordering behavior

1. **Quantity lifecycle — updated by Revision R01:** no step of the order lifecycle changes `product_variants.available_quantity`. Customer submission, confirmation, delivery and cancellation all leave stock untouched; the column is an internal Admin reference only. No reservation, decrement, restock, or stock-movement logic exists; the accounting/inventory system remains the external source of truth.
2. **Customer/orders FK delete behavior:** the original schema does not define delete behavior for `orders.customer_id`. Chosen: `restrictOnDelete` — customers with orders cannot be deleted, so historical order/customer data always remains intact.
3. **`order_items.product_id` FK:** `nullOnDelete` — deleting a product never destroys order snapshots (`product_code`, `product_name`, color, size, quantities, prices stay).
4. **Order numbering:** `ORD-<year>-<5 digits>` sequential. A candidate number is derived from the current year's max sequence inside the order transaction; `unique(order_number)` is the final integrity guarantee and a duplicate collision triggers a bounded retry (max 5) with a regenerated number. No sequence table.
5. **Duplicate-phone deterministic rule:** when several customers share the exact same phone, the earliest created customer (lowest `id`) is matched and refreshed. No phone normalization or unique-phone constraint (per schema design).

## P01-W02 — Product Variants (lookup tables)

`05-DATABASE-SCHEMA.md` does not define lookup tables for variant options. Two tables were added in P01-W02 as **admin convenience only**:

- `variant_colors` (`id`, `name` unique, `sort_order`, `active`, timestamps)
- `variant_sizes` (`id`, `name` unique, `sort_order`, `active`, timestamps)

Rules:

1. `product_variants.color` and `product_variants.size` remain plain **string columns** — not foreign keys. `product_variants` is `product_id`, `color`, `size` (nullable since R01 when the product has sizes disabled), `size_key` (generated uniqueness helper), `available_quantity`.
2. The lookup tables exist to power Filament select dropdowns. They do not own, reference, or constrain variant data at the database level.
3. Deactivating a lookup option never deletes or modifies existing `product_variants` records. Existing variants using an inactive option remain loadable and editable without data loss.
4. Model-level validation of `color`/`size` stays permissive (any trimmed non-empty string), so Excel imports are not blocked by missing lookup entries. Product import never creates or modifies variants (P05-W02).
5. `unique(product_id, color, size_key)` on `product_variants` is the database integrity guarantee; the generated key prevents duplicate `NULL`-size rows that a plain nullable composite unique would allow.

## P02-W02 — Settings unique key

`05-DATABASE-SCHEMA.md` defines the `settings` table (`id`, `key`, `value`) but does not explicitly specify an index on `key`. Since `key` is a configuration identifier used for lookup (`Setting::get()`), `unique(key)` is applied as an **approved technical schema decision**. `settings` is the approved configuration mechanism for operational settings (e.g. `whatsapp_number`); it does not originate from the original schema's index list.

## P01-W01 — Product price precision

`05-DATABASE-SCHEMA.md` does not define decimal precision/scale for `products.price`. MVP implementation value: `decimal(12,2)`.
