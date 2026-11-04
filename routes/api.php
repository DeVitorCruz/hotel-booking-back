<?php
 /**
  * API Routes - ecommerce-blog-back
  * 
  * Route groups:
  *  - Public Auth       : register, login, password reset
  *  - public Browse     : categories tree, product listing and search
  *  - Authenticated     : user profile, logout, seller onboarding,
  *                        product CRUD, category suggestions
  *  - Admin (role=admin): category and seller approval/rejection
  * 
  * Authentication: Laravel Sanctum (Bearer token)
  * Authorization: Spatie Laravel Permission (roles + middleware)
  */
	
 use App\Http\Controllers\AuthController;
 use App\Http\Controllers\Api\SellerController;
 use App\Http\Controllers\Api\SellerProductController;
 use App\Http\Controllers\Api\CategoryController;
 use App\Http\Controllers\Api\ProductController;
 use App\Http\Controllers\Api\CartController;
 use App\Http\Controllers\Api\OrderController;
 use App\Http\Controllers\Api\FAQController;
 use App\Http\Controllers\Api\BlogController;
 use App\Http\Controllers\Api\ContactController;
 use App\Http\Controllers\Api\Admin\OrderStatusController;
 use App\Http\Controllers\Api\UserProfileController;
 use App\Http\Controllers\Api\EmploymentController;
 use App\Http\Controllers\Api\TeamController;
 use App\Http\Controllers\Api\WishlistController;
 use App\Http\Controllers\Api\StaffSpotlightController;
 use App\Http\Controllers\Api\ReviewController;
 use App\Http\Controllers\Api\CheckoutController;
 use App\Http\Controllers\Api\WebhookController;
 use App\Http\Controllers\Api\RefundController;
 use App\Http\Controllers\Api\SocialAuthController;
 use App\Http\Controllers\Api\Platform\PlanController;
 use App\Http\Controllers\Api\Platform\TenantController;
 use App\Http\Controllers\Api\Platform\AppController;
 use App\Http\Controllers\Api\Platform\ThemeController;
 use App\Http\Controllers\Api\Platform\DomainController;
 use App\Http\Controllers\Api\Platform\DashboardController;
 use App\Http\Controllers\Api\Platform\PlatformAuthController;
 use Illuminate\Http\Request;
 use Illuminate\Support\Facades\Route;

 // --------- Public: Authentication ---------------------------
 Route::post('/register', [AuthController::class, 'register'])
     ->middleware(['guest:'.config('fortify.guard')])
     ->name('api.register');

 Route::post('/login', [AuthController::class, 'login'])
     ->middleware(['guest:'.config('fortify.guard')])
     ->name('api.login');

 Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])
     ->middleware(['guest:'.config('fortify.guard')])
     ->name('api.forgotPassword');

 Route::post('/reset-password', [AuthController::class, 'resetPassword'])
     ->middleware(['guest:'.config('fortify.guard')])
     ->name('api.resetPassword');

 // ------ Categories (public) -----------------------------
 Route::get('/categories', [CategoryController::class, 'index']);
 Route::get('/categories/{category}', [CategoryController::class, 'show']);

 // ------ Products (public) -------------------------------
 Route::get('/products', [ProductController::class, 'index']);
 Route::get('/products/{product}', [ProductController::class, 'show']);

 // ------- Blog (public) -----------------------------
 Route::get('/blogs', [BlogController::class, 'index']);
 Route::get('/blogs/{blog}', [BlogController::class, 'show']);

 // ------- FAQ (public) -----------------------------
 Route::get('/faqs', [FAQController::class, 'index']);

 // ------- Contact (public + guest) -----------------------------
 Route::post('/contacts', [ContactController::class, 'store'])
    ->middleware('auth:sanctum')->withoutMiddleware('auth:sanctum');
     
 // ------ Staff Spotlights (public about page) ---------------------------
 Route::get('/spotlights', [StaffSpotlightController::class, 'index']);

 // ------ Reviews (public) ---------------------------
 Route::get('/products/{product}/reviews', [ReviewController::class, 'productReviews']);
 Route::get('/sellers/{seller}/reviews', [ReviewController::class, 'sellerReviews']);

 // --- Webhooks (no auth — gateway callbacks) -------------------------------------
 Route::post('/webhooks/mercadopago', [WebhookController::class, 'mercadopago'])
    ->name('webhooks.mercadopago');

 Route::post('/webhooks/pagseguro', [WebhookController::class, 'pagseguro'])
    ->name('webhooks.pagseguro');

 // ------ Social login ------------------------------------
 Route::get('/auth/social/{provider}', [SocialAuthController::class, 'redirect']);
 Route::get('/auth/social/{provider}/callback', [SocialAuthController::class, 'callback']);

 // ------ Platform API ------------------------------------
 // Public
 Route::prefix('platform')->group(function () {
    Route::post('login', [PlatformAuthController::class, 'login']);

    Route::get('plans', [PlanController::class, 'index']);
    Route::get('plans/{plan}', [PlanController::class, 'show']);
    Route::get('themes', [ThemeController::class, 'index']);

    // Auth required
    Route::middleware('auth:sanctum', 'platform_staff')->group(function () {
        Route::post('logout', [PlatformAuthController::class, 'logout']);
        Route::get('me', [PlatformAuthController::class, 'me']);

        // Tenant management
        Route::get('tenant', [TenantController::class, 'show']);
        Route::post('tenant', [TenantController::class, 'store']);
        Route::patch('tenant', [TenantController::class, 'update']);

        // App management
        Route::get('apps', [AppController::class, 'index']);
        Route::post('apps', [AppController::class, 'store']);
        Route::patch('apps/{tenantApp}/theme', [AppController::class, 'switchTheme']);

        // Domain management
        Route::post('apps/{tenantApp}/domains', [DomainController::class, 'store']);
        Route::patch('apps/{tenantApp}/domains/{domain}/verify',[DomainController::class, 'verify']);
        Route::delete('apps/{tenantApp}/domains/{domain}', [DomainController::class, 'destroy']);

        // Dashboard
        Route::get('dashboard', [DashboardController::class, 'index']);
        Route::get('dashboard/app/{tenantApp}', [DashboardController::class, 'app']);
    });
 });

 // ------ Authencticated routes ---------------------------
 Route::middleware('auth:sanctum')->group(function () {
  
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/me', [AuthController::class, 'me'])->name('api.me');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('api.logout');

    Route::get('/profile', [UserProfileController::class, 'show']);
    Route::put('/profile', [UserProfileController::class, 'update']);

    // Blog (write)
    Route::post('/blogs', [BlogController::class, 'store']);
    Route::patch('/blogs/{blog}', [BlogController::class, 'update']);
    Route::delete('/blogs/{blog}', [BlogController::class, 'destroy']);

    // Seller
    Route::get('/seller/profile', [SellerController::class, 'show']);
    Route::post('/seller/onboard', [SellerController::class, 'store']);
    Route::patch('/seller/profile', [SellerController::class, 'update']);

    // Products
    Route::post('/products/add', [SellerProductController::class, 'store']);
    Route::patch('/products/{product}', [SellerProductController::class, 'update']);
    Route::delete('/products/{product}', [SellerProductController::class, 'destroy']);
    Route::get('/seller/products', [ProductController::class, 'sellerProducts']);

    // Categories
    Route::post('/categories', [CategoryController::class, 'store']);
    
    // --- Admin routes ------------------------
    Route::middleware('admin')->prefix('admin')->group(function () {
		
		// Category approval
		Route::get('/categories/pending', [App\Http\Controllers\Api\Admin\CategoryApprovalController::class, 'index']);
		Route::patch('/categories/{category}/approve', [App\Http\Controllers\Api\Admin\CategoryApprovalController::class, 'approve']);
		Route::patch('/categories/{category}/reject', [App\Http\Controllers\Api\Admin\CategoryApprovalController::class, 'reject']);
		
		// Seller approval
		Route::get('/sellers/pending', [App\Http\Controllers\Api\Admin\SellerApprovalController::class, 'index']);
		Route::patch('/sellers/{seller}/approve', [App\Http\Controllers\Api\Admin\SellerApprovalController::class, 'approve']);
		Route::patch('/sellers/{seller}/reject', [App\Http\Controllers\Api\Admin\SellerApprovalController::class, 'reject']);
		Route::patch('/sellers/{seller}/suspend', [App\Http\Controllers\Api\Admin\SellerApprovalController::class, 'suspend']);

        // Blog admin
        Route::get('/blogs', [BlogController::class, 'adminIndex']);
        
        // FAQ management
        Route::post('/faqs', [FAQController::class, 'store']);
        Route::patch('/faqs/{faq}', [FAQController::class, 'update']);
        Route::delete('/faqs/{faq}', [FAQController::class, 'destroy']);

        // Contact management
        Route::get('/contacts', [ContactController::class, 'index']);
        Route::get('/contacts/{contact}', [ContactController::class, 'show']);
        Route::patch('/contacts/{contact}', [ContactController::class, 'update']);
        Route::delete('/contacts/{contact}', [ContactController::class, 'destroy']);

        // Staff Spotlights management
        Route::get('/spotlights', [StaffSpotlightController::class, 'adminIndex']);
        Route::post('/spotlights', [StaffSpotlightController::class, 'store']);
        Route::patch('/spotlights/{spotlight}', [StaffSpotlightController::class, 'update']);
        Route::delete('/spotlights/{spotlight}', [StaffSpotlightController::class, 'destroy']);

		// Order management
		Route::get('/orders', [OrderStatusController::class, 'index']);
		Route::patch('/orders/{order}/status', [OrderStatusController::class, 'updateStatus']);

        // Reviews moderation
        Route::get('/reviews', [ReviewController::class, 'adminIndex']);
        Route::patch('/reviews/{review}/approve', [ReviewController::class, 'approve']);
        Route::patch('/reviews/{review}/reject', [ReviewController::class, 'reject']);
        Route::delete('/reviews/{review}', [ReviewController::class, 'destroy']);

        // Refunds
        Route::get('/refunds', [RefundController::class, 'index']);
        Route::post('/refunds', [RefundController::class, 'store']);
        Route::get('/refunds/{refund}', [RefundController::class, 'show']);
        Route::patch('/refunds/{refund}/approve', [RefundController::class, 'approve']);
        Route::patch('/refunds/{refund}/reject', [RefundController::class, 'reject']);
	});
	
    // Wishlist (registered users only - no guest wishlist)
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/{productId}', [WishlistController::class, 'add']);
    Route::delete('/wishlist/clear', [WishlistController::class, 'clear']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'remove']);

    // Teams
    Route::get('/teams', [TeamController::class, 'index']);
    Route::post('/teams', [TeamController::class, 'store']);
    Route::get('/teams/{team}', [TeamController::class, 'show']);
    Route::patch('/teams/{team}', [TeamController::class, 'update']);
    Route::delete('/teams/{team}', [TeamController::class, 'destroy']);
    Route::post('/teams/{team}/members', [TeamController::class, 'addMember']);
    Route::delete('/teams/{team}/members/{user}', [TeamController::class, 'removeMember']);

    // Employment
    Route::get('/employments', [EmploymentController::class, 'index']);
    Route::post('/employments', [EmploymentController::class, 'store']);
    Route::get('/employments/{employment}', [EmploymentController::class, 'show']);
    Route::patch('/employments/{employment}/suspend', [EmploymentController::class, 'suspend']);
    Route::delete('/employments/{employment}', [EmploymentController::class, 'destroy']);

	// Cart 
	Route::get('/cart', [CartController::class, 'show']);
	Route::post('/cart/items', [CartController::class, 'addItem']);
	Route::patch('/cart/items/{item}', [CartController::class, 'updateItem']);
	Route::delete('/cart/items/{item}', [CartController::class, 'removeItem']);
	Route::delete('/cart', [CartController::class, 'clear']);
	Route::post('/cart/merge', [CartController::class, 'mergeGuestCart']);
	
	// Orders
	Route::get('/orders', [OrderController::class, 'index']);
	Route::post('/orders', [OrderController::class, 'store']);
	Route::get('/orders/{order}', [OrderController::class, 'show']);
	Route::patch('/orders/{order}/cancel', [OrderController::class, 'cancel']);	

    // Reviews (submit)
    Route::post('/products/{product}/reviews', [ReviewController::class, 'storeProductReview']);
    Route::post('/sellers/{seller}/reviews', [ReviewController::class, 'storeSellerReview']);
    Route::patch('/reviews/{review}', [ReviewController::class, 'update']);

    // Checkout
    Route::get('/checkout/options', [CheckoutController::class, 'options']);
    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::get('/checkout/{order}', [CheckoutController::class, 'show']);
}); 
