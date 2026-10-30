# Standalone Agricultural Budget and Procurement Management System

ABPMS is an independent Laravel application for the Bongabong Municipal Agriculture Office. It manages agricultural budgets, procurement requests, approvals, suppliers, purchase orders, receiving, internal supply movements, equipment acquisitions, reports, and audit history.

This repository is **not** the full AgriConnect ERP and has no runtime or database dependency on it.

## Local setup

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate
# Optional local demo data: set a strong private ABPMS_DEMO_PASSWORD first.
php artisan db:seed
npm install
npm run build
php artisan serve --host=127.0.0.1 --port=8001
```

The application runs at `http://127.0.0.1:8001` and uses the independent MySQL database `agriconnect_abpms` by default. Configure database credentials only in `.env`; never commit `.env` or database exports.

To create demonstration accounts, set `ABPMS_DEMO_PASSWORD` in the local environment to a strong value of at least 16 characters. The value is intentionally not stored in this repository. PHPUnit uses its own isolated test password in `phpunit.xml`.

## Verification

```powershell
php artisan migrate:status
php artisan route:list
php artisan view:cache
php artisan test
npm run build
```

## Documentation

Architecture, security boundaries, workflow rules, database design, and Mermaid diagram sources are in [docs/ABPMS-ARCHITECTURE.md](docs/ABPMS-ARCHITECTURE.md).
