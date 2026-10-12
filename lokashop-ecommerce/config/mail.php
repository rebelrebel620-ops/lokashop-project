<?php
return [
    'default'=>env('MAIL_MAILER','log'),
    'mailers'=>[
        'log'=>['transport'=>'log','channel'=>env('MAIL_LOG_CHANNEL')],
        'smtp'=>['transport'=>'smtp','scheme'=>env('MAIL_SCHEME'),'host'=>env('MAIL_HOST','127.0.0.1'),
            'port'=>env('MAIL_PORT',2525),'username'=>env('MAIL_USERNAME'),'password'=>env('MAIL_PASSWORD'),'timeout'=>15],
    ],
    'from'=>['address'=>env('MAIL_FROM_ADDRESS','no-reply@lokashop.trade'),'name'=>env('MAIL_FROM_NAME','LokaShop')],
];
