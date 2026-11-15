<?php
require __DIR__.'/approval_workspace_checks.php';
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\{CourierSetupController,LogisticsAdminController};
$before=$n;$setup=new CourierSetupController();$review=new LogisticsAdminController();
foreach(['rider','driver'] as $type){
 $pending=applicationAccount('Hidden Pending '.$type,'rider','pending',$company,$type);$rejected=applicationAccount('Hidden Rejected '.$type,'rider','rejected',$company,$type);$activation=applicationAccount('Hidden Activation '.$type,'rider','pending_activation',$company,$type);$unverified=applicationAccount('Hidden Unverified '.$type,'rider','unverified',$company,$type);$accepted=applicationAccount('Visible Approved '.$type,'rider','approved',$company,$type);
 loginAs($company);$view=$setup->team(requestFor(['type'=>$type]));$data=$view->getData();$ids=$data['members']->pluck('id');foreach([$pending,$rejected,$activation,$unverified] as $id)ok(!$ids->contains($id),'Unapproved '.$type.' stays out of Courier list: '.$id);ok($ids->contains($accepted),'Approved '.$type.' appears in Courier list');$key=$type==='rider'?'riderCount':'driverCount';$expected=DB::table('users')->where('company_id',$company)->where('role','rider')->where('courier_type',$type)->where('approval_status','approved')->count();ok($data[$key]===$expected,'Courier tab count includes approved '.$type.' accounts only');
 $view=$setup->team(requestFor(['type'=>$type,'q'=>'Hidden Pending']));ok($view->getData()['members']->isEmpty(),'Search cannot reveal unreviewed '.$type);requestFor(['status'=>'pending']);$html=$review->approvals()->render();ok(str_contains($html,'Hidden Pending '.$type)&&str_contains($html,'decide-application-'.$pending),'Pending '.$type.' remains reviewable in Applications');ok(str_contains($html,'Hidden Activation '.$type)&&str_contains($html,'/ops/rider/resend'),'Activation invitation stays accessible in Applications');ok(!str_contains($html,'id="decide-application-'.$activation.'"'),'Unactivated '.$type.' cannot be reviewed before activation');
 $review->approve(requestFor(['decision'=>'approved']),$pending);ok($setup->team(requestFor(['type'=>$type]))->getData()['members']->pluck('id')->contains($pending),'Accepted '.$type.' joins Courier list after review');
}
echo 'PASS: '.($n-$before)." approved-only courier list checks.\n";
