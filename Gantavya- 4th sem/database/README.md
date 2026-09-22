# Database notes

`../schema.sql` is the complete database for this version of Gantavya EMIS.

The original development database used a different bookings/payments structure and is not compatible with the secure Khalti flow. For this unfinished project, export any records you want to keep, remove the old `gantavya_db`, and import `schema.sql` as a fresh database.

The SQL file does not include a default administrator password. After importing it, open `setup-admin.php` once to create the first administrator.
