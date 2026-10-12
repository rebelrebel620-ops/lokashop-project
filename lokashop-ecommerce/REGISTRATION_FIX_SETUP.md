# LokaShop registration fixes — installation

These updates address the reported buyer and seller registration failures in your account-creation workbook. Logistics and rider cases were marked Not Run; their account flows are preserved. Address fields are required text inputs, not the cascading geographical dropdowns assumed in the unrun tests. The legacy Flutter registration API is a separate flow and has not been converted to OTP registration.

## 1. Back up and copy the files

1. Stop both `php artisan serve` terminals with Ctrl+C.
2. Back up both project folders and export `lokashop_db` using phpMyAdmin.
3. Extract the updated ZIPs to a temporary folder.
4. Copy the contents of each extracted project into the matching existing project folder and replace matching files. Do not nest a second project folder inside the first.
5. Keep your existing `.env` files and `storage/app` documents/uploads. The ZIPs exclude `.env`, saved uploads, sessions and logs. Vendor dependencies and built CSS are included; `node_modules` is excluded.
6. Keep both `.env` files pointing to the same existing `lokashop_db`. Keep their different session-cookie names. Do not generate a new APP_KEY when updating an existing installation.

## 2. Update the shared database once

Open a terminal inside `lokashop-ecommerce` and run:

```powershell
php artisan optimize:clear
php artisan migrate --path=database/migrations/2026_10_10_000001_registration_validation_and_otp.php
```

This command adds the registration fields and OTP table to your existing database. Run it only from e-commerce. Do not run `migrate:fresh` or reimport the original SQL: those are unnecessary for this update. The targeted path also avoids rerunning the original CREATE TABLE migration if your database was originally imported manually.

Open a terminal inside `lokashop-logistics` and run:

```powershell
php artisan optimize:clear
```

Existing accounts keep their current approval/access state. New web buyer and seller accounts require verification.

## 3. Choose how to receive verification codes

For local/classroom testing, put this in the e-commerce `.env`:

```dotenv
MAIL_MAILER=log
MAIL_FROM_ADDRESS=no-reply@lokashop.trade
MAIL_FROM_NAME="LokaShop"
```

Then run `php artisan optimize:clear`. Register using fictional information. Open `storage/logs/laravel.log` and find the latest message titled `Verify your LokaShop email`. Copy its six-digit code to `/verify-email`. Log mode does not send messages to a real inbox.

For real inbox delivery, use SMTP credentials from your mail provider:

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=YOUR_SMTP_HOST
MAIL_PORT=587
MAIL_USERNAME=YOUR_SMTP_USERNAME
MAIL_PASSWORD=YOUR_SMTP_PASSWORD
MAIL_FROM_ADDRESS=YOUR_PROVIDER_VERIFIED_SENDER
MAIL_FROM_NAME="LokaShop"
```

Use `MAIL_SCHEME=smtps` if your provider specifies implicit TLS on port 465. Use the host, port and sender allowed by your provider. Run `php artisan optimize:clear` after editing `.env`. SMTP delivery requires valid credentials; no email-provider account was configured for you.

## 4. Start both websites

In e-commerce:

```powershell
php artisan serve --port=8000
```

In logistics:

```powershell
php artisan serve --port=8001
```

Visit http://127.0.0.1:8000/register and http://127.0.0.1:8001. Built CSS is included, so `npm run dev` is not needed to use this update. If you later change CSS, run `npm ci` and `npm run build` in that project.

If vendor dependencies need reinstalling on another PC, run `composer install` in each project.

## 5. Retest the reported issues

- Names: separate first/last names; accept letters including ñ and spaces, hyphens, apostrophes and periods; length 2–50 per field.
- Buyer DOB: exactly 18 is accepted; younger or future dates are rejected.
- Mobile: accept `09XXXXXXXXX` or `+639XXXXXXXXX`, normalize to `09`, reject letters/incorrect lengths and existing numbers across roles.
- Email: trim spaces, lowercase, validate format and reject existing email addresses regardless of case.
- Password: minimum 8, uppercase/lowercase/number/special character; matching confirmation; Show/Hide button.
- Terms: acceptance required; linked student-project terms/privacy page included.
- Address: one registration address linked only to the registering user.
- Seller: unique 3–30-character shop name, category, seller type, formatted unique TIN and business address.
- Seller uploads: ID for all types; DTI+BIR for sole proprietorship; SEC+BIR for corporation; JPG/PNG/PDF up to exactly 5 MB.
- New accounts: Unverified initially. OTP expires after five minutes. Incorrect/malformed/expired codes fail. Resend invalidates the old code, has a one-minute cooldown and resets attempts. Five incorrect attempts require a resend.
- Verified buyer: approved/active for login. Verified seller: pending admin approval. Admin approval page shows shop details, TIN, address and submitted documents.
- Fast repeated Register clicks: button disables after valid submission; database constraints prevent duplicate new-account email, normalized mobile, TIN and shop name.
- Role access: buyer denied seller/admin routes. Use separate browser profiles or different browsers when testing two roles simultaneously. Tabs in one browser profile share that website's login session.

## Verification performed

104 automated assertions passed using a disposable SQLite database, including seller document requirements, exactly 5 MB acceptance, invalid data, duplicates, OTP state transitions, buyer role denial and Blade rendering. PHP syntax checks passed for both apps; both Vite builds passed. Local log-mail transport was exercised without sending external email.

You can rerun the disposable checks from e-commerce:

```powershell
php tests/registration_checks.php
```

These checks do not connect to your MySQL database. MySQL migration execution, real SMTP delivery and interactive browser/phone testing still need verification on your PC. The spreadsheet was not changed or marked Passed. Seller rejection/resubmission, cascading Philippine address dropdowns and the unrun logistics/rider activation workflows are not implemented by this update. Review the included student-project terms before using the project with real customers.
