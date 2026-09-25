<?php
namespace App\Models;
use Illuminate\Foundation\Auth\User as Authenticatable;
class User extends Authenticatable {
 protected $guarded=[]; protected $hidden=['password','remember_token'];
 protected function casts(): array { return ['active'=>'boolean','password'=>'hashed']; }
 public function orders(){return $this->hasMany(Order::class,'buyer_id');}
}
