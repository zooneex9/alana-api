# Bodega API (`bodega-api`)

Laravel 13 API for Bodega catalog/admin/orders.

## Features

- Sanctum token auth
- Role-based admin access (`spatie/laravel-permission`)
- Product CRUD + status transitions
- Order management
- Stripe Checkout session creation + webhook reconciliation
- S3 image uploads for product images
- Dashboard summary endpoint

## Environment

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
```

Required sections:
- MySQL connection (`DB_*`)
- S3 credentials (`AWS_*`)
- Stripe credentials (`STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET`)
- Frontend URL (`FRONTEND_URL`)

## Install / Run

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Default seeded admin:
- `admin@bodega.test`
- `password123`

## API Base

`/api/v1`

Main routes:
- `POST /auth/login`
- `GET /products`
- `POST /orders/checkout-session`
- `POST /stripe/webhook`
- Admin (requires `auth:sanctum` + `role:admin`):
  - `GET /dashboard/summary`
  - `POST|PUT|PATCH|DELETE /products`
  - `GET|POST|PUT /orders`

## Tests

```bash
php artisan test
```
