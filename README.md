# Hotel Booking — Laravel API

Reusable backend for small hotels, pousadas and guesthouses. Pairs with the Angular
frontend (hotel theme). Forked from `ecommerce-blog-back@df04a2e` with the Mercatura
platform/tenant layer and marketplace modules removed; one install = one hotel.

**Stack:** Laravel 12 · PHP 8.2+ · MariaDB/MySQL · Sanctum · Spatie Permission · PayPal Orders v2

## What it does

- Room types, rooms, amenities, photos, seasonal rates (`rate_overrides`), room blocks
- Availability search with per-night pricing, taxes and online deposit
- Guest checkout without an account (manage-booking token by email), or logged-in guests
- **No double bookings:** every sold or blocked night is a `room_nights` row with a unique
  `(room_id, date)` index; concurrent requests fall back to the next free room
- PayPal: create order → buyer approves (JS SDK) → server capture; webhooks cover closed tabs
  and refunds; automatic refund on free cancellation
- Pay at hotel option; unpaid PayPal holds expire after `HOTEL_HOLD_MINUTES`
- Staff API (`/api/admin/*`, used by the Angular back office at `/admin`):
  dashboard, rooms × dates calendar, bookings, walk-ins, check-in/out, cancel/refund,
  room types (with photo upload), rooms, seasonal rates, room blocks, hotel settings,
  staff accounts, contact inbox, FAQ
- Content kept from the base project: blog, FAQ, contact form, staff spotlights, auth, social login
- Roles: `owner`, `admin`, `receptionist`, `editor`, `guest`

## Install a new client

```bash
git clone <this repo> hotel-api && cd hotel-api
composer install --no-dev --optimize-autoloader
cp .env.example .env && php artisan key:generate
# edit .env: DB_*, HOTEL_*, PAYPAL_*, OWNER_*, FRONTEND_URL, MAIL_*
php artisan migrate --seed --force        # roles + owner account (+ demo rooms if SEED_DEMO_HOTEL=true)
php artisan storage:link
```

Two processes must run in production:

```cron
* * * * * cd /var/www/hotel-api && php artisan schedule:run >> /dev/null 2>&1
```
```ini
; supervisor: emails are queued
[program:hotel-queue]
command=php /var/www/hotel-api/artisan queue:work --tries=3 --max-time=3600
```

### PayPal setup
1. developer.paypal.com → Apps & Credentials → create app (sandbox first) → copy client ID/secret.
2. Add a webhook to `https://<api-domain>/api/webhooks/paypal` with events
   `CHECKOUT.ORDER.APPROVED`, `PAYMENT.CAPTURE.COMPLETED`, `PAYMENT.CAPTURE.DENIED`,
   `PAYMENT.CAPTURE.REFUNDED` → copy its ID into `PAYPAL_WEBHOOK_ID`.
3. Use the client's own PayPal business account in live mode; money goes straight to them.

## Booking flow (frontend contract)

| Step | Call |
|---|---|
| Hotel info + PayPal client ID | `GET /api/hotel` |
| Search | `GET /api/availability?check_in=&check_out=&adults=&children=` |
| Book | `POST /api/bookings` → `{reference, manage_token, status, payment: {paypal_order_id}}` |
| PayPal `createOrder` | return `payment.paypal_order_id` from the step above |
| PayPal `onApprove` | `POST /api/bookings/{ref}/paypal/capture {order_id}` + header `X-Booking-Token` |
| Card declined | `POST /api/bookings/{ref}/paypal/retry` → new order id |
| Manage | `GET /api/bookings/{ref}?token=` · `POST /api/bookings/{ref}/cancel` |

Errors from the booking flow return `{message, code}`; codes include `no_availability`,
`invalid_dates`, `min_nights`, `too_many_guests`, `hold_expired`, `payment_failed`,
`payment_method_disabled`, `gateway_unavailable`.

## Back-office endpoints

All under `auth:sanctum` + `staff`, each guarded by a permission:

| Endpoints | Permission | Roles |
|---|---|---|
| `GET dashboard`, `GET calendar?from=&days=`, `GET bookings`, `GET bookings/{id}` | `bookings.view-any` | owner, admin, receptionist |
| `POST bookings` (walk-in), `PATCH bookings/{id}/status`, `POST bookings/{id}/mark-paid` | `bookings.manage` | owner, admin, receptionist |
| `POST bookings/{id}/cancel`, `POST bookings/{id}/refund` | `bookings.cancel-any` | owner, admin, receptionist |
| `GET room-types`, `GET rooms`, `GET rates` | `rooms.view` | all staff |
| room-types / rooms create, update, delete; `room-types/{id}/images` | `rooms.manage` | owner, admin |
| `rates` create, update, delete | `rates.manage` | owner, admin |
| `room-blocks` | `room-blocks.manage` | owner, admin, receptionist |
| `settings` | `settings.manage` | owner, admin |
| `staff` | `staff.manage` | owner, admin (only owners manage owners; the last owner is protected) |
| `contacts`, `faqs`, `spotlights`, `blogs` | content permissions | owner, admin, editor (+ receptionist for contacts) |

Room types and rooms that have booking history cannot be deleted (409 `has_bookings`);
deactivate them instead. Photos are stored on the `public` disk, so run
`php artisan storage:link` and set `APP_URL` to the API's public address.

## Tests

```bash
php artisan test                          # SQLite in memory
DB_CONNECTION=mariadb DB_DATABASE=hotel_test DB_USERNAME=... DB_PASSWORD=... php artisan test
```

PayPal is faked with `Http::fake()`; no credentials needed.
