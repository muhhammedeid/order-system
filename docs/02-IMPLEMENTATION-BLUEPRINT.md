# Wholesale Order System — Implementation Blueprint

## 1. Official Technical Stack

The approved stack is:

- **Laravel** — backend, routing, validation, persistence and application logic.
- **Vue 3** — customer-facing frontend.
- **Inertia.js** — bridge between Laravel and Vue 3.
- **MySQL** — relational database.
- **Tailwind CSS** — UI styling.
- **Vite** — frontend build tooling.
- **Filament** — Admin Panel.

No separate frontend repository is required.

No public REST API is required for the MVP.

Architecture:

```text
Laravel Monolith
├── Laravel Backend
├── Inertia
├── Vue 3 Storefront
├── Filament Admin
└── MySQL
```

---

## 2. Architecture Principle

Use a simple Laravel monolith.

Do not introduce:

- Microservices
- DDD layers
- CQRS
- Event sourcing
- Repository pattern without a real need
- Separate Vue SPA/API architecture
- Message brokers
- Redis requirement
- Complex queues
- Workflow engines

Prefer Laravel built-in functionality whenever possible.

---

## 3. Frontend Structure

Suggested Vue pages:

```text
resources/js/
├── Pages/
│   ├── Catalog/
│   │   ├── Index.vue
│   │   └── Show.vue
│   ├── Cart/
│   │   └── Index.vue
│   ├── Checkout/
│   │   └── Index.vue
│   └── Order/
│       └── Success.vue
│
├── Components/
│   ├── ProductCard.vue
│   ├── ProductGallery.vue
│   ├── VariantSelector.vue
│   ├── QuantityInput.vue
│   ├── CartItem.vue
│   └── WhatsAppPriceButton.vue
│
└── Layouts/
    └── StorefrontLayout.vue
```

Keep shared components limited to components that are actually reused.

---

## 4. Backend Structure

Suggested Laravel structure:

```text
app/
├── Models/
│   ├── Category.php
│   ├── Customer.php
│   ├── Product.php
│   ├── ProductVariant.php
│   ├── ProductImage.php
│   ├── Order.php
│   ├── OrderItem.php
│   └── Setting.php
│
├── Http/
│   ├── Controllers/
│   │   ├── CatalogController.php
│   │   ├── CartController.php
│   │   └── OrderController.php
│   └── Requests/
│       └── StoreOrderRequest.php
│
└── Filament/
```

Do not create service classes merely to make the project look layered.

Extract logic only when controller/model code becomes genuinely difficult to maintain.

---

## 5. Cart Strategy

For MVP, use session-based cart storage.

No cart database table is required.

Benefits:

- No client authentication required.
- No abandoned cart logic.
- Minimal implementation.
- Fits the project requirements.

The server must revalidate quantities before final order creation.

---

## 6. Customer Matching

At checkout:

1. Search existing customer by mobile number.
2. If found, reuse and optionally refresh editable contact fields.
3. If not found, create new customer.
4. Create the order using that customer.

No login account is created for the client.

---

## 7. Price Security

For `request_price` products:

- Do not render the hidden price into Vue page props.
- Do not expose it in HTML.
- Do not expose it through JavaScript.
- Do not include it in public endpoint responses.

The frontend should receive only the information necessary to display `request_price`.

---

## 8. Order Creation

Order creation must run inside a database transaction.

Before creating the order:

1. Validate cart is not empty.
2. Reload relevant product variants from database.
3. Confirm products are active.
4. Confirm each requested quantity is greater than zero.
5. Confirm each requested quantity does not exceed current available quantity.
6. Resolve/create customer.
7. Create order.
8. Create order item snapshots.
9. Commit.

MVP does not need to automatically reduce accounting inventory.

Whether `available_quantity` is decremented on submitted orders should be a separate explicit business decision before implementation.

---

## 9. Admin Panel

Use Filament for:

- Product CRUD
- Category CRUD
- Variant management
- Customer CRUD
- Order list/details
- Order status actions
- Import actions
- Export actions
- Settings

Do not rebuild Admin with Vue.

---

## 10. Import / Export

Use a maintained Laravel-compatible Excel package.

Imports should:

- Validate each row.
- Report invalid rows.
- Match by external code.
- Avoid silent duplicates.

Exports should:

- Produce one row per order variant.
- Preserve accounting Product Code.
- Preserve Customer Code.
- Support one or multiple selected orders.

---

## 11. RTL / Arabic

Storefront:

- Arabic-first
- RTL
- Mobile-first

Development naming:

- English

Do not hard-code Arabic text deeply inside business logic.

Keep presentation strings within views/components or localization files.

---

## 12. Deployment Expectations

MVP requires only:

- PHP runtime compatible with selected Laravel version
- MySQL
- Node/Vite build during deployment
- Standard web server

Redis is not a deployment dependency unless a later approved feature requires it.

---

## 13. Testing Strategy

Focus testing on business-critical behavior.

Required automated coverage:

- Product code uniqueness
- Variant uniqueness
- Quantity validation
- Public/hidden price behavior
- Customer matching
- Order creation
- Order snapshots
- Order status changes
- Import validation
- Export structure

Avoid testing framework internals.

---

## 14. Performance

No premature optimization.

Use:

- Pagination on catalog/admin lists.
- Eager loading where obvious.
- Basic DB indexes on codes, statuses and foreign keys.
- Optimized image sizes.

No caching layer is required for MVP.
