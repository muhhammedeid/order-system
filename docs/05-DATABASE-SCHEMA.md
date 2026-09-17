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
created_at
updated_at
```

Allowed `price_visibility`:

- public
- request_price

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
size
available_quantity
created_at
updated_at
```

Constraints:

```text
unique(product_id, color, size)
available_quantity >= 0
```

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
- exported
- cancelled

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
product_code
product_name
color
size
quantity
unit_price nullable
price_visibility
created_at
updated_at
```

Important:

`product_code`, `product_name`, `unit_price`, and `price_visibility` are snapshots.

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
