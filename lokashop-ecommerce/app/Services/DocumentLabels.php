<?php
namespace App\Services;
class DocumentLabels { public static function label(string $kind):string{return ['id_document'=>'Government-issued identification','dti_document'=>'DTI business registration','sec_document'=>'SEC registration certificate','bir_document'=>'BIR registration / tax document','permit_document'=>'Driver license / vehicle permit'][$kind]??ucwords(str_replace('_',' ',$kind));} }
