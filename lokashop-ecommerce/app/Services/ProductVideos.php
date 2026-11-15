<?php
namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Storage,Log};
use Symfony\Component\Process\Process;

/** Compress product demonstrations only; retain private return evidence in original quality. */
class ProductVideos
{
    public static function arguments(string $input, string $output): array
    {
        return [(string)config('product_video.ffmpeg_binary','ffmpeg'), '-nostdin', '-hide_banner', '-loglevel', 'error', '-y',
            '-i', $input, '-map', '0:v:0', '-map', '0:a:0?', '-sn', '-dn',
            '-vf', "scale=w='min(640,iw)':h='min(480,ih)':force_original_aspect_ratio=decrease:force_divisible_by=2,setsar=1",
            '-c:v', 'libx264', '-preset', 'veryfast', '-crf', '28', '-maxrate', '800k', '-bufsize', '1600k',
            '-pix_fmt', 'yuv420p', '-c:a', 'aac', '-b:a', '64k', '-ac', '2', '-map_metadata', '-1',
            '-movflags', '+faststart', $output];
    }

    public static function store(UploadedFile $file): string
    {
        $path=$file->store('product-videos','public');
        return self::optimize($path);
    }

    public static function optimize(string $path): string
    {
        // This method accepts only generated product-video paths, never arbitrary disk input.
        if(!preg_match('~^product-videos/[A-Za-z0-9_-]+\.(mp4|webm)$~i',$path))return $path;
        if(!config('product_video.compress',true)||str_ends_with($path,'-480p.mp4'))return $path;
        $disk=Storage::disk('public');
        if(!$disk->exists($path))return $path;
        $output='product-videos/'.bin2hex(random_bytes(20)).'-480p.mp4';
        $keepOutput=false;
        try {
            if(function_exists('set_time_limit'))@set_time_limit(180);
            $process=new Process(self::arguments($disk->path($path),$disk->path($output)));
            $process->setTimeout(120);
            $process->run();
            if(!$process->isSuccessful()||!$disk->exists($output)||$disk->size($output)<1){
                Log::warning('Product video compression unavailable; original retained. Check the FFmpeg executable and H.264 encoder.');
                return $path;
            }
            // Already-small originals can be lighter than a re-encode. Never make a video heavier.
            if($disk->size($output)>=$disk->size($path))return $path;
            // Keep the source file: existing duplicate listings may still reference it.
            $keepOutput=true;
            return $output;
        } catch(\Throwable $e) {
            Log::warning('Product video compression failed; original retained.',['error_type'=>get_class($e)]);
            return $path;
        } finally {
            if(!$keepOutput)$disk->delete($output);
        }
    }
}
