<?php

use App\Services\{AuditData, SecurityConfig};
use Illuminate\Support\Facades\{Artisan, DB, Schema};

Artisan::command('lokashop:secure-config {--production} {--url=}', function () {
    SecurityConfig::apply($this->laravel->environmentFilePath(), (bool) $this->option('production'), $this->option('url'));
    $this->call('config:clear');
    $this->info('Debug disabled. Existing application key, database and mail credentials were preserved.');
    if ($this->option('production')) {
        $this->info('Production URL and secure session cookies configured. Restart persistent workers after setup.');
    }
})->purpose('Apply safe environment settings without replacing existing credentials');

Artisan::command('lokashop:redact-audit-secrets', function () {
    if (!Schema::hasTable('audit_logs')) {
        $this->error('The audit table is missing. Check your database and migrations first.');
        return 1;
    }
    $updated = 0;
    $invalid = 0;
    DB::table('audit_logs')->orderBy('id')->chunkById(100, function ($rows) use (&$updated, &$invalid) {
        foreach ($rows as $row) {
            $changes = [];
            foreach (['old_value', 'new_value'] as $field) {
                if ($row->$field === null) {
                    continue;
                }
                try {
                    $original = json_decode($row->$field, true, 512, JSON_THROW_ON_ERROR);
                    $clean = AuditData::redact($original);
                    if ($clean !== $original) {
                        $changes[$field] = json_encode($clean, JSON_THROW_ON_ERROR);
                    }
                } catch (JsonException $error) {
                    $invalid++;
                }
            }
            if ($changes) {
                DB::table('audit_logs')->where('id', $row->id)->update($changes);
                $updated++;
            }
        }
    });
    $this->info('Audit entries redacted: '.$updated.'. Other audit fields were retained.');
    if ($invalid) {
        $this->warn('Unparseable audit values require private manual review: '.$invalid.'. Their contents were not displayed or modified.');
        return 1;
    }
    return 0;
})->purpose('Redact structured credentials from existing audit records without deleting history');

Artisan::command('lokashop:revoke-sessions', function () {
    if (config('session.driver') !== 'file') {
        $this->error('This command supports file sessions. Revoke your configured session store separately. No tokens were changed.');
        return 1;
    }
    $directory = config('session.files');
    $count = 0;
    if (is_string($directory) && is_dir($directory)) {
        foreach (new DirectoryIterator($directory) as $file) {
            if ($file->isFile() && !$file->isLink() && preg_match('/^[a-zA-Z0-9]{40}$/D', $file->getFilename())) {
                if (!unlink($file->getPathname())) {
                    throw new RuntimeException('Could not revoke a session file. Check file permissions.');
                }
                $count++;
            }
        }
    }
    DB::table('users')->update(['remember_token' => null, 'api_token_expires_at' => null, 'online' => 0]);
    $this->info('Web sessions removed: '.$count.'. Mobile tokens revoked. Passwords and records were retained.');
    return 0;
})->purpose('Revoke exposed file sessions and mobile tokens during a maintenance window');
