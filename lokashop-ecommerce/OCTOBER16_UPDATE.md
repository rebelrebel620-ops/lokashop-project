# October 16 update — registration, account states and logistics addresses

## Install the update

1. Keep a backup of the current projects and MySQL database.
2. Extract the updated ZIPs. Keep your current `.env`, APP_KEY, uploaded files and existing shared storage settings when updating your working folders.
3. In EACH project folder run:

```powershell
php artisan optimize:clear
php artisan migrate
```

Then run each site as before. Do not run `migrate:fresh` on the existing database.
Keep the scheduler running in a separate terminal:

```powershell
php artisan schedule:work
```

The new migration creates `pending_registrations`; ecommerce also creates `account_appeals`. If both sites use one database, Laravel records the shared pending migration once.

## Registration behavior

Buyer, seller, public sorting-center and public rider/driver registrations now store an encrypted temporary application instead of creating a user. No addresses, seller businesses, sorting centers or document database records are attached to a real user until the correct email code is accepted. Passwords are hashed before temporary storage. Only the verification email is sent at submission; the account-created email is sent after verification commits. Buyers can then log in, while sellers and logistics applicants still await administrator approval.

Leaving the registration page creates no user account. Temporary applications expire after 24 hours; the hourly scheduler deletes them and their uploaded documents. Codes expire after five minutes, have a five-attempt limit, and resending has a one-minute cooldown. Someone who leaves may register again without an abandoned application reserving their email or phone. Existing older unverified users are moved into temporary applications if they try to resume through login; already verified accounts are retained.

Center-created staff invitations retain the existing administrator provisioning workflow: they remain unavailable until email-link activation. Their premature account-created email was removed; confirmation is sent after activation.

## Ecommerce account states

- **Active / Restored:** normal access, subject to existing email and application approval rules.
- **Suspended:** login opens `/appeal`. Buying and selling remain blocked by the backend. Only suspended buyers/sellers can access this page. One pending appeal is allowed at a time.
- **Banned:** permanent. Login and appeals are blocked. The status endpoint refuses later changes, including restore.
- **Archived:** unavailable to the account holder and hidden from the default Users table. Select Account state > Archive to view archived accounts.

Admin > Users now combines name search, Account type and Account state. Admin > Appeals shows submissions. Approve restores a suspended account; Reject retains suspension. Changing an account state closes obsolete pending appeals. A banned account cannot be restored through an old appeal.

The buyer API returns `account_status`, `appeal_required`, and `appeal_url` at login. A suspended buyer's token permits only `/api/appeal` and logout. `GET /api/appeal` returns appeal history and the suspension reason; `POST /api/appeal` accepts `message` (20–3000 characters). Other buyer operations stay blocked. A separate Flutter project was not included; its screen must consume these endpoints if you use that app.

## Logistics changes

Registration forms have a responsive two-column layout with a single column on small screens. Rider and driver public registration links remain available. The shared address script now inserts a missing Region field into the visible form; previously it existed only in memory, leaving Province empty on compact forms. Delivery areas now include Region explicitly and start with the sorting center's registered province and municipality. Existing backend municipality and rider ownership checks remain enforced.

## Verification and testing

Completed here: JavaScript syntax checks; execution of the actual address script in a DOM test harness with Laguna/Santa Cruz and NCR/Metro Manila/Quezon City; lexical checks of changed PHP strings, comments and delimiters; archive integrity checks.

PHP, a Laravel runtime and a browser executable were unavailable in this environment. PHP lint, SQL migrations, Laravel integration tests, real browser layout and real Gmail delivery were NOT executed. The included regression scripts must be run on your local PHP environment; the normal SMTP settings are still required for actual email delivery.

Ecommerce checks (SQLite extension required):

```powershell
php tests/registration_checks.php
php tests/account_state_checks.php
php tests/revision_checks.php
```

Logistics checks:

```powershell
php tests/public_courier_checks.php
```

Manual checks: register and leave before verification (no users row); verify a new account (one user and one success email); suspend buyer/seller (only appeal access); approve an appeal (access restored); permanently ban a user (no login, appeal or restore); archive a user (visible only in Archive state filter); combine Archive with Buyer/Seller; create a delivery area using the prefilled center province and municipality.

## Main code locations

Both projects: `app/Services/PendingRegistration.php`, `app/Services/Registration.php`, new pending migration, `routes/console.php`, `public/js/usability.js`.

Ecommerce: `VerificationController`, `BuyerVerificationController`, `BuyerApiController`, `ApiBuyerAuth`, `AppealController`, `AccountController`, `AdminController`, `CommonController`, `Role`, `Commerce`, `DraftController`, appeal views and `admin_users.blade.php`, web/API routes and account appeals migration.

Logistics: `CompanyRegistrationController`, `PublicCourierController`, `CommonController`, `OperationsController`, `LogisticsController`, `guest-tools.blade.php`, `center_areas.blade.php`.
