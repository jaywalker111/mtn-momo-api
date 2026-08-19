# MTN MoMo Collections — PHP + MySQL

This is a beginner-friendly MTN MoMo Collections project. Lines that normally require your input are marked with `CHANGE THIS`.

## 1. Requirements

- PHP 8.1+
- PHP cURL extension
- PDO MySQL extension
- MySQL 5.7+/8.x or MariaDB
- Apache/Nginx
- HTTPS for production
- MTN MoMo Collections subscription

## 2. Main project files

```text
.env.example       -> copy to .env and edit
database.sql       -> import into MySQL
config.php         -> loads .env
src/Database.php   -> database connection
src/MomoClient.php -> MTN API calls
src/helpers.php    -> utility functions
public/index.php   -> customer payment form
public/status.php  -> payment status
public/callback.php-> MTN callback endpoint
public/admin.php   -> admin transaction dashboard
cron/poll_pending.php -> pending-payment fallback checker
```

## 3. Get MTN credentials

From MTN's MoMo developer/partner portal, obtain the Collections credentials for your environment:

- Collections subscription key
- API User
- API Key
- Sandbox credentials for testing
- Production credentials after onboarding/approval

Never put these credentials in HTML or JavaScript.

## 4. Configure `.env`

Copy:

```text
.env.example
```

to:

```text
.env
```

Then edit every line marked:

```text
>>> CHANGE THIS
```

Example:

```dotenv
APP_URL=https://payments.example.com
APP_SECRET=YOUR_LONG_RANDOM_SECRET
APP_ADMIN_USER=admin
APP_ADMIN_PASSWORD_HASH=YOUR_HASH
```

MTN section:

```dotenv
MOMO_SUBSCRIPTION_KEY=YOUR_REAL_COLLECTIONS_KEY
MOMO_API_USER=YOUR_REAL_API_USER_UUID
MOMO_API_KEY=YOUR_REAL_API_KEY
MOMO_CALLBACK_URL=https://payments.example.com/callback.php
```

Keep the sandbox configuration while testing.

## 5. Generate the admin password hash

Run:

```bash
php tools/generate_admin_hash.php 'YourStrongPassword'
```

Copy the result into `.env`:

```dotenv
APP_ADMIN_PASSWORD_HASH=...
```

Delete `tools/generate_admin_hash.php` from a production server after use.

## 6. MySQL

Import:

```text
database.sql
```

Then update these `.env` values if necessary:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=mtn_momo
DB_USER=root
DB_PASS=
```

## 7. Callback URL

The callback is:

```text
https://YOUR-DOMAIN/callback.php
```

It must be publicly reachable by MTN. `localhost` will not work for MTN callbacks.

Use a public HTTPS test server/tunnel for sandbox development, or deploy to your HTTPS domain.

## 8. Web root

Prefer setting your domain's document root to:

```text
.../mtn_momo_php_mysql/public
```

This prevents `.env` and backend source files from being directly exposed.

## 9. Payment flow

```text
Customer
  -> PHP payment form
  -> MySQL PENDING transaction
  -> MTN OAuth token
  -> MTN RequestToPay
  -> Customer approves MoMo prompt
  -> MTN callback and/or status API
  -> PHP verifies status
  -> MySQL SUCCESSFUL/FAILED
```

Only a server-confirmed `SUCCESSFUL` transaction should fulfil an order.

## 10. Where to customize

### Payment messages
Edit `src/MomoClient.php`:

```php
'payerMessage' => 'Payment for order ' . $externalId,
'payeeNote' => 'Payment received',
```

Comments show exactly where this can be changed.

### Your own order/invoice ID
Edit `public/index.php`. The generated `externalId` can be replaced by your own order/invoice ID.

### Order fulfilment
Edit `public/callback.php`. Add your own code to mark an order as paid only after MTN status is verified as `SUCCESSFUL`.

### Payment form
Edit `public/index.php`.

### Admin dashboard
Edit `public/admin.php`.

## 11. Cron

Run:

```bash
php /full/path/to/project/cron/poll_pending.php
```

approximately every minute. This is a fallback for pending payments and missed asynchronous notifications.

## 12. Production checklist

- [ ] MTN production Collections approved
- [ ] Production credentials entered in `.env`
- [ ] Correct production endpoint confirmed with MTN
- [ ] HTTPS enabled
- [ ] Callback URL registered/configured
- [ ] MySQL production database configured
- [ ] Strong admin password set
- [ ] `.env` protected
- [ ] Cron configured
- [ ] Successful payment tested
- [ ] Failed payment tested
- [ ] Duplicate fulfilment prevented
- [ ] Database backups configured

## 13. Important

Do not disable SSL verification. Do not expose MTN API keys. Do not treat a browser redirect as proof of payment. Verify payment server-side through MTN before providing goods/services.
