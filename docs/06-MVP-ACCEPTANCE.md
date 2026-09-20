# Wholesale Order System — MVP Acceptance

The MVP is complete when the following end-to-end flow works reliably.

## Client Flow

1. Client opens catalog.
2. Client sees active products.
3. Client opens product.
4. Client sees available colors.
5. Client sees available sizes only when the product enables sizes.
6. Client sees public price when allowed.
7. Client can request hidden price through WhatsApp.
8. Client chooses quantity 5, 10 or a custom positive integer and adds variants to cart.
9. Client updates/removes cart items.
10. Client enters customer data.
11. System reuses existing customer by mobile when applicable.
12. Client submits order.
13. System validates products, variants and positive quantities (no stock cap, no stock exposure).
14. System creates order.
15. Client receives Order Number.

## Admin Flow

16. Admin logs in.
17. Admin sees new orders in Order Management.
18. Admin opens complete order details.
19. Admin sees customer data.
20. Admin sees all variants and quantities.
21. Admin can edit a pending (`new`) order, confirm it, or cancel it.
22. Admin can record partial deliveries on confirmed orders until the order is delivered.
23. Delivered orders remain available in the Delivered Orders history.
24. Admin can export orders to Excel (Revision R04; pending).
25. Excel contains accounting Product Codes.
26. Excel contains Customer Codes when available.
27. Each ordered variant appears as an individual row; export never changes an order status.

## Product Management

28. Admin can create/edit products.
29. Admin can set product as public price or request price.
30. Admin can manage colors, optional sizes and available quantities (Admin reference only; never exposed or used to block customers).
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
