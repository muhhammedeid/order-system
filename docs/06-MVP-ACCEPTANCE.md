# Wholesale Order System — MVP Acceptance

The MVP is complete when the following end-to-end flow works reliably.

## Client Flow

1. Client opens catalog.
2. Client sees active products.
3. Client opens product.
4. Client sees available colors.
5. Client sees available sizes.
6. Client sees quantity availability.
7. Client sees public price when allowed.
8. Client can request hidden price through WhatsApp.
9. Client adds one or more variants to cart.
10. Client updates/removes cart items.
11. Client enters customer data.
12. System reuses existing customer by mobile when applicable.
13. Client submits order.
14. System validates current quantities.
15. System creates order.
16. Client receives Order Number.

## Admin Flow

17. Admin logs in.
18. Admin sees the new order.
19. Admin opens complete order details.
20. Admin sees customer data.
21. Admin sees all variants and quantities.
22. Admin can confirm or cancel order.
23. Admin can export one or multiple orders to Excel.
24. Excel contains accounting Product Codes.
25. Excel contains Customer Codes when available.
26. Each ordered variant appears as an individual row.
27. Admin can mark/export order as exported.

## Product Management

28. Admin can create/edit products.
29. Admin can set product as public price or request price.
30. Admin can manage colors, sizes and available quantities.
31. Admin can import product data.

## Customer Management

32. Admin can create/edit/search customers.
33. Admin can import customer data.

## UX

34. Storefront is mobile friendly.
35. Storefront is Arabic-first.
36. Storefront is RTL.
37. Hidden prices are not exposed to client-side source/data.

## MVP Completion Rule

If the following works:

Catalog  
→ Product  
→ Cart  
→ Customer  
→ Order  
→ Admin Review  
→ Excel Export

then the system has achieved its MVP objective.

Anything beyond this requires a separately approved scope.
