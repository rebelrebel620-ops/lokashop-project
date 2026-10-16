<?php
return [
 'default'=>'local',
 'disks'=>[
  'local'=>['driver'=>'local','root'=>env('LOKASHOP_PRIVATE_STORAGE',dirname(base_path()).'/lokashop-shared/private'),'throw'=>true],
  'public'=>['driver'=>'local','root'=>env('LOKASHOP_PUBLIC_STORAGE',dirname(base_path()).'/lokashop-shared/public'),'url'=>env('APP_URL').'/storage','visibility'=>'public','throw'=>true],
 ],
 'links'=>[public_path('storage')=>env('LOKASHOP_PUBLIC_STORAGE',dirname(base_path()).'/lokashop-shared/public')],
];
