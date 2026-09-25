# LokaShop — two Laravel websites, one MySQL database

This source package follows the attached ERP flow and component PDFs. It targets Laravel 12, PHP 8.2+, MySQL, Windows 10, XAMPP, Composer, and npm.

## Folder structure and implementation plan

```text
LokaShop-package/
├── lokashop-ecommerce/     buyer, seller, admin; database migration owner
│   ├── database/schema/lokashop_schema.sql
│   ├── database/migrations/
│   └── lokashop_db.sql      one-step phpMyAdmin import, with demo users
├── lokashop-logistics/     sorting center and courier; no migrations
└── README.md
```

The e-commerce app owns the schema. Both apps use `lokashop_db` and the same IDs, tables, parcels, and status history. Separate file sessions and different cookies prevent accidental cross-site login. The local URLs are `http://127.0.0.1:8000` (e-commerce) and `http://127.0.0.1:8001` (logistics).

**Initial database setup: choose ONE option.** The easiest is importing `lokashop-ecommerce/lokashop_db.sql` in phpMyAdmin. It includes every table, the migration ledger, and demo records. Do not follow that import with `migrate:fresh` or another SQL import. Alternatively, create an empty `lokashop_db`, then run `php artisan migrate` and `php artisan db:seed` **only inside `lokashop-ecommerce`**. Never migrate from the logistics folder. If a table already exists, stop: you likely imported SQL and attempted a second schema creation. Keep data by not dropping anything; check the `migrations` table before deciding what to do.

## Windows local installation, step by step

1. Install PHP 8.2 or newer (XAMPP's PHP version matters), Composer, and Node/npm. Open XAMPP Control Panel and start **Apache** and **MySQL**. Open `http://localhost/phpmyadmin` in your browser.
2. Create a database named `lokashop_db` in phpMyAdmin, click its **Import** tab, choose `lokashop-ecommerce/lokashop_db.sql`, and click **Import**. The SQL also creates the database, so you can import at the server level if your phpMyAdmin allows it. Run the import once.
3. Extract the folders somewhere on your PC, for example `C:\Users\YourName\Documents\LokaShop-package`. Open the package in VS Code. In each app folder, copy `.env.example` to `.env` (PowerShell: `Copy-Item .env.example .env`). Both must contain `DB_DATABASE=lokashop_db`, `DB_HOST=127.0.0.1`, `DB_USERNAME=root`, and the actual XAMPP root password (`DB_PASSWORD=` when empty). Use `DB_PORT=3306` normally, or `DB_PORT=3307` if your XAMPP MySQL server uses 3307. Both apps must use the same MySQL port.
4. In **each** app folder, run `composer install`, `php artisan key:generate`, `npm install`, then `npm run build`. Composer downloads Laravel into `vendor`; the key command writes the app encryption key into that app's `.env`; npm builds the CSS assets. Keep each app's `APP_KEY` separate. If Laravel says a directory is missing, ensure `storage/framework/{cache/data,sessions,views}` and `bootstrap/cache` exist and are writable.
5. In terminal 1, inside `lokashop-ecommerce`, run `php artisan serve --host=127.0.0.1 --port=8000`. In terminal 2, inside `lokashop-logistics`, run `php artisan serve --host=127.0.0.1 --port=8001`. Leave both terminals open. Open `http://127.0.0.1:8000` and `http://127.0.0.1:8001`. XAMPP Apache is used for phpMyAdmin in this local setup; Laravel's two `artisan serve` processes host the websites.
6. To inspect routes use `php artisan route:list` inside each folder. To confirm database access use `php artisan migrate:status` **in e-commerce**. For the SQL import it should show the existing migration as completed.

If you get **Access denied** or **Connection refused**, check that MySQL is green in XAMPP, both `.env` files have the correct password and MySQL port, then run `php artisan config:clear` in both folders. If CSS is missing, run `npm run build` again. If you get **table already exists**, do not keep importing or repeatedly run migrations; use one of the two initial database options above. The demo password hash is bcrypt.

## Demo accounts

All five accounts have password **`Password123!`**; change it before any public deployment.

| Role | Email | Website |
| --- | --- | --- |
| Buyer | `buyer@lokashop.test` | E-commerce, port 8000 |
| Seller | `seller@lokashop.test` | E-commerce, port 8000 |
| Admin | `admin@lokashop.test` | E-commerce, port 8000 |
| Sorting center | `sorting_center@lokashop.test` | Logistics, port 8001 |
| Rider | `rider@lokashop.test` | Logistics, port 8001 |

The demo buyer has a Santa Cruz, Laguna address. Demo product #1 is a reusable bag from demo seller. Delivery Area A matches Santa Cruz and is assigned to rider #5. Buyer, seller, and center registrations require approval; courier registration requires sorting center approval. Uploaded documents are stored privately and can be viewed by the corresponding reviewer. Registration captures the essential identity and permit fields; detailed sex/birthday and a province/barangay address dropdown from an API are not included.

## Full order test

1. At port **8000**, sign in as buyer, open the demo product, pick a variation and quantity, and add it to the cart. At `/cart`, choose the existing address, optionally use voucher `GREEN10`, select **Cash on delivery**, and place the order. You will see **PLACED** in My orders. One checkout with multiple sellers creates one order per seller.
2. Sign out and sign in as seller at port **8000**. Open **Orders**. Click **CONFIRMED**, then **PREPARING**. Request pickup from rider #5. Open the printable shipping label.
3. Sign in as the sorting center at port **8001**. Open **Pickups** and verify the request. This binds the parcel to this center and offers the rider the pickup assignment.
4. Return to the seller's **Orders** and click **READY_FOR_PICKUP**. Sign in as rider at port **8001**, open **Assignments**, accept the pickup, and click **Scan pickup from seller**. Status becomes **PICKED_UP**.
5. As the sorting center at port **8001**, open **Parcels**, click **Scan received**, select Area A to **Sort by area**, and **Assign area rider**. The center checks that the selected area's city and province match the buyer address.
6. As rider, accept the delivery assignment, click **Collect from center / out for delivery**, and click **Delivered**. For a failed delivery, enter a reason and click **Delivery failed**; the center can reschedule the assigned rider or return the parcel.
7. As buyer at port **8000**, open **My orders**, click **Confirm received**, then leave a rating. The final status is **COMPLETED**. Order, parcel, and dated `status_history` records share the same MySQL database, including which user made each change.

Role middleware blocks buyers from admin/seller/courier routes. Passwords use Laravel hashing; forms use CSRF protection and server-side validation. Order and stock creation use a database transaction and row locks. The sample payment method COD is a local workflow; manual payment is recorded as awaiting confirmation and does **not** charge anyone or prove payment. Do not accept payment based on that field without adding a verified provider and settlement checks.

## Cloudflare domain and optional services

For deployment, put `APP_URL=https://lokashop.trade` in the e-commerce `.env` and `APP_URL=https://logistics.lokashop.trade` in the logistics `.env`. Set `APP_ENV=production`, `APP_DEBUG=false`, secure cookies, a strong MySQL password, and production-safe credentials. Keep the local `.env` URLs and local ports when working on your PC. No local IP is hardcoded in application code.

For a Cloudflare Tunnel on the computer hosting both apps, configure two **Public Hostnames** in the same tunnel: `lokashop.trade` → `http://127.0.0.1:8000`, and `logistics.lokashop.trade` → `http://127.0.0.1:8001`. Cloudflare manages the public hostname/DNS records and HTTPS edge certificates for the tunnel. For a VPS without Tunnel, point the apex and logistics DNS records to the server and configure HTTPS plus two separate web server virtual hosts whose document roots are each Laravel app's `public` folder. Do not expose the full project directory as the web root. A tunnel connected to your own PC only works while that PC, the two Laravel processes, MySQL, and `cloudflared` remain running. For reliable availability use an always-on server. Check your current Cloudflare dashboard instructions before changing DNS or tunnel configuration.

Optional province/municipality/barangay API, real email approval notifications, SMS, online payments, live chat, barcode hardware, and a shipment carrier integration require separate providers or credentials. The local fallback uses manual address fields, in-app status notifications on Account, internal messages, printable text shipping labels, COD, and status buttons as scans. This is a starter implementation and has not been executed end-to-end in the authoring environment, which has no PHP, Composer, or MySQL installed. Check functionality on your Windows setup before public deployment.

## Current implementation scope

Implemented in source: two Laravel apps; logo assets, responsive pages and favicon; shared SQL schema and migration owner; buyer product search, variation/cart/checkout/address/receipt/rating; seller product creation/editing, variations, vouchers, orders, shipping labels, pickup requests and sales records; admin account approval, product approval, account activation, complaints, announcements and 10% commission fields; center rider approval, pickup verification, area assignment, parcel sorting and reports; rider pickup/delivery assignments, failed delivery reasons and earnings; role checks, messages, and status history.

Still needs work for production or a fully complete ERP: seller product archiving UI, editing delivery areas, policy editing, detailed financial charts/date filters and profit costs, seller responses to reviews, email notices, real-time messaging, proof-of-delivery media, online payment verification/refunds, warehouse scanning devices, fine-grained center-to-rider membership checks, and deployment hardening. See the actual forms and routes before representing any of these as a working feature.
