<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

/** Restore legacy uploads without moving originals or overwriting shared files. */
class StorageSync
{
    public static function run(): int
    {
        $copies = [];
        foreach (['local' => [storage_path('app/private')],
                  'public' => [storage_path('app/public'), public_path('storage')]] as $disk => $sources) {
            $target = config('filesystems.disks.'.$disk.'.root');
            if (!is_string($target) || $target === '') {
                throw new RuntimeException('Configure the '.$disk.' storage root first.');
            }
            File::ensureDirectoryExists($target);
            $target = realpath($target);
            if ($target === false) {
                throw new RuntimeException('Cannot resolve the '.$disk.' storage directory.');
            }
            foreach ($sources as $source) {
                $source = realpath($source);
                if ($source === false || !is_dir($source) || $source === $target) {
                    continue;
                }
                $prefix = rtrim($source, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
                if (str_starts_with($target.DIRECTORY_SEPARATOR, $prefix)) {
                    throw new RuntimeException('Shared storage must not be nested inside a legacy upload directory.');
                }
                foreach (File::allFiles($source) as $file) {
                    if ($file->getFilename() === '.gitignore') {
                        continue;
                    }
                    if ($file->isLink()) {
                        throw new RuntimeException('Resolve linked upload files before restoring storage.');
                    }
                    $destination = $target.DIRECTORY_SEPARATOR.$file->getRelativePathname();
                    $hash = hash_file('sha256', $file->getPathname());
                    if ($hash === false) {
                        throw new RuntimeException('Cannot read a legacy upload.');
                    }
                    if (is_link($destination) || (file_exists($destination)
                        && (!is_file($destination) || hash_file('sha256', $destination) !== $hash))) {
                        throw new RuntimeException('Storage conflict: '.$file->getRelativePathname().'. No conflicting file was overwritten.');
                    }
                    if (isset($copies[$destination]) && $copies[$destination]['hash'] !== $hash) {
                        throw new RuntimeException('Conflicting legacy copies: '.$file->getRelativePathname());
                    }
                    if (!file_exists($destination)) {
                        $copies[$destination] = ['source' => $file->getPathname(), 'hash' => $hash];
                    }
                }
            }
        }

        // Check every legacy source for conflicts before beginning the copy phase.
        $count = 0;
        foreach ($copies as $destination => $copy) {
            File::ensureDirectoryExists(dirname($destination));
            $input = @fopen($copy['source'], 'rb');
            if ($input === false) {
                throw new RuntimeException('Cannot read a legacy upload.');
            }
            $output = @fopen($destination, 'xb');
            if ($output === false) {
                fclose($input);
                // Another sync may have finished the same copy after preflight.
                if (is_file($destination) && !is_link($destination) && hash_file('sha256', $destination) === $copy['hash']) {
                    continue;
                }
                throw new RuntimeException('Cannot safely create an upload file. Check permissions or concurrent uploads.');
            }
            $completed = false;
            try {
                $bytes = stream_copy_to_stream($input, $output);
                $flushed = fflush($output);
                fclose($output);
                $output = null;
                if ($bytes === false || !$flushed || hash_file('sha256', $destination) !== $copy['hash']) {
                    throw new RuntimeException('Upload verification failed; the incomplete copy was removed.');
                }
                $completed = true;
                $count++;
            } finally {
                fclose($input);
                if (is_resource($output)) {
                    fclose($output);
                }
                if (!$completed) {
                    @unlink($destination);
                }
            }
        }

        return $count;
    }
}
