<?php
namespace App\Services;

use Illuminate\Support\Facades\Storage;

/** Non-destructive, bounded-size product image derivatives on the local public disk. */
class ProductImages
{
    public const SIZES = [400, 1200];

    public static function available(): bool
    {
        return function_exists('imagecreatefromjpeg') && function_exists('imagecreatefrompng')
            && function_exists('imagejpeg') && function_exists('imagecreatetruecolor');
    }

    public static function source(string $path): ?string
    {
        if (!preg_match('~^(uploads|stores|profiles|variants)/[A-Za-z0-9_./-]+\.(jpg|jpeg|png)$~i', $path)
            || str_contains($path, '..')) return null;
        $disk = Storage::disk('public');
        $file = $disk->exists($path) ? $disk->path($path) : public_path('storage/'.$path);
        return is_file($file) ? $file : null;
    }

    public static function warm(string $path): void
    {
        $source = self::source($path);
        if ($source) foreach (self::SIZES as $size) self::optimized($source, $size);
    }

    public static function optimized(string $source, int $size): string
    {
        if (!in_array($size, self::SIZES, true) || !self::available()) return $source;
        $info = @getimagesize($source);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) return $source;
        [$width, $height] = $info;
        // Bound decoded memory. Large or unsupported files still display as their original.
        $limit = ini_get('memory_limit');
        $unit = strtolower(substr($limit, -1));
        $memory = (int)$limit * match ($unit) {'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1};
        $estimate = $width * $height * 8 + min($size, $width) * min($size, $height) * 8 + 16777216;
        if ($width < 1 || $height < 1 || $width * $height > 24000000
            || ($memory > 0 && memory_get_usage(true) + $estimate > $memory)) return $source;
        $webp = function_exists('imagewebp');
        $key = hash('sha256', 'v1|'.$source.'|'.filemtime($source).'|'.filesize($source).'|'.$size.'|'.(int)$webp);
        $directory = storage_path('app/product-image-cache');
        $target = $directory.'/'.$key.($webp ? '.webp' : '.jpg');
        if (is_file($target) && filesize($target) > 0) return $target;
        if (is_file($target.'.original')) return $source;
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) return $source;
        $lock = @fopen($target.'.lock', 'c');
        if (!$lock) return $source;
        $image = $canvas = null;
        $temporary = null;
        try {
            if (!flock($lock, LOCK_EX)) return $source;
            if (is_file($target) && filesize($target) > 0) return $target;
            $image = $info[2] === IMAGETYPE_JPEG ? @imagecreatefromjpeg($source) : @imagecreatefrompng($source);
            if (!$image) return $source;
            // Phone JPEGs commonly store rotation in EXIF instead of rotating their pixels.
            if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
                $exif = @exif_read_data($source);
                $orientation = (int)($exif['Orientation'] ?? 1);
                if (in_array($orientation, [2, 4, 5, 7], true)) imageflip($image, IMG_FLIP_HORIZONTAL);
                $angle = match ($orientation) {3, 4 => 180, 5, 6 => -90, 7, 8 => 90, default => 0};
                if ($angle) {
                    $rotated = imagerotate($image, $angle, 0);
                    if ($rotated) { imagedestroy($image); $image = $rotated; }
                }
            }
            $width = imagesx($image); $height = imagesy($image);
            $ratio = min(1, $size / max($width, $height));
            $w = max(1, (int)round($width * $ratio)); $h = max(1, (int)round($height * $ratio));
            $canvas = imagecreatetruecolor($w, $h);
            if ($webp) {
                imagealphablending($canvas, false); imagesavealpha($canvas, true);
                imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
            } else imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $w, $h, $width, $height);
            $temporary = tempnam($directory, 'image-');
            if (!$temporary) return $source;
            $ok = $webp ? imagewebp($canvas, $temporary, 76) : imagejpeg($canvas, $temporary, 78);
            // Never replace a small original with a heavier derivative.
            if (!$ok || filesize($temporary) === 0) return $source;
            if (filesize($temporary) >= filesize($source)) { @touch($target.'.original'); return $source; }
            return rename($temporary, $target) ? $target : $source;
        } catch (\Throwable $error) {
            return $source;
        } finally {
            if ($image) imagedestroy($image);
            if ($canvas) imagedestroy($canvas);
            if ($temporary && is_file($temporary)) @unlink($temporary);
            flock($lock, LOCK_UN); fclose($lock);
        }
    }
}
