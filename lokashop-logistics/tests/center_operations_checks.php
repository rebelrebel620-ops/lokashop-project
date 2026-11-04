<?php
// Run: php tests/center_operations_checks.php. Uses disposable SQLite, never your MySQL data.
require __DIR__.'/workflow_checks.php';
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\{CenterManagementController,CommonController};
$before=$n;
foreach(['2026_10_24_000001_bulk_driver_trips.php','2026_10_25_000001_transport_handover_confirmation.php'] as $file){$migration=require __DIR__.'/../database/migrations/'.$file;$migration->up();}
config(['app.site_kind'=>'logistics']);
function centerDenied($fn,$label){try{$fn();throw new RuntimeException('Unexpected success: '.$label);}catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){ok(in_array($e->getStatusCode(),[403,404]),$label);}}
function centerInvalid($fn,$label){try{$fn();throw new RuntimeException('Unexpected success: '.$label);}catch(ValidationException $e){ok(true,$label);}}
$manager=new CenterManagementController();$local=user('rider','centerops-local');$foreign=user('rider','centerops-foreign');$otherCenter=user('sorting_center','centerops-other');
foreach([$local,$foreign] as $id){DB::table('users')->where('id',$id)->update(['company_id'=>$id===$local?$company:$otherCenter]);DB::table('addresses')->insert(['user_id'=>$id,'recipient'=>'Courier','phone'=>'09170000001','street'=>'Test','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','is_default'=>1]);}
loginAs($company);$links=app(CommonController::class)->links();ok(!isset($links['Setup'])&&!isset($links['History'])&&isset($links['Appeal'],$links['Courier'],$links['Area']),'Direct operations navigation replaces Setup and History');
centerDenied(fn()=>$manager->show($foreign),'Cannot view another sorting center courier, even in same municipality');
centerDenied(fn()=>$manager->status(requestFor(['id'=>$foreign,'active'=>0,'reason'=>'Out of scope suspension attempt'])),'Cannot suspend another center courier');
$area=DB::table('delivery_areas')->insertGetId(['center_id'=>$center,'name'=>'Regression coverage','barangay'=>'Commonwealth','city'=>'Quezon City','province'=>'Metro Manila','rider_id'=>$rider]);
$manager->area(requestFor(['action'=>'assign','rider_id'=>$local]),$area);ok(DB::table('delivery_areas')->where('id',$area)->value('rider_id')===$local,'Area rider can be changed');
centerDenied(fn()=>$manager->area(requestFor(['action'=>'assign','rider_id'=>$foreign]),$area),'Area cannot assign another center courier');
$manager->area(requestFor(['action'=>'delete']),$area);ok(!DB::table('delivery_areas')->where('id',$area)->exists(),'Unused area can be deleted');
$manager->status(requestFor(['id'=>$local,'active'=>0,'reason'=>'Suspended for center operations review']));ok(DB::table('users')->where('id',$local)->value('account_status')==='suspended','Suspension updates account status');
loginAs($local);$manager->submitAppeal(requestFor(['message'=>'Please review this suspension and restore my courier access.']));centerInvalid(fn()=>$manager->submitAppeal(requestFor(['message'=>'This is another appeal while the previous one is still pending.'])),'Duplicate pending appeals are blocked');
$appeal=DB::table('courier_appeals')->where('user_id',$local)->value('id');loginAs($company);$manager->review(requestFor(['decision'=>'approved','reason'=>'Reviewed and approved by the registered center']),$appeal);ok(DB::table('users')->where('id',$local)->value('account_status')==='active','Own municipality appeal restores courier');ok(DB::table('courier_appeals')->where('id',$appeal)->value('status')==='approved','Appeal review is recorded');
DB::table('users')->where('id',$local)->update(['account_status'=>'banned','active'=>0]);centerInvalid(fn()=>$manager->status(requestFor(['id'=>$local,'active'=>1,'reason'=>'Attempt to restore a banned courier account'])),'Center cannot restore banned courier');
DB::table('users')->where('id',$local)->update(['account_status'=>'active','active'=>1]);DB::table('addresses')->where('user_id',$local)->update(['city'=>'Calamba','province'=>'Laguna']);centerDenied(fn()=>$manager->status(requestFor(['id'=>$local,'active'=>0,'reason'=>'Courier has a different municipality'])),'Municipality boundary enforced for owned courier');
// Compile and lint changed templates, including tab expressions and rate JSON.
foreach(['process_overview','courier_overview','center_courier','center_appeals','courier_appeal_form','center_areas','center_team','center_pickups','center_deliveries','transfers','tools','operation_tabs','dashboard'] as $name){$compiled=app('blade.compiler')->compileString(file_get_contents(__DIR__.'/../resources/views/'.$name.'.blade.php'));$temp=tempnam(sys_get_temp_dir(),'center-blade-');file_put_contents($temp,$compiled);$output=[];$exit=0;exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1',$output,$exit);unlink($temp);ok($exit===0,$name.' Blade compiles to valid PHP: '.implode(' ',$output));}
echo 'PASS: '.($n-$before)." center operations regression checks.\n";
