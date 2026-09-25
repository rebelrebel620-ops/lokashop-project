<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Parcel extends Model { protected $table='parcels'; protected $guarded=[]; public function order(){return $this->belongsTo(Order::class);} public function history(){return $this->hasMany(StatusHistory::class);} }
