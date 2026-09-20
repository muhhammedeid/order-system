# Wholesale Order System — Development Guardrails

These rules are mandatory for all agents and developers.

## Scope Rules

1. Do not add features not explicitly requested.
2. Do not add architecture for hypothetical future requirements.
3. Do not turn the system into ERP, CRM, accounting or inventory software.
4. Do not implement online payments.
5. Do not implement client login/authentication.
6. Do not implement invoices.
7. Do not implement financial balances or customer credit.
8. Do not implement stock movements or warehouse transactions. No order lifecycle step changes `available_quantity`; it is an Admin reference only.
9. Do not implement delivery/shipping systems (couriers, shipments, dispatch, shipping integrations). Lightweight fulfillment tracking inside orders (`delivered_quantity`) is approved and is not such a system.
10. Do not implement advanced analytics unless separately approved.

## Architecture Rules

11. Use Laravel + Vue 3 + Inertia + MySQL.
12. Use Filament for Admin.
13. Keep one Laravel monolith.
14. Do not create microservices.
15. Do not create a separate Vue API application.
16. Do not introduce DDD/CQRS/Event Sourcing.
17. Do not introduce Repository Pattern without demonstrated need.
18. Do not require Redis.
19. Do not create queues unless a real approved task requires them.
20. Prefer framework-native Laravel functionality.

## Development Rules

21. Keep each work package isolated.
22. Do not begin the next package before acceptance.
23. Do not silently expand scope.
24. Report scope conflicts instead of inventing infrastructure.
25. Preserve accounting Product Codes.
26. Preserve accounting Customer Codes.
27. Optimize for delivery speed, clarity and maintainability.
28. Write only useful tests around business-critical logic.
29. Keep customer-facing UI Arabic-first and RTL.
30. Keep code and schema naming in English.

## Price Privacy

31. Hidden prices must never be exposed to the public frontend.
32. A `request_price` product must not send its internal price through Inertia props.
33. Hidden prices must not appear in page source or client-side JavaScript.

## Data Ownership

34. The accounting system remains the source of truth for official product/customer codes.
35. This system is an operational order capture layer only.

## Stop Condition

If a requested implementation appears to require:

- Accounting logic
- Financial logic
- Inventory transaction logic
- Client authentication
- A major new architectural layer

stop the work package and report the conflict before implementation.
