<?php
namespace App\Http\Controllers;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\{DB,Storage};
use App\Services\Commerce;

class ProductVideoController extends Controller
{
    public function show(int $id)
    {
        $product=DB::table('products')->where('id',$id)->whereNull('deleted_at')->first();
        abort_unless($product,404);
        $viewer=auth()->user();
        $preview=$viewer&&($viewer->role==='admin'||($viewer->role==='seller'&&$viewer->id==$product->seller_id));
        abort_unless($preview||Commerce::catalog(DB::table('products'))->where('products.id',$id)->exists(),404);
        $path=$product->video_path??'';
        abort_unless(preg_match('~^product-videos/[A-Za-z0-9_-]+\.(mp4|webm)$~i',$path),404);
        $disk=Storage::disk('public');
        $file=$disk->exists($path)?$disk->path($path):public_path('storage/'.$path);
        abort_unless(is_file($file)&&is_readable($file),404);
        $type=strtolower(pathinfo($path,PATHINFO_EXTENSION))==='webm'?'video/webm':'video/mp4';
        // Symfony's BinaryFileResponse handles HEAD, Content-Length and byte-range (206) requests.
        return response()->file($file,['Content-Type'=>$type,'Accept-Ranges'=>'bytes',
            'X-Content-Type-Options'=>'nosniff','Cache-Control'=>'private, max-age=0, must-revalidate']);
    }
}
