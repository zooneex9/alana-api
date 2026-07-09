# Alana Dress Rental API (`alana-api`)

Laravel 13 API for Alana Dress Rental: catalog, admin, rental blocks, and customers.

## Features

- Sanctum token auth
- Role-based admin access (`spatie/laravel-permission`)
- Dress catalog CRUD
- Rental blocks (calendar availability)
- Customer catalog + rental history per dress
- S3 image uploads for product images
- Dashboard summary endpoint

## Environment

Copy `.env.example` to `.env` and configure:

```bash
cp .env.example .env
```

Required sections:

- MySQL connection (`DB_*`)
- S3 credentials (`AWS_*`) for dress images
- Frontend URL (`FRONTEND_URL`)

## Install / Run

```bash
composer install
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Default seeded admin:

- `admin@bodega.com` / `bodega123`
- `admin@bodega.test` / `password123`

## API Base

`/api/v1`

Main routes:

- `POST /auth/login`
- `GET /products`
- `GET /products/{id}/availability`
- `GET /rental-blocks` (admin)
- `GET /customers` (admin)
- `GET /products/{id}/rental-history` (admin)
