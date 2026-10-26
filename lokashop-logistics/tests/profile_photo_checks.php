<?php
require __DIR__.'/workflow_checks.php';
use App\Services\Cart;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB,Storage};
$png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
foreach(['buyer','seller','rider','sorting_center','admin','logistics_admin'] as $role){
 $uid=user($role,'photo'.str_replace('_','',$role));DB::table('users')->where('id',$uid)->update(['phone'=>'09170001111']);loginAs($uid);
 $temp=tempnam(sys_get_temp_dir(),'profile-photo');file_put_contents($temp,$png);
 Cart::profile(requestFor(['name'=>'Photo Tester','phone'=>'09170001111'],['profile_photo'=>new UploadedFile($temp,'photo.png','image/png',null,true)]));
 $path=DB::table('users')->where('id',$uid)->value('profile_photo');ok($path&&Storage::disk('public')->exists($path),$role.' profile photo stored');
}
$uid=user('logistics_admin','photoemptyphone');loginAs($uid);$temp=tempnam(sys_get_temp_dir(),'empty-phone-photo');file_put_contents($temp,$png);Cart::profile(requestFor(['name'=>'Photo Admin'],['profile_photo'=>new UploadedFile($temp,'photo.png','image/png',null,true)]));ok(DB::table('users')->where('id',$uid)->value('profile_photo')!==null,'Admin without registered phone can save own photo');
$uid=DB::table('users')->where('email','photobuyer@example.test')->value('id');loginAs($uid);try{Cart::profile(requestFor(['name'=>'Phone Change','phone'=>'09179999999']));throw new RuntimeException('Unverified phone changed');}catch(\Illuminate\Validation\ValidationException $e){ok(isset($e->errors()['phone']),'Profile photo improvements do not allow unverified phone changes');}
echo "Profile photo checks passed.\n";
