# MP WMS

Multi-client warehouse management system. Currently serving NeoNail UK / Fuzion Gel.

**Live:** https://nnuk.wms.mp-transport.co.uk  
**Branch under review:** `feature/catalogue-and-mapping`

## Version Block

| Component | Version |
|---|---|
| **PHP** | 8.3.35 |
| **Laravel** | v13.33.0 |
| **Livewire** | v4.4.6 |
| **Livewire Flux UI** | v2.20.0 |
| **Laravel Fortify** | v1.40.0 |
| **Pest** | v4.7.8 |
| **PHPUnit** | 12.5.33 |
| **SQLite** | 3.53.4 (production) |

## Quick Start

```bash
cp .env.example .env
composer install
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed --class=DemoSeeder   # test data only
npm install && npm run build
php artisan serve
```

## Test Suite

```bash
php vendor/bin/pest tests/Feature/FoundationTest.php tests/Feature/CatalogueTest.php
# 17 tests, 17 passed
```

## Architecture Notes

See `docs/` for Paul's review documents and the security evidence report.
