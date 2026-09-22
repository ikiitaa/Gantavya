# Manual acceptance test

## Account and booking

1. Register a new customer with a unique email and a password of at least eight characters.
2. Select each service type and confirm the checkout asks for name, email, phone, travel date, pickup/meeting address, destination, travelers, and special requests.
3. Change the browser's displayed estimate with developer tools and submit. Confirm the database total still follows the product price in `products`.
4. Confirm the new booking begins with `payment_status = initiated` or `failed`, never `paid`.

## Khalti sandbox

1. Complete a sandbox payment and confirm the callback shows “Payment verified.”
2. Confirm the booking is `paid`, the booking becomes `confirmed`, and `payments.transaction_id` is stored.
3. Cancel a Khalti checkout. Confirm the booking is not paid and My Bookings offers a retry.
4. Force or obtain a Pending status. Confirm My Bookings offers a status recheck and does not allow a second payment attempt while pending.
5. Open the callback URL and alter its `pidx`, `state`, or amount-related Khalti data. Confirm the booking remains unpaid.
6. Use Admin → Payments → Recheck Khalti for a pending payment and confirm only Khalti's real lookup response changes payment status.

## Admin

1. Create the first administrator through `setup-admin.php`; reload that page and confirm it is locked.
2. Verify customers cannot open any `/admin/` management page.
3. Edit a product price, description, traveler capacity, and image.
4. Deactivate a product and confirm it disappears from the customer homepage.
5. Update an operational booking status. Confirm there is no control to manually mark the payment paid.
6. Disable a customer account and confirm it can no longer log in.
