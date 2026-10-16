# LokaShop fixes — October 15 revision

## Apply this update on Windows

1. Back up both project folders and export your MySQL database.
2. Stop both Laravel servers and the scheduler with Ctrl+C.
3. Extract the ZIP. Copy the **contents** of `lokashop-ecommerce` over your existing ecommerce folder. Copy the contents of `lokashop-logistics` over your logistics folder. Choose Replace for code files.
4. Keep your existing `.env` files, APP_KEY values and uploads. The projects must stay next to each other and use the same MySQL database.
5. Run `UPDATE_WINDOWS.bat` in ecommerce, then in logistics. The second app can say “Nothing to migrate”; that is expected for the shared database.
6. Start ecommerce with `php artisan serve --port=8000`, logistics with `php artisan serve --port=8001`, and the scheduler in a third ecommerce terminal with `php artisan schedule:work`.

Do not use `migrate:fresh` or `db:wipe`. The new `000004_usability_and_territories` migration adds fields and retains your existing records. Old orders keep their original grouping and amounts.

## Changes in this revision

| Area | Behavior |
|---|---|
| Logout | Explicit Yes / No confirmation in both sites. No stays on the current page. |
| Notifications | Separate account-created email; approval/rejection email includes the decision and rejection reason. Account status changes also create a notification and email. |
| Addresses | Local Region → Province → City/Municipality → Barangay dropdowns in registration, saved-address editing and account-address forms. Street and postal code are still entered manually. |
| Product navigation | Buyer product pages use the same navbar and layout as the shop. |
| Checkout | Missing request key fixed; COD is the only supported method for new orders. Exactly one platform, one shipping and one store voucher may be selected; all three are optional. Claimable selected vouchers are claimed automatically. |
| Product orders | Each distinct product becomes a separate order. Two units of the same product stay together. Different variants of the same product also stay together. Shipping is calculated once per store and allocated across its product orders. Discounts and 5% seller commission reconcile across the split orders. |
| Filters | Generic search/date filters removed from management forms; report filters are collapsed. Secondary management forms are collapsible. |
| Account | Password and verified contact changes are inside the ecommerce Account page. Address editing is also available on Account. |
| Creation forms | Database IDs are generated automatically. Referenced products, orders, companies and couriers are selected by name. Multiple-product promotions use selections instead of typed IDs. |
| Store | Prefilled store details, logo/cover, vacation setting, category cards and product/category selections. |
| Complaints | Admin navigation and resolution forms now use admin routes; buyers choose an owned order from a dropdown. |
| Account management | Admins can suspend, ban, archive or restore accounts with a reason. Ecommerce admins manage buyers/sellers; logistics admins manage centers/riders/drivers. History is preserved. |
| Vouchers | Admin and seller use the same create/edit template. Admin additionally creates shipping discounts. Seller menu → Vouchers; Admin → Platform vouchers. |
| Centers | One new center registration per municipality, protected by a unique database key. Sta./Santa aliases are normalized. The center's registered city/municipality defines its service territory. |
| Parcels | Parcels is labeled as the operational parcel list, separate from Overview. The duplicate Operations menu was removed. |
| Couriers | Public registration and center-created invitations have Logistics rider and Logistics driver choices. Riders use motorcycles/tricycles; drivers use 4-, 6-, 8- or 10-wheel trucks. |
| Local riders | Pickup and customer delivery must be within their registered municipality. Their service address is inherited from their center. |
| Drivers | Only inter-center transfers. A driver cannot pick up from a seller or deliver directly to a buyer. Destination-center staff confirm receipt, then a local rider completes customer delivery. |
| Legacy riders | Working authorized route and name-based company/courier selections. Linking assigns courier type, vehicle and service address. Pending accounts receive an activation invitation. |

## Logistics setup after upgrading

1. Logistics admin → **Territories**. Check each center's municipality. The migration derives it from existing addresses and reserves each municipality once. It never deletes duplicate legacy centers. A center marked “Needs municipality assignment” needs an unused municipality assigned by the admin. Territory changes are blocked while that center has unfinished parcels.
2. Existing couriers keep the default type **rider**. For unlinked couriers, use **Legacy riders** to select their company, courier type and vehicle. New couriers are created through the center's **Riders and drivers** invitation form. Public registration also supports riders/drivers through Register as rider or Register as driver. Public applicants verify email and await logistics-admin approval.
3. Each center adds active **Shipping rates** for its own municipality and **Areas** with an approved local rider. New checkout requires an approved active destination center and an active matching shipping rate. No shipping amount is invented when a rate is missing.
4. Local riders and drivers go Online under **Availability**. Vehicle type must match the registered courier type.
5. For a different buyer municipality: source local pickup → source center receives parcel → **Center transfers** selects an Online driver → driver accepts and departs → destination center opens Center transfers and confirms receipt → destination center sorts and assigns its local rider.
6. If a transfer offer expires, the source center can offer it again from Center transfers. Accepted transfers cannot be reassigned through that form. The standard delivery photo/OTP/COD checks remain in place.

The registered municipality cannot be changed from a center/courier profile. Logistics admin manages territory corrections. Archiving or banning a center does not automatically resolve parcels it still holds; review those operational records.

## Enable actual Gmail delivery

The code sends mail through Laravel's configured mailer. `MAIL_MAILER=log` writes messages locally and does **not** deliver them to Gmail. Keep your existing working SMTP configuration if already configured.

For an eligible Gmail account, enable 2-Step Verification and create a Google App Password. Put your own settings in BOTH `.env` files:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-address@gmail.com
MAIL_PASSWORD=your_app_password_without_spaces
MAIL_FROM_ADDRESS=your-address@gmail.com
MAIL_FROM_NAME="LokaShop"
```

Run `php artisan optimize:clear` in both folders after editing. Test with a new registration, OTP, and an admin rejection. If Google policy does not allow App Passwords, use your approved SMTP provider instead. Real email delivery could not be tested here because your provider credentials were not supplied. Do not replace APP_KEY.

Google references: https://support.google.com/mail/answer/185833 and https://support.google.com/mail/answer/7104828 .

## Validation and limits

- 104 registration/OTP regression checks passed.
- Ecommerce: 53 base workflow checks + 30 revision checks passed.
- Logistics: 48 base workflow checks + 30 revision checks passed.
- 12 DOM interaction checks passed for logout Yes/No, address cascading/reset, rider/driver vehicle choices and request-key injection.
- PHP syntax, Laravel route loading and Blade compilation checked in both projects. Exact counts are in VALIDATION.json.

The backend checks use disposable SQLite databases, not your MySQL database. MySQL migrations/locking, actual SMTP delivery and your complete Windows/browser setup still need checking locally. DOM checks are not a full visual browser test. These results do not mean all 469 original black-box cases were executed.

To rerun backend checks (PHP with PDO SQLite, SQLite3, mbstring, XML and ZIP):

```powershell
# Ecommerce folder
php tests/registration_checks.php
php tests/revision_checks.php
# Logistics folder
php tests/revision_checks.php
```

The dropdown dataset is a bundled snapshot from https://psgc.gitlab.io/api/ retrieved for this revision. It works without an external connection; it is not a guarantee of the latest administrative boundaries. Existing address values are retained for display; select a valid current combination from the bundled list when saving.

Only Laravel projects were supplied. Flutter UI changes (COD/voucher selectors, address dropdowns, courier type and transfer actions) require the Flutter source. The backend enforces these rules for API callers too. Existing historical/manual payments remain in history; new checkout rejects manual/online methods.
