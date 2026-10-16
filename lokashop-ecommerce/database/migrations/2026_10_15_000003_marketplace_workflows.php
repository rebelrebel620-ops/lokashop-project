<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  $columns = [
   'users'=>[
    'latitude'=>fn($t)=>$t->decimal('latitude',10,7)->nullable(),
    'longitude'=>fn($t)=>$t->decimal('longitude',10,7)->nullable(),
    'company_id'=>fn($t)=>$t->unsignedBigInteger('company_id')->nullable()->index(),
    'online'=>fn($t)=>$t->boolean('online')->default(false),
    'last_seen_at'=>fn($t)=>$t->dateTime('last_seen_at')->nullable(),
    'api_token_expires_at'=>fn($t)=>$t->dateTime('api_token_expires_at')->nullable(),
    'license_number'=>fn($t)=>$t->string('license_number')->nullable()->unique(),
    'license_expires_at'=>fn($t)=>$t->date('license_expires_at')->nullable(),
    'plate_number'=>fn($t)=>$t->string('plate_number')->nullable()->unique(),
    'rejection_reason'=>fn($t)=>$t->text('rejection_reason')->nullable(),
    'activation_hash'=>fn($t)=>$t->string('activation_hash',64)->nullable(),
    'activation_expires_at'=>fn($t)=>$t->dateTime('activation_expires_at')->nullable(),
    'sec_number'=>fn($t)=>$t->string('sec_number')->nullable()->unique(),
    'service_areas'=>fn($t)=>$t->json('service_areas')->nullable(),
   ],
   'seller_businesses'=>[
    'description'=>fn($t)=>$t->text('description')->nullable(),
    'logo'=>fn($t)=>$t->string('logo')->nullable(), 'banner'=>fn($t)=>$t->string('banner')->nullable(),
    'vacation'=>fn($t)=>$t->boolean('vacation')->default(false),
   ],
   'sorting_centers'=>['name_normalized'=>fn($t)=>$t->string('name_normalized',180)->nullable()->unique()],
   'products'=>[
    'images'=>fn($t)=>$t->json('images')->nullable(), 'weight'=>fn($t)=>$t->decimal('weight',10,3)->default(1),
    'dimensions'=>fn($t)=>$t->json('dimensions')->nullable(), 'draft'=>fn($t)=>$t->boolean('draft')->default(false),
    'deleted_at'=>fn($t)=>$t->dateTime('deleted_at')->nullable(),
    'purchase_limit'=>fn($t)=>$t->unsignedInteger('purchase_limit')->nullable(),
    'low_stock_notified'=>fn($t)=>$t->boolean('low_stock_notified')->default(false),
   ],
   'product_variations'=>[
    'sku'=>fn($t)=>$t->string('sku')->nullable()->unique(), 'options'=>fn($t)=>$t->json('options')->nullable(),
    'image'=>fn($t)=>$t->string('image')->nullable(), 'deleted_at'=>fn($t)=>$t->dateTime('deleted_at')->nullable(),
    'low_stock_notified'=>fn($t)=>$t->boolean('low_stock_notified')->default(false),
   ],
   'vouchers'=>[
    'type'=>fn($t)=>$t->string('type')->default('percent'), 'value'=>fn($t)=>$t->decimal('value',12,2)->nullable(),
    'minimum_spend'=>fn($t)=>$t->decimal('minimum_spend',12,2)->default(0),
    'cap'=>fn($t)=>$t->decimal('cap',12,2)->nullable(), 'starts_at'=>fn($t)=>$t->dateTime('starts_at')->nullable(),
    'usage_limit'=>fn($t)=>$t->unsignedInteger('usage_limit')->nullable(),
    'per_buyer_limit'=>fn($t)=>$t->unsignedInteger('per_buyer_limit')->default(1),
    'used_count'=>fn($t)=>$t->unsignedInteger('used_count')->default(0),
    'claimable'=>fn($t)=>$t->boolean('claimable')->default(true), 'product_ids'=>fn($t)=>$t->json('product_ids')->nullable(),
   ],
   'orders'=>[
    'checkout_key'=>fn($t)=>$t->string('checkout_key',64)->nullable()->index(),
    'merchandise_total'=>fn($t)=>$t->decimal('merchandise_total',12,2)->default(0),
    'shipping_fee'=>fn($t)=>$t->decimal('shipping_fee',12,2)->default(0),
    'store_discount'=>fn($t)=>$t->decimal('store_discount',12,2)->default(0),
    'platform_discount'=>fn($t)=>$t->decimal('platform_discount',12,2)->default(0),
    'shipping_discount'=>fn($t)=>$t->decimal('shipping_discount',12,2)->default(0),
    'address_snapshot'=>fn($t)=>$t->json('address_snapshot')->nullable(),
    'note'=>fn($t)=>$t->string('note',200)->nullable(),
    'delivered_at'=>fn($t)=>$t->dateTime('delivered_at')->nullable(),
    'completed_at'=>fn($t)=>$t->dateTime('completed_at')->nullable(),
    'cancelled_at'=>fn($t)=>$t->dateTime('cancelled_at')->nullable(),
    'stock_restored_at'=>fn($t)=>$t->dateTime('stock_restored_at')->nullable(),
    'refunded_at'=>fn($t)=>$t->dateTime('refunded_at')->nullable(),
   ],
   'order_items'=>['image'=>fn($t)=>$t->string('image')->nullable()],
   'payments'=>['reference'=>fn($t)=>$t->string('reference')->nullable()->unique()],
   'parcels'=>[
    'attempts'=>fn($t)=>$t->unsignedInteger('attempts')->default(0),
    'proof_path'=>fn($t)=>$t->string('proof_path')->nullable(),
    'delivery_otp_hash'=>fn($t)=>$t->string('delivery_otp_hash')->nullable(),
    'otp_attempts'=>fn($t)=>$t->unsignedInteger('otp_attempts')->default(0),
    'otp_locked_until'=>fn($t)=>$t->dateTime('otp_locked_until')->nullable(),
   ],
   'assignments'=>[
    'expires_at'=>fn($t)=>$t->dateTime('expires_at')->nullable(),
    'declines'=>fn($t)=>$t->unsignedInteger('declines')->default(0),
   ],
   'messages'=>['attachment'=>fn($t)=>$t->string('attachment')->nullable()],
   'ratings'=>['edit_count'=>fn($t)=>$t->unsignedInteger('edit_count')->default(0)],
  ];
  foreach($columns as $table=>$fields) foreach($fields as $name=>$add) if(!Schema::hasColumn($table,$name))Schema::table($table,$add);
  $tables=[
   'store_categories'=>function($t){$t->id();$t->unsignedBigInteger('seller_id')->index();$t->unsignedBigInteger('parent_id')->nullable();$t->string('name',50);$t->string('normalized_name',50);$t->boolean('visible')->default(true);$t->integer('position')->default(0);$t->unique(['seller_id','normalized_name']);$t->timestamps();},
   'product_store_categories'=>function($t){$t->unsignedBigInteger('product_id');$t->unsignedBigInteger('category_id');$t->primary(['product_id','category_id']);},
   'bundle_deals'=>function($t){$t->id();$t->unsignedBigInteger('seller_id');$t->string('type');$t->json('product_ids');$t->unsignedInteger('minimum_quantity');$t->decimal('percent',5,2)->default(0);$t->unsignedBigInteger('gift_product_id')->nullable();$t->unsignedInteger('gift_quantity')->default(1);$t->dateTime('starts_at');$t->dateTime('ends_at');$t->boolean('active')->default(true);$t->timestamps();},
   'promotions'=>function($t){$t->id();$t->boolean('reminder_sent')->default(false);$t->unsignedBigInteger('seller_id')->index();$t->unsignedBigInteger('product_id');$t->string('type');$t->decimal('price',12,2);$t->unsignedInteger('quantity')->nullable();$t->unsignedInteger('used')->default(0);$t->dateTime('starts_at');$t->dateTime('ends_at');$t->boolean('active')->default(true);$t->timestamps();},
   'voucher_claims'=>function($t){$t->id();$t->unsignedBigInteger('buyer_id');$t->unsignedBigInteger('voucher_id');$t->unique(['buyer_id','voucher_id']);$t->timestamps();},
   'voucher_redemptions'=>function($t){$t->id();$t->unsignedBigInteger('buyer_id');$t->unsignedBigInteger('voucher_id');$t->string('checkout_key',64);$t->unique(['buyer_id','voucher_id','checkout_key']);$t->timestamps();},
   'order_vouchers'=>function($t){$t->unsignedBigInteger('order_id');$t->unsignedBigInteger('voucher_id');$t->decimal('amount',12,2);$t->primary(['order_id','voucher_id']);},
   'wishlists'=>function($t){$t->id();$t->unsignedBigInteger('buyer_id');$t->unsignedBigInteger('product_id');$t->unique(['buyer_id','product_id']);$t->timestamps();},
   'store_follows'=>function($t){$t->id();$t->unsignedBigInteger('buyer_id');$t->unsignedBigInteger('seller_id');$t->unique(['buyer_id','seller_id']);$t->timestamps();},
   'shipping_rates'=>function($t){$t->id();$t->unsignedBigInteger('center_id');$t->string('origin_province',120)->default('');$t->string('city',120);$t->string('province',120);$t->decimal('base_fee',12,2);$t->decimal('extra_kg_fee',12,2)->default(0);$t->boolean('active')->default(true);$t->unique(['center_id','origin_province','city','province']);$t->timestamps();},
   'return_requests'=>function($t){$t->id();$t->unsignedBigInteger('order_id')->unique();$t->unsignedBigInteger('buyer_id');$t->unsignedBigInteger('seller_id');$t->string('reason');$t->text('description');$t->json('photos');$t->string('status')->default('requested');$t->text('response')->nullable();$t->boolean('escalated')->default(false);$t->dateTime('responded_at')->nullable();$t->timestamps();},
   'refunds'=>function($t){$t->id();$t->unsignedBigInteger('order_id')->unique();$t->decimal('amount',12,2);$t->string('method');$t->string('status')->default('pending');$t->string('reference')->nullable();$t->timestamps();},
   'payouts'=>function($t){$t->id();$t->unsignedBigInteger('seller_id')->index();$t->decimal('amount',12,2);$t->string('account');$t->string('status')->default('pending');$t->timestamps();},
   'cod_collections'=>function($t){$t->id();$t->unsignedBigInteger('parcel_id')->unique();$t->unsignedBigInteger('rider_id');$t->unsignedBigInteger('company_id');$t->decimal('amount',12,2);$t->unsignedBigInteger('remittance_id')->nullable();$t->timestamps();},
   'remittances'=>function($t){$t->id();$t->unsignedBigInteger('rider_id');$t->unsignedBigInteger('company_id');$t->decimal('expected',12,2);$t->decimal('amount',12,2);$t->string('status')->default('pending');$t->unsignedBigInteger('confirmed_by')->nullable();$t->string('reference');$t->timestamps();},
   'item_reviews'=>function($t){$t->id();$t->unsignedBigInteger('order_item_id')->unique();$t->unsignedBigInteger('buyer_id');$t->unsignedBigInteger('seller_id');$t->unsignedInteger('stars');$t->text('comment')->nullable();$t->json('photos')->nullable();$t->unsignedInteger('edit_count')->default(0);$t->text('seller_response')->nullable();$t->timestamps();},
   'audit_logs'=>function($t){$t->id();$t->unsignedBigInteger('actor_id')->nullable();$t->string('action');$t->string('entity');$t->unsignedBigInteger('entity_id');$t->json('old_value')->nullable();$t->json('new_value')->nullable();$t->text('reason')->nullable();$t->timestamps();},
   'request_keys'=>function($t){$t->string('key',64)->primary();$t->unsignedBigInteger('user_id');$t->string('action');$t->json('result')->nullable();$t->timestamps();},
  ];
  foreach($tables as $name=>$build)if(!Schema::hasTable($name))Schema::create($name,$build);
 }
 // Forward-only upgrade: rollback deliberately does not destroy orders or accounting records.
 public function down(): void { throw new RuntimeException('Restore your backup to undo this data-preserving upgrade.'); }
};
