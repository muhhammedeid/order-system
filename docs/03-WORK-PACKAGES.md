# Wholesale Order System — Work Packages

Execution must be sequential.

Do not begin the next package until the current package passes its acceptance criteria.

---

# Phase 00 — Foundation

## P00-W01 — Project Bootstrap

### Scope

Create and configure:

- Laravel
- Vue 3
- Inertia.js
- MySQL
- Tailwind CSS
- Vite
- Filament
- Admin authentication
- Arabic / RTL frontend foundation

### Acceptance

- Application runs successfully.
- Vue 3 renders through Inertia.
- Database connection works.
- Filament Admin login works.
- Storefront base layout renders correctly.
- No business features implemented.

---

# Phase 01 — Core Data

## P01-W01 — Categories & Products

### Scope

Implement:

- Categories
- Products
- Product Images
- Relationships
- Filament CRUD

### Acceptance

- Admin can create/edit products.
- Product Code is required and unique.
- Products can be active/inactive.
- Product can have multiple images.

---

## P01-W02 — Product Variants

### Scope

Implement:

- Color
- Size
- Available Quantity

### Acceptance

- Admin can manage multiple variants per product.
- `product_id + color + size` is unique.
- Negative quantities are rejected.

---

## P01-W03 — Customers

### Scope

Implement customer entity and Filament management.

### Acceptance

Admin can:

- Create customer.
- Edit customer.
- Search by name.
- Search by phone.
- Search by Customer Code.

---

# Phase 02 — Storefront

## P02-W01 — Product Catalog

### Scope

Vue 3 storefront:

- Product grid
- Search
- Category filter
- Pagination

### Acceptance

- Only active products are visible.
- Catalog works on mobile.
- Search works.
- Category filter works.

---

## P02-W02 — Product Details

### Scope

Implement:

- Product gallery
- Product code
- Product information
- Price visibility
- Variant selection
- Quantity availability

### Acceptance

- Public price is visible.
- Hidden price is not exposed in frontend props/source.
- Available colors/sizes are correct.

---

## P02-W03 — WhatsApp Price Request

### Scope

Implement request price button.

Message includes:

- Product Name
- Product Code
- Product URL

### Acceptance

- Correct WhatsApp number is used.
- Correct product data is included.
- No internal price request workflow is added.

---

# Phase 03 — Ordering

## P03-W01 — Cart

### Scope

Session-based cart.

Implement:

- Add
- Update quantity
- Remove
- Clear
- Cart totals where applicable

### Acceptance

- Quantity > 0.
- Quantity cannot exceed available quantity.
- Cart survives standard navigation.
- No database cart tables.

---

## P03-W02 — Customer Capture

### Scope

Checkout customer form.

Matching rule:

- Match existing customer by mobile.
- Otherwise create customer.

### Acceptance

- Customer data is saved.
- Existing customer can be reused.
- No password/login account created.

---

## P03-W03 — Order Creation

### Scope

Implement:

- Orders
- Order Items
- Order numbering
- Snapshots
- Transactional creation
- Server-side revalidation

### Acceptance

- Empty cart rejected.
- Quantity is revalidated on server.
- Order number generated.
- Product snapshots stored.
- Correct customer linked.

---

## P03-W04 — Order Success

### Scope

Vue success page.

### Acceptance

Displays:

- Success message
- Order Number
- Contact/next-step message

---

# Phase 04 — Admin Orders

## P04-W01 — Order Management

### Scope

Filament order management.

### Acceptance

Admin can:

- List orders.
- Filter orders.
- Open order details.
- View customer information.
- View order items.

---

## P04-W02 — Order Status

### Scope

Statuses:

- new
- confirmed
- exported
- cancelled

### Acceptance

- Admin can update status.
- No workflow engine added.

---

# Phase 05 — Import / Export

## P05-W01 — Customer Import

### Scope

Excel customer import.

### Acceptance

- Customer Code supported.
- Duplicate codes handled safely.
- Invalid rows reported.
- Valid rows imported.

---

## P05-W02 — Product Import

### Scope

Excel product import.

### Acceptance

- Product Code is matching key.
- Existing products can be updated.
- New products can be created.
- Invalid rows reported.

---

## P05-W03 — Order Export

### Scope

Excel export for one or multiple orders.

### Acceptance

- One row per order variant.
- Product Code preserved.
- Customer Code preserved.
- Required columns exported.
- Exported orders can be marked `exported`.

---

# Phase 06 — Finalization

## P06-W01 — Arabic / RTL Finalization

### Scope

Finalize storefront Arabic UX.

### Acceptance

- RTL works correctly.
- Mobile flow is usable.
- Main client-facing copy is Arabic.

---

## P06-W02 — MVP QA

### Scope

Verify complete flow:

Catalog  
→ Product  
→ Cart  
→ Customer  
→ Order  
→ Admin  
→ Export

### Acceptance

No blocker remains in the core MVP flow.

No new business features should be added in this package.
