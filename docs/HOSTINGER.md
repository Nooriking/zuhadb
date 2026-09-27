# Domain: zuha.tezhoshan.com

DNS already points to Hostinger IP: `2.24.116.118`.

## Server `.env` (Hostinger)

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://zuha.tezhoshan.com
SESSION_DOMAIN=.tezhoshan.com
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"
LICENSE_ENABLED=true
LICENSE_ALLOW_ISSUE=false
```

## Hostinger panel

1. Domain / subdomain: `zuha.tezhoshan.com`
2. Document Root → project `public` folder
3. SSL (Let's Encrypt) ON for this domain
4. In Google Cloud Console (if Google login used), add redirect:
   `https://zuha.tezhoshan.com/auth/google/callback`

## After upload

```bash
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan license:machine-id
```

Then issue/activate license from your vendor PC.
