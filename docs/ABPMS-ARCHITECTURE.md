# Standalone ABPMS architecture

## System overview

ABPMS is an independent Laravel 13 application for the Bongabong Municipal Agriculture Office. It owns its users, session cookies, database connection, migrations, budget ledger, procurement workflow, internal supply receiving registry, equipment acquisition registry, reports, and audit log. It has no foreign keys or runtime reads against AgriConnect ERP, AISMS, or AEAMS.

The application is intentionally a focused ABPMS boundary. The `inventory_movements`/`supply_batches` tables preserve the supply-receipt behavior needed by procurement, while `equipment_assets` preserves acquisition behavior without pretending to be a full inventory or equipment-management suite. Those tables are the documented extension points for future API adapters.

## Scope and roles

| Role | Responsibilities |
| --- | --- |
| System Administrator | Accounts, roles, system-wide access, all operational views |
| MAO Administrator | Budget oversight and financial/procurement approvals |
| Budget Officer | Fiscal periods, allocations, adjustments, ledger, reports |
| Procurement Officer | Requests, suppliers, purchase orders, receiving |
| Auditor | Read-only reports and audit trail |

All write routes use authentication, active-account checks, role middleware, CSRF protection, server-side validation, and database transactions. A requester cannot approve their own request or related PO. Deactivation prevents login and invalidates an active session on the next request.

## Workflow rules

1. A fiscal period contains budget allocations. An allocation has an authorized amount and a ledger of debit/credit entries.
2. Available balance is `authorized amount + credits − debits`. Money is converted to integer cents in `BudgetService`; database columns remain `DECIMAL(14,2)`.
3. A procurement request calculates item totals and checks the allocation before submission. Approval rechecks the current balance.
4. A PO is created only from an approved request. PO approval creates one unique commitment reference. Cancellation releases the commitment.
5. Receiving locks the PO item, rejects quantities beyond the ordered balance, and requires a unique idempotency key. Supply receipts create exactly one stock movement and batch. Equipment receipts create an internal asset and no stock movement.
6. Status histories and `audit_logs` record material decisions, actors, references, timestamps, and relevant metadata.

## Database design

Core entities are `users`, `roles`, `fiscal_periods`, `budget_allocations`, `budget_transactions`, `procurement_suppliers`, `procurement_requests`, `procurement_request_items`, `purchase_orders`, `purchase_order_items`, `procurement_receipts`, `procurement_receipt_items`, `inventory_locations`, `inventory_movements`, `supply_batches`, `equipment_assets`, `procurement_status_histories`, and `audit_logs`.

The single standalone schema migration is ordered so MySQL foreign keys are added only after their parent tables exist. Composite indexes use short explicit names for MySQL identifier limits. The test environment uses an in-memory SQLite database; local runtime configuration uses MySQL database `agriconnect_abpms`.

## Security architecture

- Independent `APP_KEY`, session cookie, `.env`, database, and route namespace.
- Password hashing through Laravel's `hashed` cast / `Hash::make`.
- Login throttling and session regeneration.
- Active-account middleware on every authenticated route.
- Role middleware plus permission-aware navigation.
- CSRF protection on all state-changing web forms.
- Idempotency keys on requests, purchase orders, and receipts.
- Row locks and transactions around financial commitments and receiving.
- Append-only audit records for material actions.

## Installation

```powershell
cd C:\Agriconnect\abpms-system
composer install
Copy-Item .env.example .env
php artisan key:generate
# Create the independent MySQL database named agriconnect_abpms.
php artisan migrate --seed
npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8001
```

Demonstration accounts are created only when a local `ABPMS_DEMO_PASSWORD` environment variable is set to a strong value. That variable is intentionally not committed. The seeded usernames are:

| Email | Role |
| --- | --- |
| `admin@abpms.local` | System Administrator |
| `mao@abpms.local` | MAO Administrator |
| `budget@abpms.local` | Budget Officer |
| `procurement@abpms.local` | Procurement Officer |
| `auditor@abpms.local` | Auditor |

## Capstone diagram sources

The following Mermaid sources are grounded in the implemented controllers, services, routes, and tables. They can be rendered in Mermaid Live, GitHub, or a documentation build.

### UML use case

```mermaid
flowchart LR
  Admin[System Administrator] --> Accounts((Manage accounts and roles))
  MAO[MAO Administrator] --> Approve((Approve requests and POs))
  Budget[Budget Officer] --> BudgetUC((Manage periods, allocations, adjustments))
  Procurement[Procurement Officer] --> Procure((Create requests, suppliers, POs, receipts))
  Auditor[Auditor] --> Read((Read reports and audit trail))
  Procure --> Request((Purchase request))
  Request --> Approve
  Approve --> PO((Purchase order))
  PO --> Receive((Receive delivery))
  Receive --> Supply((Supply movement / batch))
  Receive --> Asset((Equipment asset))
```

### Context diagram

```mermaid
flowchart LR
  Users[ABPMS users] -->|HTTPS / session| ABPMS[Standalone ABPMS]
  ABPMS -->|budget, procurement, audit records| DB[(agriconnect_abpms)]
  ABPMS -. future documented adapter .-> AISMS[AISMS integration boundary]
  ABPMS -. future documented adapter .-> AEAMS[AEAMS integration boundary]
  ABPMS --> Reports[CSV and browser reports]
```

### DFD level 1

```mermaid
flowchart TB
  U[Authenticated users] --> P1[1. Authenticate and authorize]
  P1 --> P2[2. Manage fiscal periods and allocations]
  P1 --> P3[3. Manage requests and approvals]
  P1 --> P4[4. Manage suppliers and purchase orders]
  P1 --> P5[5. Receive supplies and equipment]
  P1 --> P6[6. Report and audit]
  P2 <--> D1[(Budget tables)]
  P3 <--> D2[(Request tables)]
  P4 <--> D3[(Supplier and PO tables)]
  P5 <--> D4[(Receipt, movement, asset tables)]
  P1 <--> D5[(Users and roles)]
  P6 <--> D6[(Audit logs)]
```

### ERD

```mermaid
erDiagram
  USERS ||--o{ BUDGET_ALLOCATIONS : creates
  USERS ||--o{ PROCUREMENT_REQUESTS : requests
  USERS ||--o{ BUDGET_TRANSACTIONS : performs
  USERS ||--o{ PURCHASE_ORDERS : creates
  FISCAL_PERIODS ||--o{ BUDGET_ALLOCATIONS : contains
  BUDGET_ALLOCATIONS ||--o{ BUDGET_TRANSACTIONS : records
  BUDGET_ALLOCATIONS ||--o{ PROCUREMENT_REQUESTS : funds
  PROCUREMENT_REQUESTS ||--|{ PROCUREMENT_REQUEST_ITEMS : contains
  PROCUREMENT_REQUESTS ||--o{ PURCHASE_ORDERS : becomes
  PROCUREMENT_SUPPLIERS ||--o{ PURCHASE_ORDERS : receives
  PURCHASE_ORDERS ||--|{ PURCHASE_ORDER_ITEMS : contains
  PURCHASE_ORDERS ||--o{ PROCUREMENT_RECEIPTS : receives
  PURCHASE_ORDER_ITEMS ||--o{ PROCUREMENT_RECEIPT_ITEMS : fulfilled_by
  PROCUREMENT_RECEIPTS ||--|{ PROCUREMENT_RECEIPT_ITEMS : contains
  PROCUREMENT_RECEIPTS ||--o{ INVENTORY_MOVEMENTS : posts
  PROCUREMENT_RECEIPTS ||--o{ SUPPLY_BATCHES : creates
  PURCHASE_ORDER_ITEMS ||--o{ EQUIPMENT_ASSETS : acquires
```

### UML class diagram

```mermaid
classDiagram
  class BudgetService { +createAllocation() +adjust() +commit() +releaseCommitment() +postExpenditure() +summary() }
  class ProcurementService { +createRequest() +reviewRequest() +createPurchaseOrder() +approvePurchaseOrder() +orderPurchaseOrder() +cancelPurchaseOrder() +receive() }
  class BudgetAllocation { authorized_amount status }
  class BudgetTransaction { entry_type direction amount reference }
  class ProcurementRequest { request_number status estimated_total }
  class PurchaseOrder { purchase_order_number status total_amount }
  class ProcurementReceipt { receipt_reference idempotency_key }
  class EquipmentAsset { asset_tag acquisition_cost }
  BudgetService --> BudgetAllocation
  BudgetService --> BudgetTransaction
  ProcurementService --> BudgetService
  ProcurementService --> ProcurementRequest
  ProcurementService --> PurchaseOrder
  ProcurementService --> ProcurementReceipt
  ProcurementService --> EquipmentAsset
```

### Activity diagram

```mermaid
flowchart TD
  Start([Start]) --> Submit[Submit request with items]
  Submit --> Balance{Enough available budget?}
  Balance -- No --> Reject[Validation error]
  Balance -- Yes --> Review[Authorized review]
  Review --> Decision{Approve?}
  Decision -- No --> End([End])
  Decision -- Yes --> Draft[Draft PO]
  Draft --> POApprove[Authorized PO approval]
  POApprove --> Commit[Post commitment]
  Commit --> Ordered[Mark ordered]
  Ordered --> Receive[Record partial/full receipt]
  Receive --> Type{Item type}
  Type -- Supply --> Movement[Create movement and batch]
  Type -- Equipment --> Asset[Create equipment asset]
  Movement --> End
  Asset --> End
```

### Sequence diagram

```mermaid
sequenceDiagram
  actor Officer
  actor Approver
  participant UI as Blade UI
  participant Service as ProcurementService
  participant Budget as BudgetService
  participant DB as agriconnect_abpms
  Officer->>UI: Submit request
  UI->>Service: createRequest(payload, actor)
  Service->>Budget: assertAvailable(allocation, total)
  Service->>DB: request, items, history, audit in transaction
  Approver->>UI: Approve request and PO
  UI->>Service: reviewRequest / approvePurchaseOrder
  Service->>Budget: lock allocation and post commitment
  Service->>DB: status history and audit
  Officer->>UI: Receive delivery with idempotency key
  UI->>Service: receive(item, payload, actor)
  Service->>DB: lock item, receipt, movement or asset, audit
```
