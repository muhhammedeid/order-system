# Wholesale Order System — Product Requirements Document

## 1. Product Vision

Wholesale Order System is a lightweight ordering platform for a shoe factory.

Its purpose is to allow wholesale customers to browse products, check available colors and optional sizes, view allowed prices, and submit wholesale orders without online payment. Exact stock levels are never shown to customers.

The existing accounting system remains the source of truth for accounting, financial records and official product/customer codes.

The new system is only an operational ordering layer.

---

## 2. Business Objectives

1. Reduce repetitive communication between wholesale customers and operations.
2. Allow customers to build orders independently.
3. Reduce product code, size and quantity mistakes.
4. Keep customer and product codes aligned with the accounting system.
5. Organize wholesale orders in one place.
6. Export orders in a structured Excel format for the accounting team.
7. Keep selected product prices private.

---

## 3. Users

### 3.1 Client

Client users do not require normal authentication in the MVP.

They can:

- Browse catalog.
- Open product details.
- View available colors and optional sizes.
- View price when public.
- Request hidden price through WhatsApp.
- Add products to an order with any positive quantity.
- Enter customer information.
- Submit order.

Customer data is stored after the first order.

No client password, client dashboard, email verification, roles or permission system.

### 3.2 Admin

Admin authentication is required.

Admin can:

- Manage products.
- Manage product variants.
- Manage public/hidden pricing.
- Manage customers.
- Review, edit (while pending), confirm or cancel orders.
- Record partial or complete deliveries.
- Import customers/products.
- Export orders to Excel.
- Manage basic settings.

Only one Admin role is required for MVP.

---

## 4. Product Catalog

Each product contains:

- Product Code
- Product Name
- Category
- Short Description
- Main Image
- Additional Images
- Price Visibility
- Price
- Active Status

`product_code` must match the product code in the accounting system.

---

## 5. Product Variants

Each product can contain variants defined by:

- Color (only when the product has `color_enabled = true`; on by default)
- Size (only when the product has `size_enabled = true`; off by default)
- Available Quantity (internal Admin reference)

The two controls are independent, so a product can use color only, size only, both, or neither.

Example:

| Product | Color | Size | Available Qty |
|---|---|---|---|
| SH-100 | Black | 40 | 15 |
| SH-100 | Black | 41 | 10 |
| SH-100 | White | 40 | 8 |
| SH-200 | White | — | 12 |

The system does not manage warehouse transactions.

`available_quantity` is an internal Admin reference only. It is never shown to customers and never blocks ordering: any positive requested quantity is accepted (quantity presets `5`, `10`, or a custom positive integer). Operations may contact the customer and agree on quantities before confirmation.

When `size_enabled` is `false`, the customer selects color and quantity only; no size is required or stored for new variants.

When `color_enabled` is `false`, the customer selects size and quantity only (or quantity only when sizes are also disabled); no color is required or stored for new variants. Disabled dimensions are stored as `NULL`, never as a fake value.

---

## 6. Price Visibility

Each product has:

`price_visibility`

Allowed values:

- `public`
- `request_price`

### Public

Price is shown to the customer.

### Request Price

Price is hidden.

The customer sees:

**معرفة السعر عبر WhatsApp**

No approval workflow is implemented inside the system.

---

## 7. WhatsApp Price Request

When the customer presses the price request button, WhatsApp opens with a prepared message containing:

- Product Name
- Product Code
- Product URL

Example:

> مرحبًا، أريد معرفة سعر المنتج Model X  
> Code: SH-1025  
> Product Link: ...

The request is handled manually by the operations team.

---

## 8. Customer Data

Customer fields:

- Customer Code
- Customer Name
- Company / Store Name
- Mobile
- WhatsApp
- Governorate
- City
- Address
- Notes

`customer_code` should match the existing accounting system when available.

The accounting system remains the source of truth.

---

## 9. Order Flow

Client flow:

Catalog  
→ Product Details  
→ Select Color  
→ Select Size (only when the product enables sizes)  
→ Enter Quantity (5, 10 or any custom positive integer)  
→ Add to Order  
→ Cart  
→ Customer Information  
→ Submit Order  
→ Order Success

Example order number:

`ORD-2026-00001`

Submitting an order means an order request was recorded.

It does not represent a confirmed sale or payment.

---

## 10. Cart

Cart displays:

- Product
- Product Code
- Color
- Size
- Quantity
- Unit Price if public
- Line Total if applicable

Client can:

- Change quantity
- Remove item
- Continue shopping
- Submit order

Quantity must be a positive integer. It is not limited by `available_quantity`.

---

## 11. Order Data

### Order

- Order Number
- Customer ID
- Status
- Customer Notes
- Admin Notes
- Total Quantity
- Created At
- Updated At

### Order Item

- Order ID
- Product ID
- Variant Reference
- Product Code Snapshot
- Product Name Snapshot
- Color
- Size (when the product enables sizes)
- Quantity
- Delivered Quantity
- Unit Price Snapshot
- Price Visibility Snapshot

Remaining quantity is derived (`quantity - delivered_quantity`) and never stored.

Historical order data must not change when product data changes later.

---

## 12. Order Statuses

Allowed statuses:

- `new`
- `confirmed`
- `partially_delivered`
- `delivered`
- `cancelled`

Excel export is an action, not a status. No workflow engine.

---

## 13. Excel Export

Implementation is pending Revision R04. Export is an action and never changes an order status.

Admin can:

- Export one order.
- Export selected orders.

Each variant becomes a separate Excel row.

Example:

| Order No | Customer Code | Product Code | Color | Size | Qty | Unit Price |
|---|---|---|---|---|---:|---:|
| ORD-001 | C100 | SH-100 | Black | 40 | 5 | 450 |
| ORD-001 | C100 | SH-100 | Black | 41 | 10 | 450 |

Final columns must later match the accounting system import template exactly.

---

## 14. Product Import

Support Excel import for product data.

Minimum fields:

- Product Code
- Product Name
- Category
- Price
- Price Visibility
- Active

Variant import format can be finalized after reviewing the source accounting data.

---

## 15. Customer Import

Support Excel import for customers.

Fields:

- Customer Code
- Customer Name
- Company Name
- Phone
- WhatsApp
- Governorate
- City
- Address

---

## 16. Admin Panel

Main sections:

- Dashboard
- Products
- Customers
- Orders
- Settings

Dashboard only needs operational information:

- New Orders
- Today's Orders
- Confirmed Orders
- Active Products
- Customers

No advanced analytics.

---

## 17. Client Screens

MVP pages:

1. Catalog
2. Product Details
3. Cart
4. Customer Information
5. Order Success

---

## 18. Admin Screens

1. Login
2. Dashboard
3. Product List
4. Product Form
5. Order Management (active orders by status)
6. Order Details / Confirm
7. Delivered Orders (history)
8. Customer List
9. Customer Form
10. Settings

---

## 19. Language

The customer-facing system should be Arabic-first and RTL.

Code, classes, database naming and development terminology remain English.

---

## 20. Out of Scope

Explicitly excluded:

- Online Payments
- Payment Gateways
- Invoices
- Accounting
- Taxes
- Customer Balance
- Customer Credit
- Supplier Management
- Purchase Orders
- Warehouses
- Inventory Transactions
- Inventory Valuation
- Delivery Management
- Shipping Integration
- Sales Representatives
- Commissions
- Loyalty
- Coupons
- CRM
- Client Authentication
- Client Passwords
- Client Dashboard
- Advanced Permissions
- Mobile App
- ERP Features
- Advanced Reporting
- AI Features
