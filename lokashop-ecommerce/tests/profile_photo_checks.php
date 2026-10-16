<?php
require __DIR__.'/workflow_checks.php';
use App\Services\Cart;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
foreach(['buyer','seller','rider','sorting_center'] as $role){
 $uid=user($role,'photo'.str_replace('_','',$role));DB::table('users')->where('id',$uid)->update(['phone'=>'09170001111']);loginAs($uid);
 $temp=tempnam(sys_get_temp_dir(),'profile-photo');file_put_contents($temp,$png);
 Cart::profile(requestFor(['name'=>'Photo Tester','phone'=>'09170001111'],['profile_photo'=>new UploadedFile($temp,'photo.png','image/png',null,true)]));
 $path=DB::table('users')->where('id',$uid)->value('profile_photo');ok($path&&Storage::disk('public')->exists($path),$role.' profile photo stored');
}
$uid=user('admin','photoadmin');DB::table('users')->where('id',$uid)->update(['phone'=>'09170001111']);loginAs($uid);
$temp=tempnam(sys_get_temp_dir(),'admin-photo');file_put_contents($temp,$png);
try{Cart::profile(requestFor(['name'=>'Admin Tester','phone'=>'09170001111'],['profile_photo'=>new UploadedFile($temp,'photo.png','image/png',null,true)]));throw new RuntimeException('Admin photo accepted');}catch(Symfony\Component\HttpKernel\Exception\HttpException $ex){ok($ex->getStatusCode()===403,'Admin photo upload rejected');}
echo "Profile photo checks passed.\n";
