# Wholesale Order System — Schema Decisions

Technical/schema decisions that deviate from, or refine, `05-DATABASE-SCHEMA.md`. These are engineering choices, not business requirements.

## P03-W03 — Ordering behavior

1. **No quantity decrement (approved business decision):** submitting an order does NOT change `product_variants.available_quantity`. No reservation, restock, or stock-movement logic exists. The accounting/inventory system remains the external source of truth; quantities are managed by admin/imports.
2. **Customer/orders FK delete behavior:** the original schema does not define delete behavior for `orders.customer_id`. Chosen: `restrictOnDelete` — customers with orders cannot be deleted, so historical order/customer data always remains intact.
3. **`order_items.product_id` FK:** `nullOnDelete` — deleting a product never destroys order snapshots (`product_code`, `product_name`, color, size, quantities, prices stay).
4. **Order numbering:** `ORD-<year>-<5 digits>` sequential. A candidate number is derived from the current year's max sequence inside the order transaction; `unique(order_number)` is the final integrity guarantee and a duplicate collision triggers a bounded retry (max 5) with a regenerated number. No sequence table.
5. **Duplicate-phone deterministic rule:** when several customers share the exact same phone, the earliest created customer (lowest `id`) is matched and refreshed. No phone normalization or unique-phone constraint (per schema design).

## P01-W02 — Product Variants (lookup tables)

`05-DATABASE-SCHEMA.md` does not define lookup tables for variant options. Two tables were added in P01-W02 as **admin convenience only**:

- `variant_colors` (`id`, `name` unique, `sort_order`, `active`, timestamps)
- `variant_sizes` (`id`, `name` unique, `sort_order`, `active`, timestamps)

Rules:

1. `product_variants.color` and `product_variants.size` remain plain **string columns** — not foreign keys. `product_variants` is exactly as approved: `product_id`, `color`, `size`, `available_quantity`.
2. The lookup tables exist to power Filament select dropdowns. They do not own, reference, or constrain variant data at the database level.
3. Deactivating a lookup option never deletes or modifies existing `product_variants` records. Existing variants using an inactive option remain loadable and editable without data loss.
4. Model-level validation of `color`/`size` stays permissive (any trimmed non-empty string), so Excel imports (P05-W02) are not blocked by missing lookup entries. Import behavior will be decided in P05-W02.
5. `unique(product_id, color, size)` on `product_variants` remains the database integrity guarantee.

## P02-W02 — Settings unique key

`05-DATABASE-SCHEMA.md` defines the `settings` table (`id`, `key`, `value`) but does not explicitly specify an index on `key`. Since `key` is a configuration identifier used for lookup (`Setting::get()`), `unique(key)` is applied as an **approved technical schema decision**. `settings` is the approved configuration mechanism for operational settings (e.g. `whatsapp_number`); it does not originate from the original schema's index list.

## P01-W01 — Product price precision

`05-DATABASE-SCHEMA.md` does not define decimal precision/scale for `products.price`. MVP implementation value: `decimal(12,2)`.
