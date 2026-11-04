<?php
/**
 * API Routes - hotel-booking-back
 *
 * Route groups:
 *  - Public auth       : register, login, password reset, social login
 *  - Public hotel      : hotel info, room types, availability
 *  - Public booking    : create booking, manage it with the booking token, PayPal capture
 *  - Public content    : blog, FAQ, contact, staff spotlights
 *  - Webhooks          : PayPal
 *  - Authenticated     : profile, my bookings, content management
 *  - Staff (/admin)    : bookings, room blocks, settings, content moderation
 *
 * Authentication: Laravel Sanctum (Bearer token)
 * Authorization: Spatie Laravel Permission (roles + permissions)
 */

use App\Http\Controllers\Api\Admin\BookingAdminController;
use App\Http\Controllers\Api\Admin\RoomBlockController;
use App\Http\Controllers\Api\Admin\SettingsController;
use App\Http\Controllers\Api\AvailabilityController;
use App\Http\Controllers\Api\BlogController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\FAQController;
use App\Http\Controllers\Api\HotelController;
use App\Http\Controllers\Api\PayPalWebhookController;
use App\Http\Controllers\Api\RoomTypeController;
use App\Http\Controllers\Api\SocialAuthController;
use App\Http\Controllers\Api\StaffSpotlightController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// --------- Public: Authentication ---------------------------
Route::middleware(['guest:' . config('fortify.guard')])->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->name('api.register');
    Route::post('/login', [AuthController::class, 'login'])->name('api.login');
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.forgotPassword');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->name('api.resetPassword');
});

Route::get('/auth/social/{provider}', [SocialAuthController::class, 'redirect']);
Route::get('/auth/social/{provider}/callback', [SocialAuthController::class, 'callback']);

// --------- Public: Hotel & rooms ----------------------------
Route::get('/hotel', [HotelController::class, 'show']);
Route::get('/amenities', [HotelController::class, 'amenities']);
Route::get('/room-types', [RoomTypeController::class, 'index']);
Route::get('/room-types/{slug}', [RoomTypeController::class, 'show']);
Route::get('/availability', AvailabilityController::class)->middleware('throttle:60,1');

// --------- Public: Booking (guest checkout, token-protected) -
Route::middleware('throttle:bookings')->group(function () {
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::post('/bookings/{reference}/paypal/capture', [BookingController::class, 'capture']);
    Route::post('/bookings/{reference}/paypal/retry', [BookingController::class, 'retry']);
    Route::post('/bookings/{reference}/cancel', [BookingController::class, 'cancel']);
});
Route::get('/bookings/{reference}', [BookingController::class, 'show'])->middleware('throttle:30,1');

// --------- Public: Content ----------------------------------
Route::get('/blogs', [BlogController::class, 'index']);
Route::get('/blogs/{blog}', [BlogController::class, 'show']);
Route::get('/faqs', [FAQController::class, 'index']);
Route::get('/spotlights', [StaffSpotlightController::class, 'index']);
Route::post('/contacts', [ContactController::class, 'store'])->middleware('throttle:5,1');

// --------- Webhooks (no auth — signature verified) ----------
Route::post('/webhooks/paypal', PayPalWebhookController::class)->name('webhooks.paypal');

// --------- Authenticated ------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me'])->name('api.me');
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::put('/profile', [UserProfileController::class, 'update']);

    Route::get('/my/bookings', [BookingController::class, 'mine']);

    // Blog authoring (editor+, checked in controller)
    Route::post('/blogs', [BlogController::class, 'store']);
    Route::patch('/blogs/{blog}', [BlogController::class, 'update']);
    Route::delete('/blogs/{blog}', [BlogController::class, 'destroy']);

    // --------- Staff back office ----------------------------
    Route::middleware('staff')->prefix('admin')->group(function () {
        // Bookings / front desk
        Route::middleware('permission:bookings.view-any')->group(function () {
            Route::get('/bookings', [BookingAdminController::class, 'index']);
            Route::get('/bookings/{booking}', [BookingAdminController::class, 'show']);
        });
        Route::middleware('permission:bookings.manage')->group(function () {
            Route::post('/bookings', [BookingAdminController::class, 'store']);
            Route::patch('/bookings/{booking}/status', [BookingAdminController::class, 'status']);
            Route::post('/bookings/{booking}/mark-paid', [BookingAdminController::class, 'markPaid']);
        });
        Route::middleware('permission:bookings.cancel-any')->group(function () {
            Route::post('/bookings/{booking}/cancel', [BookingAdminController::class, 'cancel']);
            Route::post('/bookings/{booking}/refund', [BookingAdminController::class, 'refund']);
        });

        // Room blocks
        Route::middleware('permission:room-blocks.manage')->group(function () {
            Route::get('/room-blocks', [RoomBlockController::class, 'index']);
            Route::post('/room-blocks', [RoomBlockController::class, 'store']);
            Route::delete('/room-blocks/{roomBlock}', [RoomBlockController::class, 'destroy']);
        });

        // Hotel settings
        Route::middleware('permission:settings.manage')->group(function () {
            Route::get('/settings', [SettingsController::class, 'show']);
            Route::patch('/settings', [SettingsController::class, 'update']);
        });

        // Content moderation
        Route::get('/blogs', [BlogController::class, 'adminIndex'])->middleware('permission:blog.edit-any');

        Route::middleware('permission:faq.manage')->group(function () {
            Route::post('/faqs', [FAQController::class, 'store']);
            Route::patch('/faqs/{faq}', [FAQController::class, 'update']);
            Route::delete('/faqs/{faq}', [FAQController::class, 'destroy']);
        });

        Route::middleware('permission:contacts.view-any')->group(function () {
            Route::get('/contacts', [ContactController::class, 'index']);
            Route::get('/contacts/{contact}', [ContactController::class, 'show']);
            Route::patch('/contacts/{contact}', [ContactController::class, 'update']);
            Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);
        });

        Route::middleware('permission:spotlights.manage')->group(function () {
            Route::get('/spotlights', [StaffSpotlightController::class, 'adminIndex']);
            Route::post('/spotlights', [StaffSpotlightController::class, 'store']);
            Route::patch('/spotlights/{spotlight}', [StaffSpotlightController::class, 'update']);
            Route::delete('/spotlights/{spotlight}', [StaffSpotlightController::class, 'destroy']);
        });
    });
});
