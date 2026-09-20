# Wholesale Order System — Initial Database Schema

This is the approved lean MVP schema.

---

## users

Used only for Admin authentication.

Fields depend on standard Laravel/Filament auth requirements.

---

## categories

```text
id
name
slug
image_path nullable
active
created_at
updated_at
```

Indexes:

- unique(slug)

---

## customers

```text
id
customer_code nullable
name
company_name nullable
phone
whatsapp nullable
governorate nullable
city nullable
address nullable
notes nullable
created_at
updated_at
```

Indexes:

- unique(customer_code) when applicable
- index(phone)

Primary operational customer matching key:

`phone`

External/accounting reference:

`customer_code`

---

## products

```text
id
product_code
category_id nullable
name
slug
description nullable
price_visibility
price nullable
active
color_enabled
size_enabled
created_at
updated_at
```

Allowed `price_visibility`:

- public
- request_price

`price` is required whenever `price_visibility = public` (enforced server-side on every write path). `request_price` may store an internal price or `null`.

`color_enabled` (default `true`) controls whether the customer may select one or
more available colors. Disabling it keeps all variant colors visible and orders the
complete available set.

`size_enabled` (default `false`) controls whether the customer must select a size.
When disabled, assigned sizes remain visible under their colors without choice.

`size_enabled = true` requires `color_enabled = true`.

Indexes:

- unique(product_code)
- unique(slug)
- index(category_id)
- index(active)

---

## product_variants

```text
id
product_id
color
color_key (generated column derived from color)
size nullable
size_key (generated column derived from size)
available_quantity
created_at
updated_at
```

Constraints:

```text
unique(product_id, color_key, size_key)
available_quantity >= 0
```

`color` is required for new variants so available colors remain displayable regardless
of the choice toggle. `size` is optional because some products are genuinely size-less.
The generated `color_key`/`size_key` columns preserve combination uniqueness and
legacy nullable rows remain readable.

`available_quantity` is an internal Admin reference only. It is not exposed to customers
and never blocks ordering. Storefront quantity is entered per color; when customer size
choice is disabled it must divide evenly by the available size count for each selected
color.

---

## product_images

```text
id
product_id
image_path
sort_order
created_at
updated_at
```

---

## orders

```text
id
order_number
customer_id
status
customer_notes nullable
admin_notes nullable
total_quantity
created_at
updated_at
```

Statuses:

- new
- confirmed
- partially_delivered
- delivered
- cancelled

Excel export is an action, not a lifecycle status (`exported` was removed in Revision R01).

Indexes:

- unique(order_number)
- index(customer_id)
- index(status)
- index(created_at)

---

## order_items

```text
id
order_id
product_id nullable
product_variant_id nullable
product_code
product_name
color nullable
size nullable
requested_quantity
color_count
quantity
delivered_quantity
unit_price nullable
price_visibility
created_at
updated_at
```

Important:

`product_code`, `product_name`, color, size, `unit_price`, and `price_visibility`
are snapshots. `color` and `size` are nullable text fields and may contain a
human-readable list when one order line includes multiple variants. Historical
snapshots are never rewritten.

`requested_quantity` is the quantity requested per color. `color_count` is the number
of selected colors (or all available colors when color choice is disabled). `quantity`
is the derived actual-piece total: `requested_quantity × color_count`. Sizes do not
multiply this value. For products whose size choice is disabled, requested quantity
must divide evenly by the available size count for each selected color.

Rows created before the quantity-breakdown migration retain their historical
`quantity`; they are initialized with `requested_quantity = quantity` and
`color_count = 1` so historical production and delivery totals never change.

`product_variant_id` is the operational variant reference used by Admin order operations (editing, delivery tracking). It is `nullOnDelete`.

`delivered_quantity` is non-negative and never exceeds `quantity`; remaining quantity is derived (`quantity - delivered_quantity`). The invariant is enforced by the model and, on MariaDB/MySQL, by a CHECK constraint.

Historical order data should remain valid even when product data changes later.

---

## settings

Simple key/value configuration.

```text
id
key
value nullable
created_at
updated_at
```

Example keys:

- business_name
- logo
- whatsapp_number
- currency
- order_prefix
- contact_information
- stock_display_mode

---

# Explicitly Not Required

Do not create tables for:

- payments
- invoices
- warehouses
- stock_movements
- customer_balances
- carts
- client_sessions/accounts
- shipping
- commissions
- CRM activities
