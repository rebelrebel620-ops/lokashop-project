# LokaShop — October 16 update

## Apply this update on Windows

1. Back up your database and both project folders.
2. Extract each ZIP to a temporary folder. Copy its contents into the matching existing project folder. Keep your current `.env`, uploaded images, and the `lokashop-shared` folder beside your projects. The ZIP intentionally excludes `.env` so your settings are not overwritten.
3. In EACH project terminal run:

```powershell
php artisan optimize:clear
php artisan migrate
php artisan lokashop:sync-storage
```

If both projects use the same database, migrations added by the first project are already recorded when the second project runs. Both projects include the same shared migration names. Do not import `database.sql`, run `migrate:fresh`, or run seeders on your existing database.

4. Start ecommerce and logistics as usual. Keep their public and private upload storage settings pointed at the same existing shared folders. Uploaded images also have a `/media/...` route so displaying them does not require a Windows storage symlink. Existing images in `public/storage` are supported too.
5. Use a fresh registration email. Before OTP verification, only the verification-code email should arrive. The account-created message is sent only after successful OTP verification. Sellers, sorting centers and couriers then wait for approval; buyers can log in immediately after verification. The uploaded baseline already implemented this ordering; this update clarifies the email wording and adds regression assertions. Previously delivered email messages cannot be removed by this update.

## Changes and code locations

| Task | Result | Main code |
| --- | --- | --- |
| Registration email | OTP explicitly says registration is incomplete; welcome email follows successful OTP verification only | `app/Services/PendingRegistration.php` |
| Profile photos | Buyers, sellers, riders/drivers and sorting centers can upload JPG/PNG up to 5 MB; admins cannot | `app/Services/Cart.php`, `resources/views/profile.blade.php` |
| Categories | 12 main categories and all 72 numbered subcategories from your list | `config/registration.php`, taxonomy migration |
| Seller category | Registration selects one permanent main category; product and draft writes validate that seller's subcategory | `app/Services/ProductCategories.php`, `MarketController.php`, `DraftController.php` |
| Store name | Locked in store settings, POST validation and rejected-application resubmission | `MarketController.php`, `ReviewController.php` |
| Legacy store categories | Clear old names map automatically; ambiguous names have a one-time permanent assignment under Admin > Users | taxonomy migration, `routes/web.php`, `admin_users.blade.php` |
| Cart | Select products with checkboxes; empty selection is rejected; unselected products stay saved | `cart.blade.php`, `Commerce.php` |
| Cart actions | Update and red Remove actions share one row | `cart.blade.php` |
| Buyer notifications | Separate Notifications navigation/page; bell opens it; removed from Account | `CommonController.php`, `app.blade.php`, `notifications.blade.php` |
| Buyer account | Centered account, photo, security and address sections | `profile.blade.php` |
| Seller product tools | Products retains its design and includes drafts, purchase limits, activation, duplication and variant editing/removal; old Catalog tools URL redirects | `seller_products.blade.php`, `MarketController.php` |
| Remove product | Red Remove beside Update; existing unfinished-order protection and soft deletion retained | `seller_products.blade.php`, `MarketController.php` |
| Product photos | Actual uploaded photos on shop, landing page, dashboard, cart and seller listings; clear fallback when no image exists | `product-photo.blade.php`, `Support.php`, media route |
| Commission | 10% on merchandise after seller-funded discounts for new checkouts; shipping excluded; historical order commissions retained | `app/Services/Commerce.php` |
| Buyer approval | Verified buyers need no admin approval; existing verified pending buyers are normalized; missing verification dates display Unverified | verified-buyers migration, `admin_users.blade.php` |

## Existing stores and products

The migration preserves all products, category IDs, orders and historical amounts. Old broad categories are retained internally for existing product references but hidden from the new category selectors. Sellers must select a valid new subcategory when editing older products. Ambiguous old main categories (for example Fashion or Other) require a one-time administrator assignment under Users; the system will not guess. Once a valid main category is assigned it is permanent. Seller-defined display groups in Store settings remain separate from the permanent platform main category.

## App clients

Buyer and courier profile APIs accept `multipart/form-data` with `name`, the unchanged `phone`, and optional `profile_photo`. Buyer account and courier JSON expose `profile_photo_url`. No Flutter project was attached, so these ZIPs update Laravel pages/APIs only; a separate Flutter UI would need its own file picker if it does not already have one. Driver accounts use role `rider` with courier type `driver`.

## Validation

All application/controller/service, route, config, migration and test PHP files passed a PHP grammar syntax check. ZIP integrity and static feature checks passed. PHP/Laravel/MySQL and browser runtime checks could not be executed in the editing environment because PHP was unavailable. The following disposable SQLite regression scripts are included for your PHP 8.2+ machine (enable SQLite, mbstring, fileinfo and XML extensions):

Ecommerce:
```powershell
php tests/registration_checks.php
php tests/october16_feature_checks.php
php tests/profile_photo_checks.php
```

Logistics:
```powershell
php tests/public_courier_checks.php
php tests/profile_photo_checks.php
```

These scripts configure an in-memory SQLite database rather than modifying your configured MySQL database. Test with your local server: register each role, verify with the emailed OTP, upload a picture, create a product in the correct subcategory, attempt an invalid category POST, select one of two cart products, and check the resulting new order commission. Check both desktop and mobile widths before submitting school screenshots.
