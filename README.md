# LokaShop

LokaShop consists of two Laravel websites and two Flutter mobile applications. The websites share one MySQL database and shared upload storage. Mobile applications connect to the corresponding website APIs.

## Projects and roles

| Folder | Application | Users |
| --- | --- | --- |
| `lokashop-ecommerce` | Marketplace website and buyer API | Buyer, seller, ecommerce admin |
| `lokashop-logistics` | Logistics website and logistics APIs | Logistics admin, sorting center, rider, driver |
| `lokashop_buyer_mobile` | Flutter buyer application | Buyer |
| `loka_rider` | Flutter logistics application | Rider, driver, sorting center |
| `lokashop-shared` | Shared public and private uploads | Used by both Laravel applications |

The logistics mobile app serves three operational roles; its folder name does not mean it is limited to riders. Sellers and administrators use the web portals.

## Requirements

- Websites: PHP 8.2+, Composer, MySQL, and Node.js/npm. The source requires Laravel 12.
- Mobile: Flutter 3.35+ with Dart 3.9+, as declared in both mobile projects, plus the Android SDK for Android builds.
- Windows development: VS Code and XAMPP can provide the editor, MySQL, PHP, and phpMyAdmin.
- PHP must have the extensions required by Composer and the application, including MySQL database support, file information, and multibyte strings. GD is needed for image-processing features and tests that generate images.

## Local website setup

Keep the two Laravel folders beside each other. Their default upload paths resolve to the same sibling `lokashop-shared` directory.

### 1. Configure the applications

Start MySQL in XAMPP. Apache is needed for XAMPP phpMyAdmin; the commands below use Laravel's development server to host the websites.

In each Laravel folder, copy `.env.example` to `.env`. In PowerShell:

```powershell
Copy-Item .env.example .env
```

Use the same database connection in both applications:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lokashop_db
DB_USERNAME=root
DB_PASSWORD=
```

Use the actual MySQL password and port on your computer. Set `APP_URL=http://127.0.0.1:8000` for ecommerce and `APP_URL=http://127.0.0.1:8001` for logistics. Keep application keys separate. Local HTTP development uses `SESSION_SECURE_COOKIE=false`.

In **each** Laravel folder:

```powershell
composer install
php artisan key:generate
npm install
npm run build
```

Generate keys during initial installation. Do not regenerate an existing application's key as a routine update step.

### 2. Initialize the shared database once

The ecommerce project is the schema migration owner. Choose one initial installation method:

- Import the supplied `lokashop-ecommerce/lokashop_db.sql` into MySQL using phpMyAdmin; or
- Create an empty `lokashop_db`, then run the following **inside ecommerce only**:

```powershell
php artisan migrate
php artisan db:seed
```

Do not combine a full SQL import with a second schema creation. Do not use `migrate:fresh` on an existing database you want to keep. Logistics uses the same tables; do not independently initialize or migrate a second schema there.

For an existing installation, back up the shared database, inspect `php artisan migrate:status` in ecommerce, and apply outstanding migrations from ecommerce. Run migrations only when the supplied change requires them. Copying CSS, views, or route fixes alone does not require a new database import.

### 3. Configure shared uploads

Both projects default to:

```text
lokashop-shared/public
lokashop-shared/private
```

For separate server locations, configure both applications to use the same accessible storage locations:

```dotenv
LOKASHOP_PUBLIC_STORAGE=C:/path/to/lokashop-shared/public
LOKASHOP_PRIVATE_STORAGE=C:/path/to/lokashop-shared/private
```

Keep private uploads outside the public web root. Product media, return evidence, identity documents, and delivery proof have different access rules; do not make every uploaded file publicly accessible.

### 4. Run the websites

Terminal 1, inside `lokashop-ecommerce`:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2, inside `lokashop-logistics`:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

Open ecommerce at `http://127.0.0.1:8000` and logistics at `http://127.0.0.1:8001`. Keep both processes and MySQL running.

### 5. Create administrator accounts and configure email

There is no universal demo password guaranteed for the current database. Use existing authorized accounts or create an administrator in each project's terminal:

```powershell
php artisan lokashop:create-admin admin@example.com
```

Use a different intended email in each project. The command prompts for a password and creates the administrator role corresponding to that project.

The example environment uses `MAIL_MAILER=log`; this writes mail to the application log instead of sending it. Configure a real mail transport for email verification, activation, and recovery on a deployed installation. Accounts must complete the verification and approval steps required by their role before operational access is available.

### 6. Run scheduled maintenance

For local development, run the scheduler from ecommerce in another terminal:

```powershell
php artisan schedule:work
```

Scheduled work handles time-dependent workflows such as stale offers and eligible automatic receipt completion. A deployed installation needs a persistent scheduler arrangement. Use one designated scheduler against the shared database rather than unintentionally running duplicate jobs from both applications.

## Main features in the source

### Ecommerce website

- Public shopping pages, category carousel, search/filtering, product variations, stock, wishlist, and store following.
- Product image/video gallery, cart, vouchers, addresses, checkout, and tracking.
- Buyer and seller order tabs, including **Returns & Cancelled**, with matching counts and filters.
- Seller listings, variations, promotions, pickup requests, labels, sales, and review replies.
- Buyer return requests and escalation; seller decisions and returned-item receipt confirmation.
- Account review, product review, complaints, announcements, reports, and financial views for administrators.
- Role-scoped charts, notifications, internal messages, profile settings, and wallets.

### Logistics website

- Courier applications, approval/rejection, activation, approved courier lists, and area assignments.
- Pickup verification, parcel receipt, sorting, local delivery, and transfers between centers.
- Driver bulk trips, handover and receiving steps, tracking history, and proof-related workflows.
- Rider availability, assignment acceptance, delivery attempts, earnings, and COD remittance.
- Center financial reports, role-scoped charts, messages, notifications, and account management.

Rider pickup/delivery capacity is configured at 20 active assignments under the current dispatch rules. This is not a promise that every parcel will list a rider: approval, activation, availability, area coverage, existing workload, and assignment state also affect eligibility. Driver/trip rules are separate.

### Mobile applications

The buyer app includes catalog/cart/checkout, orders and tracking, return evidence, messages, notifications, wallet/account features, and **Account → Stats**. Its product detail media uses one horizontal thumbnail carousel for photos and video.

The logistics app contains rider, driver, and sorting-center workspaces, with role-specific operations, account/stats, financial and communication features. Available actions depend on the signed-in role and backend permissions.

These features exist in source. Device builds and real-device behavior still require testing; feature presence is not a certification that every mobile flow works.

## Order, return, and money rules

### Normal order flow

1. A buyer selects a product/variation, chooses an address and payment method, and checks out. A checkout involving several sellers creates separate seller orders.
2. The seller accepts/prepares the order and requests pickup. The sorting center selects an eligible area rider; the seller does not choose an arbitrary rider.
3. The rider accepts and follows the pickup/scan workflow. The center receives and sorts the parcel.
4. For another municipality, the relevant transfer/trip and destination-center handover steps occur before local delivery.
5. The assigned rider records delivery, including the required proof and exact COD amount when applicable.
6. The buyer confirms receipt, or eligible delivered orders reach automatic completion after seven full days. An active return blocks receipt settlement.

### Returns

- Return requests must meet the current eligibility window and order-state rules. The buyer supplies a reason, description, and supporting evidence.
- After a request is submitted, the buyer order card shows **View my return** instead of the receipt action, delivery-photo action, and receipt reminder. The delivery proof remains stored in the system.
- Sellers review pending requests using approve/reject actions and confirm receipt when the returned items arrive. Buyers can escalate eligible rejected requests.
- Seller approval alone is not confirmation that the goods have arrived or that the refund has completed. Follow the request's current actions and status.
- Return evidence remains private and is not compressed by the product-video workflow.

### Wallet, COD, and shipping

- COD collection records cash collected by the rider. Remittance records cash handed to the responsible sorting center; center confirmation is a separate step.
- Seller COD earnings and the associated platform commission require eligible order completion and confirmed COD remittance. Receipt confirmation alone does not release unremitted COD funds.
- Remittance references are generated automatically when omitted. The interface includes **Remit all**; shortage top-ups and center confirmation retain their own validation rules. Center confirmation does not require a reason field.
- Current shipping distribution uses a single fee pool: **50% sorting centers, 25% drivers, and 25% delivery riders**. A missing driver's share remains unallocated. Historical credited shipments retain legacy treatment where the implementation requires it.
- COD order totals round to whole pesos; wallet payments retain centavos. These are local application ledgers, not proof of an external bank or payment-provider transfer.
- Public cash-in and manual earnings-claim controls have been removed. Do not restore removed cash-in methods just to satisfy obsolete tests.

## Product videos and responsive design

Seller product video uploads target approximately **420p**, preserve aspect ratio, and must be **three minutes or shorter**. The browser upload flow accepts source MP4/WebM files up to 100 MB and targets an output of at most 20 MB. Larger resolutions are reduced without upscaling smaller videos. Browser recording support is required for client-side conversion; output codec support still varies by browser/device.

Server-side FFmpeg optimization is optional and separate. The maintenance command `php artisan lokashop:optimize-product-videos` requires FFmpeg when used; configure `FFMPEG_BINARY` if it is not on the system path. Browser resizing does not require a server FFmpeg installation. Existing videos are not automatically converted merely by copying a view or stylesheet patch.

Phone layout density changes are scoped to widths up to 600px. Authenticated phone/tablet navigation drawers use a 1024px breakpoint, with a menu toggle, close button, and backdrop. Larger desktop layouts retain their existing navigation. Ecommerce and logistics use different dark-green sidebar pictures.

## Run the mobile apps

Run these commands inside the corresponding Flutter project:

```powershell
flutter pub get
flutter analyze
```

For an Android emulator using the local Laravel servers:

Buyer:

```powershell
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api
```

Logistics:

```powershell
flutter run --dart-define=LOGISTICS_BASE_URL=http://10.0.2.2:8001
```

The buyer URL includes `/api`; the logistics URL is the server root. For a physical phone, use the computer's reachable LAN address instead of `127.0.0.1` or `10.0.2.2`, bind the development server to an accessible interface, and permit the chosen development ports through the firewall.

The source defaults target `https://lokashop.trade/api` for buyers and `https://logistics.lokashop.trade` for logistics. A default address does not establish that the server is online. Use your actual environment. Prefer HTTPS for deployed/release builds; do not assume debug HTTP allowances also apply to release APKs.

After device validation, an Android release build can be produced with the matching environment value:

```powershell
flutter build apk --release --dart-define=API_BASE_URL=https://your-ecommerce-host/api
```

For logistics, replace the define with `LOGISTICS_BASE_URL=https://your-logistics-host`. Configure release signing and deployment credentials for the intended distribution.

## Applying changed-file ZIPs

Copy files into the matching project folders while preserving their relative paths. Back up files before replacing them. A changed-file ZIP is a patch, not a complete replacement installation.

After replacing Laravel files, run in each affected project:

```powershell
php artisan optimize:clear
```

If `resources/css`, JavaScript build inputs, or package dependencies changed, rebuild with `npm run build`. Hard-refresh the browser after public asset changes. For mobile source/dependency changes, run `flutter pub get` and rebuild the app; copying Dart files does not update an installed APK.

## Verification status and known test limitations

The latest automated web checks used disposable SQLite fixtures and Laravel's HTTP kernel. After the supplied fixes, 562 requests across both projects completed without server errors, including invalid-input and missing-record cases. Expected validation/authorization/not-found responses were included; this does not mean every request returned HTTP 200.

Confirmed fixes include malformed search/login input handling, malformed record URL handling, and the ecommerce phone/tablet drawer close controls. Drawer behavior was checked with scripted DOM mocks rather than physical-device visual testing.

The stricter PHP regression run reported **71 of 90 suites passing** before the two later record-route suites were added. The remaining 19 stop on obsolete methods, outdated controller calls, incomplete fixture tables, wrong-project references, a registration-session fixture, or the missing GD extension in the audit runtime. The later record-route checks passed separately. Do not describe this as a completely passing test suite or restore retired application features to silence old tests.

Real MySQL concurrency, external email/SMS/payment integrations, complete cross-site handoffs, physical browser/device behavior, Flutter analysis/builds, and actual mobile video codec playback have not been certified by these checks. Validate those against the intended deployment before release.

Useful commands:

```powershell
php artisan route:list
php artisan migrate:status
php tests/workflow_checks.php
php tests/malformed_web_input_checks.php
php tests/record_route_checks.php
```

Use `migrate:status` from ecommerce. The supplied integration fixtures use isolated databases; inspect any unfamiliar test before running it. Laravel can print a test exception while exiting with code zero, so inspect exception output and final assertions instead of trusting the process exit status alone.

## Deployment notes

Serve each Laravel application's `public` directory as its web root. Set correct HTTPS `APP_URL` values, separate application/session configuration, `APP_ENV=production`, `APP_DEBUG=false`, secure cookies, appropriate database credentials, shared storage permissions, and an active scheduler.

Keep `.env`, database backups, private documents, and tokens out of public downloads and version control. Configure and test mail transport, backups, upload limits, TLS, and any external integrations. A tunnel to a personal computer works only while that computer and its required services remain running.
