<?php
// Isolated regression checks: no connection to your configured MySQL database.
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Middleware\LoginThrottle;
use App\Services\{AuditData, SecurityConfig, StorageSync, Support};
use Illuminate\Cache\{ArrayStore, RateLimiter as Limiter, Repository};
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Artisan, DB, File, RateLimiter, Schema};
use Symfony\Component\HttpFoundation\Response;

$checks = 0;
function checkFix(bool $passed, string $label): void {
    global $checks;
    if (!$passed) throw new RuntimeException('FAIL: '.$label);
    $checks++;
}
function loginRequest(string $email, string $ip = '192.0.2.1'): Request {
    return Request::create('/login', 'POST', ['email' => $email], [], [], ['REMOTE_ADDR' => $ip]);
}
function assertLimited(callable $action): void {
    try { $action(); } catch (ThrottleRequestsException $error) {
        checkFix($error->getStatusCode() === 429 && (int) $error->getHeaders()['Retry-After'] > 0, 'Login returns 429 and a retry delay');
        return;
    }
    throw new RuntimeException('FAIL: excessive login requests were accepted');
}

// Isolate the cache before exercising real middleware behavior.
RateLimiter::swap(new Limiter(new Repository(new ArrayStore())));
$middleware = new LoginThrottle();
$next = fn () => new Response('OK');
for ($i = 0; $i < 5; $i++) $middleware->handle(loginRequest(' Buyer@Example.test '), $next);
assertLimited(fn () => $middleware->handle(loginRequest('buyer@example.test'), $next));
checkFix($middleware->handle(loginRequest('buyer@example.test', '192.0.2.2'), $next)->getStatusCode() === 200, 'An unrelated IP keeps its own account budget');
RateLimiter::swap(new Limiter(new Repository(new ArrayStore())));
for ($i = 0; $i < 30; $i++) $middleware->handle(loginRequest('buyer'.$i.'@example.test'), $next);
assertLimited(fn () => $middleware->handle(loginRequest('new-account@example.test'), $next));
$route = $app['router']->getRoutes()->match(loginRequest('route@example.test'));
checkFix(in_array('login.throttle', $route->gatherMiddleware(), true), 'The real web login route installs the limiter');

$original = (object) ['name' => 'Keep this name', 'password' => 'private-hash', 'remember_token' => 'private-token',
    'nested' => ['Authorization' => 'private-auth', 'api_token' => 'private-api', 'delivery_otp_hash' => 'private-otp', 'status' => 'active']];
$clean = AuditData::redact($original);
checkFix($clean['name'] === 'Keep this name' && $clean['nested']['status'] === 'active', 'Audit business fields are retained');
checkFix($clean['password'] === '[REDACTED]' && $clean['remember_token'] === '[REDACTED]'
    && $clean['nested']['Authorization'] === '[REDACTED]' && $clean['nested']['api_token'] === '[REDACTED]'
    && $clean['nested']['delivery_otp_hash'] === '[REDACTED]', 'Audit credentials are filtered recursively from query-builder objects');
checkFix($original->password === 'private-hash', 'Redaction does not mutate the original user object');

$temporary = sys_get_temp_dir().'/lokashop-security-'.bin2hex(random_bytes(6));
File::ensureDirectoryExists($temporary);
$oldStorage = $app->storagePath();
$oldPublic = $app->publicPath();
try {
    $env = $temporary.'/.env';
    file_put_contents($env, "APP_ENV=local\r\nAPP_DEBUG=true\r\nAPP_KEY=keep-existing-key\r\nDB_PASSWORD=keep-existing-db\r\nMAIL_PASSWORD=keep-existing-mail\r\n");
    SecurityConfig::apply($env);
    $contents = file_get_contents($env);
    checkFix(str_contains($contents, 'APP_DEBUG=false') && str_contains($contents, 'APP_ENV=local'), 'Local setup disables debug without switching environments');
    checkFix(str_contains($contents, 'APP_KEY=keep-existing-key') && str_contains($contents, 'DB_PASSWORD=keep-existing-db')
        && str_contains($contents, 'MAIL_PASSWORD=keep-existing-mail'), 'Existing keys and credentials survive configuration updates');
    SecurityConfig::apply($env, true, 'https://example.test');
    $contents = file_get_contents($env);
    checkFix(str_contains($contents, 'APP_ENV=production') && str_contains($contents, 'SESSION_SECURE_COOKIE=true')
        && str_contains($contents, 'APP_URL=https://example.test'), 'HTTPS production setup enables secure cookies and the intended URL');
    try { SecurityConfig::apply($env, true, 'http://example.test'); throw new LogicException('Unsafe URL accepted'); }
    catch (RuntimeException $error) { checkFix(file_get_contents($env) === $contents, 'Unsafe production URLs are rejected without changing the file'); }

    $app->useStoragePath($temporary.'/legacy/storage');
    $app->usePublicPath($temporary.'/legacy/public');
    config(['filesystems.disks.local.root' => $temporary.'/shared/private', 'filesystems.disks.public.root' => $temporary.'/shared/public']);
    foreach (['storage/app/private/documents', 'storage/app/public/uploads', 'public/storage/profiles'] as $directory) {
        File::ensureDirectoryExists($temporary.'/legacy/'.$directory);
    }
    file_put_contents(storage_path('app/private/documents/example.png'), 'private fixture');
    file_put_contents(storage_path('app/public/uploads/example.png'), 'public fixture');
    file_put_contents(public_path('storage/profiles/example.png'), 'legacy public fixture');
    checkFix(StorageSync::run() === 3, 'Both storage/app directories and legacy public/storage are restored');
    checkFix(file_get_contents($temporary.'/shared/private/documents/example.png') === 'private fixture'
        && file_get_contents($temporary.'/shared/public/profiles/example.png') === 'legacy public fixture', 'Restored upload contents match their originals');
    checkFix(StorageSync::run() === 0, 'Storage restoration is idempotent');
    file_put_contents(storage_path('app/private/documents/new.png'), 'new fixture');
    file_put_contents($temporary.'/shared/public/profiles/example.png', 'existing conflicting file');
    try { StorageSync::run(); throw new LogicException('Storage conflict accepted'); }
    catch (RuntimeException $error) {
        checkFix(!file_exists($temporary.'/shared/private/documents/new.png')
            && file_get_contents($temporary.'/shared/public/profiles/example.png') === 'existing conflicting file', 'Conflicts are detected before copying and shared data is not overwritten');
    }
    checkFix(file_exists(public_path('storage/profiles/example.png')), 'Legacy originals survive restoration');
} finally {
    $app->useStoragePath($oldStorage);
    $app->usePublicPath($oldPublic);
    File::deleteDirectory($temporary);
}

// Only an in-memory SQLite database is used for audit persistence tests.
config(['database.default' => 'sqlite', 'database.connections.sqlite' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'session.driver' => 'array']);
DB::purge();
Schema::create('audit_logs', function ($table) {
    $table->id(); $table->unsignedBigInteger('actor_id')->nullable(); $table->string('action');
    $table->string('entity'); $table->unsignedBigInteger('entity_id'); $table->text('old_value')->nullable();
    $table->text('new_value')->nullable(); $table->text('reason')->nullable(); $table->timestamps();
});
Support::audit('security-test', 'user', 1, $original, ['password' => 'replacement-hash', 'status' => 'active']);
$row = DB::table('audit_logs')->first();
checkFix(!str_contains($row->old_value, 'private-hash') && !str_contains($row->new_value, 'replacement-hash'), 'The real audit writer persists redacted values');
$legacy = DB::table('audit_logs')->insertGetId(['action' => 'legacy', 'entity' => 'user', 'entity_id' => 2,
    'old_value' => json_encode(['password' => 'old-secret', 'name' => 'Retain history']), 'new_value' => null]);
checkFix(Artisan::call('lokashop:redact-audit-secrets') === 0, 'Existing audit cleanup completes');
$row = DB::table('audit_logs')->find($legacy);
checkFix(!str_contains($row->old_value, 'old-secret') && str_contains($row->old_value, 'Retain history'), 'Historical secrets are redacted while history remains');
checkFix(Artisan::call('lokashop:redact-audit-secrets') === 0, 'Historical audit cleanup can be safely repeated');

if (config('app.site_kind') === 'logistics') {
    $user = (object) ['role' => 'rider', 'approval_status' => 'approved', 'active' => true, 'account_status' => 'active',
        'verification_required' => true, 'email_verified_at' => null];
    checkFix(!App\Services\MobileAccountPolicy::allowed($user), 'Approved riders still need required email verification');
    $user->email_verified_at = '2026-11-14 00:00:00';
    checkFix(App\Services\MobileAccountPolicy::allowed($user), 'Verified active riders remain eligible');
    $user->role = 'sorting_center';
    checkFix(App\Services\MobileAccountPolicy::allowed($user), 'Verified sorting centers remain eligible');
    $user->account_status = 'suspended';
    checkFix(!App\Services\MobileAccountPolicy::allowed($user), 'Suspension still blocks mobile access');

    config(['app.key' => 'base64:'.base64_encode(str_repeat('S', 32)), 'hashing.bcrypt.rounds' => 4]);
    Schema::create('users', function ($table) {
        $table->id(); $table->string('name'); $table->string('email'); $table->string('password');
        $table->string('role'); $table->string('approval_status'); $table->boolean('active'); $table->string('account_status');
        $table->boolean('verification_required'); $table->timestamp('email_verified_at')->nullable();
        $table->string('remember_token')->nullable(); $table->timestamp('api_token_expires_at')->nullable();
        $table->boolean('online')->default(false); $table->timestamp('last_seen_at')->nullable(); $table->timestamps();
    });
    $id = DB::table('users')->insertGetId(['name' => 'Verification fixture', 'email' => 'fixture@example.test',
        'password' => password_hash('Fixture-password-2026!', PASSWORD_BCRYPT), 'role' => 'rider', 'approval_status' => 'approved',
        'active' => true, 'account_status' => 'active', 'verification_required' => true]);
    $login = (new App\Http\Controllers\RiderApiController())->login(Request::create('/api/rider/login', 'POST',
        ['email' => 'fixture@example.test', 'password' => 'Fixture-password-2026!']));
    checkFix($login->getStatusCode() === 403, 'The legacy login rejects an approved but unverified rider');
    [$token] = App\Services\ApiToken::issue(App\Models\User::find($id));
    $request = Request::create('/api/rider/me', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer '.$token]);
    $guard = new App\Http\Middleware\ApiRider();
    checkFix($guard->handle($request, $next)->getStatusCode() === 401, 'An already issued token cannot bypass the verification gate');
    DB::table('users')->where('id', $id)->update(['email_verified_at' => now()]);
    checkFix($guard->handle($request, $next)->getStatusCode() === 200, 'Verified riders retain access through the same guard');

    $sessions = sys_get_temp_dir().'/lokashop-revoke-'.bin2hex(random_bytes(6));
    File::ensureDirectoryExists($sessions);
    file_put_contents($sessions.'/'.str_repeat('s', 40), 'session fixture');
    file_put_contents($sessions.'/.gitignore', 'retain placeholder');
    config(['session.driver' => 'file', 'session.files' => $sessions]);
    try {
        $password = DB::table('users')->where('id', $id)->value('password');
        checkFix(Artisan::call('lokashop:revoke-sessions') === 0, 'Session revocation completes against the isolated fixture');
        checkFix(!file_exists($sessions.'/'.str_repeat('s', 40)) && file_exists($sessions.'/.gitignore'), 'Revocation removes session files while retaining placeholders');
        checkFix(App\Services\ApiToken::user($token) === null, 'Revocation invalidates the previously accepted mobile token');
        checkFix(DB::table('users')->count() === 1 && DB::table('users')->where('id', $id)->value('password') === $password, 'Revocation preserves accounts and passwords');
    } finally {
        File::deleteDirectory($sessions);
    }
}

echo 'PASS: '.$checks." security regression checks (isolated cache/files and in-memory SQLite).\n";
