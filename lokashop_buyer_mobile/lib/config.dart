/// -----------------------------------------------------------------------
/// Point this at the SAME Laravel backend / database as the LokaShop
/// website (the API routes were added to routes/api.php in that project).
///
///   • Android emulator  -> http://10.0.2.2:8000/api        (10.0.2.2 = host machine)
///   • iOS simulator      -> http://127.0.0.1:8000/api
///   • Physical phone     -> http://<your-computer-LAN-IP>:8000/api
///   • Deployed server    -> https://yourdomain.com/api
///
/// Start the Laravel backend with:  php artisan serve --host=0.0.0.0
/// -----------------------------------------------------------------------
const String apiBaseUrl = 'https://lokashop.trade/api';
