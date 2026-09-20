# Wholesale Order System

Lean wholesale ordering platform for a shoe factory.

## Official Stack

- Laravel
- Vue 3
- MySQL
- Tailwind CSS
- Vite
- Filament for Admin Panel

## Product Boundary

This system is only for:

- Product catalog browsing
- Variant options by color and optional size (exact stock is Admin-only)
- Public or hidden product prices
- WhatsApp price requests
- Customer data capture
- Wholesale order submission
- Admin order management and lightweight delivery tracking
- Product/customer imports
- Excel order export (pending Revision R04)

This system is **not**:

- ERP
- Accounting system
- Payment platform
- Inventory transaction system
- CRM
- Shipping platform

## Core Rule

Keep the implementation simple.

Do not add architecture, services, workflows, integrations, or features unless explicitly required by an approved work package.

## Main Documents

- `01-PRD.md`
- `02-IMPLEMENTATION-BLUEPRINT.md`
- `03-WORK-PACKAGES.md`
- `04-DEVELOPMENT-GUARDRAILS.md`
- `05-DATABASE-SCHEMA.md`
- `06-MVP-ACCEPTANCE.md`
- `07-ENVIRONMENT.md` — official local development environment (WSL MariaDB, PHP extensions)
- `08-SCHEMA-DECISIONS.md` — technical/schema decisions that refine the approved schema
