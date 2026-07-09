# Deploy — Alana Dress Rental

## Laravel Forge (API)

Site: `alana-api-lmlybcbz.on-forge.com`  
SSH: `forge@147.182.129.19`

### Forge deploy script

This project is **API-only**. Do **not** run `npm install` / `npm run build` on Forge (Node 18 breaks Vite 8; assets are not needed).

Copy the contents of [`forge-deploy.sh`](./forge-deploy.sh) into **Forge → Site → Deploy Script**.

### Production `.env` (Forge)

```env
APP_NAME="Alana Dress Rental"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://alana-api-lmlybcbz.on-forge.com

FRONTEND_URL=https://www-alana.vercel.app
CORS_ALLOWED_ORIGINS=https://www-alana.vercel.app
CORS_SUPPORTS_CREDENTIALS=false

DB_CONNECTION=mysql
DB_DATABASE=dress_rental
# ... Forge MySQL credentials

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=...

SANCTUM_STATEFUL_DOMAINS=www-alana.vercel.app
```

After changing `.env` on the server:

```bash
php artisan config:clear && php artisan config:cache
```

### Verify API

```bash
curl https://alana-api-lmlybcbz.on-forge.com/api/v1/products
curl https://alana-api-lmlybcbz.on-forge.com/api/v1/categories
```

---

## Vercel (Frontend)

Repo: `www-alana`

### Environment variable

| Name | Value |
|------|--------|
| `VITE_API_BASE_URL` | `https://alana-api-lmlybcbz.on-forge.com/api/v1` |

Set in **Vercel → Project → Settings → Environment Variables** for Production (and Preview if needed).

### Deploy

Connect the GitHub repo `zooneex9/www-alana` in Vercel. Build command: `npm run build`, output: `dist`.

Or CLI:

```bash
cd www-alana
vercel --prod
```
