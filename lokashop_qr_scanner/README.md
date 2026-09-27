# LokaShop QR Scanner

A tiny, standalone Flutter app with a single job: scan the QR code printed on a
LokaShop shipping label and show the order details. No login, no registration,
no account of any kind — opening the app puts you straight on the camera.

## What's in this folder

Only the Flutter source, not a full generated project (the platform folders
like `android/` and `ios/` are large boilerplate that `flutter create` writes
for you). Set it up like this:

1. Make sure the [Flutter SDK](https://docs.flutter.dev/get-started/install) is installed.
2. Create a fresh project scaffold, then drop these files into it:
   ```bash
   flutter create lokashop_qr_scanner
   cd lokashop_qr_scanner
   ```
3. Replace the generated `pubspec.yaml` and `lib/main.dart` with the two files
   from this folder.
4. Add camera permissions (see below), then:
   ```bash
   flutter pub get
   flutter run
   ```

## Camera permission

**Android** — open `android/app/src/main/AndroidManifest.xml` and add this line
inside the `<manifest>` tag, above `<application>`:

```xml
<uses-permission android:name="android.permission.CAMERA" />
```

**iOS** — open `ios/Runner/Info.plist` and add this inside the outermost
`<dict>`:

```xml
<key>NSCameraUsageDescription</key>
<string>Camera access is needed to scan order QR codes.</string>
```

Nothing else needs to change — `mobile_scanner` handles asking the user for
the camera permission itself at runtime.

## How it works

- **Scanner screen** (home screen): opens the camera and looks for a QR code.
- Once a code is found, it checks that it's a real `http(s)` link, then opens
  it in an in-app **WebView** — this reuses the exact order-detail page
  already built on the LokaShop site at `/track/{tracking_code}`, so you get
  the buyer's name, phone, address, itemized products, and the total they
  paid, styled the same as on the web.
- Tapping the scan icon in the top bar (or the back button) returns to the
  scanner so you can scan the next label.

## Pointing it at your site

The scanned QR code already encodes the full URL (e.g.
`https://yourdomain.com/track/ABC123`), so there's nothing to configure in the
app itself — whatever URL is in the QR code is what gets opened. Just make
sure the LokaShop web app is reachable from wherever this app runs (same
network, or a public domain, not `localhost` on a different device).
