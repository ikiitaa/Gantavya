# Gantavya EMIS

Gantavya EMIS is a Core PHP and MySQL travel-booking management system for vehicle rentals, Nepal treks, and international tours. It includes customer accounts, detailed booking checkout, Khalti KPG-2 payment integration, booking history, and an administrator dashboard.

## Included features

- Customer registration, login, logout, session security, and CSRF protection
- Customer travel-assistant chatbox for services, prices, booking steps, tracking, and payment support
- Travel date, customer name, email, Nepal phone number, pickup/meeting address, destination, travelers, rental days, and special requests
- Server-side price calculation from database product prices
- Vehicle daily pricing, trek group discount, and international group discount
- Khalti ePayment initiation on the PHP server
- Redirect to Khalti's hosted checkout page
- Server-side Khalti lookup after callback
- Success only for `Completed` with matching PIDX, amount, and transaction ID
- Pending, cancelled, expired, failed, and refunded handling
- Customer booking history, safe pending-payment recheck, failed-payment retry, and inactive unpaid-booking cancellation
- First-admin setup with no default password
- Admin dashboard, booking operations, payment audit/recheck, product management with image upload, and user access controls

## Requirements

- XAMPP with PHP 8.1 or newer and MySQL/MariaDB
- PHP extensions: PDO MySQL, cURL, OpenSSL, JSON, and Fileinfo
- A Khalti sandbox merchant account for testing or a production merchant account for live payment

## XAMPP setup on Windows

1. Extract the project to `C:\xampp\htdocs\proj4`.
2. Start Apache and MySQL from the XAMPP Control Panel.
3. Open `http://localhost/phpmyadmin`.
4. The old development schema is incompatible with this completed payment flow. Export anything you need, delete the old `gantavya_db`, and import `schema.sql` from this project.
5. Copy `.env.example` and rename the copy to `.env`. Make sure Windows has not named it `.env.txt`.
6. Keep these local values unless your XAMPP database is different:

   ```env
   APP_URL=http://localhost/proj4
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=gantavya_db
   DB_USERNAME=root
   DB_PASSWORD=
   KHALTI_MODE=sandbox
   KHALTI_SECRET_KEY=your_sandbox_live_secret_key_here
   ```

7. Get the sandbox server key from your Khalti test merchant dashboard. Khalti calls it the `live_secret_key` even in its sandbox dashboard. Do not use a public key here and never place the secret key in JavaScript.
8. Open `http://localhost/proj4/setup-admin.php` and create the first administrator.
9. Open `http://localhost/proj4/` to register a customer and test a booking.

## Khalti sandbox test details

Khalti's current sandbox documentation provides these test credentials:

- Test IDs: `9800000000`, `9800000001`, `9800000002`, `9800000003`, `9800000004`, or `9800000005`
- MPIN: `1111`
- OTP: `987654`

The integration uses:

- Sandbox: `https://dev.khalti.com/api/v2/epayment/initiate/` and `/lookup/`
- Production: `https://khalti.com/api/v2/epayment/initiate/` and `/lookup/`

Official documentation: <https://docs.khalti.com/khalti-epayment/>

## How payment safety works

1. The PHP server loads the selected product and calculates the total. It does not trust a total sent by the browser.
2. A pending booking and a payment-attempt record are created.
3. PHP initiates Khalti ePayment using the secret key and sends the customer to the returned `payment_url`.
4. Khalti redirects to `khalti-callback.php` with a PIDX.
5. The callback validates a random state token and matches the stored PIDX.
6. PHP calls Khalti's lookup endpoint.
7. Only `Completed`, with the matching PIDX, amount, and transaction ID, sets the booking to paid.

A payment that Khalti reports as `Pending` cannot be retried as a second attempt. The customer or admin must recheck it first, which prevents accidental double payment.

There is no simulation, automatic success fallback, client-side success endpoint, or admin “mark paid” button.

## Admin pages

- `/admin/index.php`: overview and verified revenue
- `/admin/bookings.php`: search and filter bookings
- `/admin/booking.php`: booking details and operational status
- `/admin/payments.php`: Khalti attempts and secure lookup rechecks
- `/admin/products.php`: create, edit, activate, deactivate, and upload images
- `/admin/users.php`: customer/admin account list and access controls

## Testing

From an XAMPP shell in the project folder, run:

```bash
php tests/run.php
```

Then complete every item in `tests/MANUAL_TEST_CHECKLIST.md`, including successful, cancelled, pending, and tampered callback scenarios.

## Going live

1. Complete sandbox testing.
2. Set `APP_URL` to the exact HTTPS production URL.
3. Set `APP_ENV=production` and `APP_DEBUG=false`.
4. Set `KHALTI_MODE=production`.
5. Replace the sandbox key with the production `live_secret_key` from the Khalti production merchant dashboard.
6. Confirm the production PHP server has a valid CA certificate bundle so cURL TLS verification remains enabled.
7. Make a small real payment and verify it appears in the Khalti dashboard, the `payments` table, and the admin payment page.

Never commit `.env` or share the Khalti secret key in screenshots, source files, or chat messages.
