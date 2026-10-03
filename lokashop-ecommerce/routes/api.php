<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\BuyerApiController as B;

// Public
Route::post('/register', [B::class, 'register']);
Route::post('/login', [B::class, 'login']);
Route::get('/categories', [B::class, 'categories']);
Route::get('/products', [B::class, 'products']);
Route::get('/products/{id}', [B::class, 'product']);

// Authenticated buyer
Route::middleware('api.buyer')->group(function () {
    Route::post('/logout', [B::class, 'logout']);
    Route::get('/me', [B::class, 'me']);
    Route::post('/profile', [B::class, 'updateProfile']);

    Route::get('/cart', [B::class, 'cart']);
    Route::post('/cart', [B::class, 'cartAdd']);
    Route::post('/cart/{id}', [B::class, 'cartUpdate']);
    Route::delete('/cart/{id}', [B::class, 'cartRemove']);

    Route::get('/addresses', [B::class, 'addresses']);
    Route::post('/addresses', [B::class, 'addressAdd']);
    Route::post('/addresses/{id}/default', [B::class, 'addressSetDefault']);

    Route::post('/checkout', [B::class, 'checkout']);
    Route::get('/orders', [B::class, 'orders']);
    Route::get('/orders/{id}', [B::class, 'orderDetail']);
    Route::post('/orders/{id}/confirm', [B::class, 'orderConfirm']);
    Route::post('/orders/{id}/rating', [B::class, 'orderRating']);

    Route::get('/vouchers', [B::class, 'vouchers']);
    Route::get('/notifications', [B::class, 'notifications']);

    Route::get('/messages/contacts', [B::class, 'messageContacts']);
    Route::get('/messages/{userId}', [B::class, 'messageThread']);
    Route::post('/messages', [B::class, 'sendMessage']);

    Route::get('/complaints', [B::class, 'complaints']);
    Route::post('/complaints', [B::class, 'complaintCreate']);
});
