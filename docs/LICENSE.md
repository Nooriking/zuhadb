# Machine-bound license (anti-resale)

The POS can be locked to **one computer**. Copying the folder to another PC blocks the app until you issue a new key.

## Your vendor PC (keep secret)

In `.env`:

```env
LICENSE_ENABLED=true
LICENSE_ALLOW_ISSUE=true
LICENSE_SECRET=put-a-long-private-random-string-here
```

## Client PC (delivery)

1. Install the app on **their** computer.
2. Run:

```bash
php artisan license:machine-id
```

3. On **your** PC, issue a key:

```bash
php artisan license:issue MACHINE_ID_HERE --customer="Shop Name"
```

4. On the client PC activate:

```bash
php artisan license:activate "ZUHA-...."
```

Or open `/license` in the browser and paste the key.

5. On the client `.env` set:

```env
LICENSE_ENABLED=true
LICENSE_ALLOW_ISSUE=false
```

Do **not** put `LICENSE_ALLOW_ISSUE=true` or your private `LICENSE_SECRET` workflow docs on the client machine in a way that lets them mint keys for other PCs. Prefer activating yourself during handover, then leave issue disabled.

## Notes

- Local/dev (`APP_ENV=local`) stays unlocked unless `LICENSE_ENABLED=true`.
- Production (`APP_ENV=production`) enables the lock by default.
- Automated tests always skip the lock.
