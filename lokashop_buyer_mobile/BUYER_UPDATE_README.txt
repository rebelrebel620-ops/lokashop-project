LOKASHOP BUYER MOBILE - 30 OCTOBER 2026

WHAT THIS PACKAGE CONTAINS
- lokashop_buyer_mobile: the complete cleaned Android Flutter buyer project.
- lokashop-ecommerce: changed Laravel files only. Merge into the existing web
  project; do not delete or replace the entire web project.
- No rider source or APK is included. The rider ZIP failed to upload.

INSTALL THE ECOMMERCE PATCH FIRST
Back up the current projects. Copy the files inside lokashop-ecommerce to the
matching paths in the existing ecommerce project. Overwrite matching files.
The patch includes the latest ecommerce fixes from earlier in this conversation.
Keep your .env, vendor folder, database and shared storage.
Run in the existing ecommerce folder:
    php artisan optimize:clear
Restart the Laravel server. No new database migration is required for this update.
If using the hosted website, deploy these PHP changes there as well.

RUN THE BUYER APP
Use the included Flutter folder as your updated project, or overwrite the
matching files in your existing buyer project after making a backup. Keep your
own android/local.properties and signing configuration. Generated build folders,
Gradle caches, IDE files and machine-specific local.properties are excluded.
The source uses Flutter 3.35+ / Dart 3.9+ APIs (also used by the uploaded design).
From lokashop_buyer_mobile run:
    flutter pub get
    flutter analyze
    flutter test
    flutter run
Stop and rebuild the app after installing this update; hot reload alone does
not reliably rebuild the asset bundle. You do not need to delete your database.
This is source code, not a prebuilt APK.

SERVER CONNECTION
Buyer uses the ecommerce API: https://lokashop.trade/api by default.
There is no server-selection button. Do not point this app at logistics.
For local development you can build with your PC's LAN IP, for example:
    flutter run --dart-define=API_BASE_URL=http://192.168.1.10:8000/api
Replace that example IP with your actual PC address. Run Laravel bound to your
LAN interface, keep the phone and PC on the same network, and permit the port
through your firewall. localhost on a physical phone means the phone, not the PC.
Debug Android builds permit local HTTP; release builds should use HTTPS.

FIXES AND DESIGN
- Missing registration address: assets/places.json existed but was not declared
  in pubspec.yaml. It is now bundled. Its content matches ecommerce exactly.
- Address loading now has retry/error handling and blocks submission until
  geographic choices load. Editing an address infers region from province and
  retains recognized city/barangay selections. Parent changes reset children.
- Registration includes name, birthday, phone, email, password/confirmation,
  region, province, city/municipality, barangay, street, postal code and terms.
- Buyer login can resume an unverified pending registration through a new
  ecommerce API branch, without creating an account before verification.
  The API checks the password before returning a verification challenge.
- Clearer sign-in/registration/verification screens, password visibility,
  autofill, loading/error states, keyboard submission and accessible buttons.
- Responsive green-and-white design, visible guest sign-in, twelve-category
  icon grid, subcategory filters and existing search/sort/pagination.
- Product details distinguish store and seller name, show rating summary,
  use the selected variation's image/price, reject unavailable variations,
  and clamp quantity to both product and variant stock.
- Added Buy now using an ecommerce JSON endpoint. Its dedicated cart selection
  is passed to checkout so unrelated cart rows are not checked out. The endpoint
  requires a request key and uses the same stock/variant validation as the web.
- Checkout retains a saved-address dropdown and links to Addresses for changes.
  It uses ecommerce's quote for shipping, discounts and COD totals. Saved-address
  changes clear and refresh the quote. No duplicate inline address-create form.
- Order details show item subtotal, shipping, store discount, optional platform
  and shipping discounts, total, tracking history and private proof photo.
- Delivery OTP instructions removed. Buyer taps Order received after DELIVERED.
  Registration and account-contact verification codes remain.
- Existing buyer wishlist, store following, vouchers, messages/attachments,
  reviews, returns, complaints, profile/photo, password/contact changes,
  notifications and suspension appeals are retained. No seller functions added.
- Profile photo is circular. Buyer cart selections clear between account logins.
- Restored the Android Kotlin plugin required by the project's existing Kotlin
  configuration with android.builtInKotlin=false. Existing plugin versions kept.

VERIFICATION
Checked all Dart sources/tests with a Dart syntax parser, the changed Laravel
files with a PHP syntax parser, asset declarations and referenced files, the
Laguna -> Calamba -> Barandal relationship, and exact equality of the mobile
and ecommerce place datasets. Reviewed API paths against routes/api.php.
Added Flutter regression tests for the bundled address asset, sign-in validation,
registration geographic fields and restoring an address's inferred region.
Extended the Laravel registration tests for pending buyer login recovery.

Flutter/Dart and PHP runtimes are not available in this environment, so flutter
analyze, Flutter widget tests, Android compilation and Laravel runtime tests
could NOT be executed here. No live Gmail delivery or hosted API success is
claimed. Run the commands above and test on your Android device.

MANUAL CHECKS
1. Guest: open app, browse/search all main categories and subcategories.
2. Create buyer: choose every address dropdown, enter street/postal and verify
   the emailed code. Leave verification and log in again to test recovery.
3. Existing buyer: sign in, add/edit/default an address, then reopen its editor.
4. Choose a variation, increase quantity, switch to one with less stock.
5. Add two products to the bag; Buy now one product and verify checkout includes
   only that selected product. Check COD shipping/discount totals.
6. Complete rider delivery in logistics, open the proof photo as buyer, then
   click Order received. No final-delivery OTP should be requested by the web.
7. Exercise saved finds, vouchers, messaging, account security and returns.

RIDER / DRIVER / SORTING CENTER
The supplied loka_rider.zip was unavailable and was not read or changed.
Upload it again to implement/check logistics login and public registration,
remove its server button, configure its logistics endpoint, improve its design,
and expose the authorized web logistics functions for these roles. This buyer
package does not claim to implement those logistics mobile changes.
