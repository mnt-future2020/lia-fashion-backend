<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SubcategoryController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\PosController;
use App\Http\Controllers\InvoiceSettingController;
use App\Http\Controllers\UserProfileController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\CouponController;
use App\Http\Controllers\Admin\VendorController;
use App\Http\Controllers\Admin\PurchaseEntryController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\PublicController\ProductController as PublicProductController;
use App\Http\Controllers\PublicController\CategoryController as PublicCategoryController;
use App\Http\Controllers\PublicController\SubcategoryController as PublicSubcategoryController;
use App\Http\Controllers\PublicController\SiteReviewController as PublicSiteReviewController;
use App\Http\Controllers\PublicController\PromoPopupController as PublicPromoPopupController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\Admin\ShippingRuleController;
use App\Http\Controllers\Admin\PaymentGatewaySettingController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\Admin\AdminReviewController;
use App\Http\Controllers\RazorpayController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\Admin\TransactionController as AdminTransactionController;
use App\Http\Controllers\Admin\ShiprocketSettingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\UserOrderController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SiteReviewController as AdminSiteReviewController;

// User Transaction Routes
Route::middleware('auth:sanctum')->group(function () {
    // Order Routes
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/{id}', [OrderController::class, 'show']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::patch('/orders/{id}/note', [OrderController::class, 'updateNote']);

    // Basic Transaction Routes
    Route::post('/transactions', [TransactionController::class, 'store']);
    // Route::get('/transactions/{id}', [AdminTransactionController::class, 'show']);
    // Alias route to fetch transaction details by order id (mirrors admin usage)
    Route::get('/transactions/orders/{id}', [AdminTransactionController::class, 'show']);

    // Payment Routes
    Route::prefix('razorpay')->group(function () {
        Route::post('/create-order', [RazorpayController::class, 'createOrder']);
        Route::post('/verify-payment', [RazorpayController::class, 'verifyPayment']);
        Route::get('/transactions/{transactionId}/items', [RazorpayController::class, 'getRazorpayOrderItems']);
        Route::post('/verify-payment-direct', [RazorpayController::class, 'verifyPaymentDirect']);
        Route::get('/pending-payments', [RazorpayController::class, 'getPendingPayments']);
        Route::post('/verify-pending-payments', [RazorpayController::class, 'verifyPendingPayments']);
    });
});

// Razorpay webhook route (no authentication or CSRF required)
Route::post('/razorpay/webhook', [RazorpayController::class, 'webhook'])
    ->middleware(['api'])
    ->withoutMiddleware(['auth:sanctum', 'throttle:api', 'verify.csrf']);

// Debug webhook route (for testing signature verification)
Route::post('/razorpay/webhook-debug', [RazorpayController::class, 'debugWebhook'])
    ->middleware(['api'])
    ->withoutMiddleware(['auth:sanctum', 'throttle:api', 'verify.csrf']);

// Admin routes
Route::prefix('admin')->group(function () {
    // Public admin routes
    Route::post('/login', [AdminAuthController::class, 'login']);
    Route::post('/forgot-password', [AdminAuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AdminAuthController::class, 'resetPassword']);
    Route::get('/settings', [SettingController::class, 'index']);
    Route::post('/settings', [SettingController::class, 'store']);

    // Add invoice number generation as a public route
    Route::get('/invoice-settings/generate-number', [InvoiceSettingController::class, 'generateInvoiceNumber']);

    // Public data routes - moved here but still under admin prefix
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('categories/{categoryId}/subcategories', [CategoryController::class, 'subcategories'])
        ->where('categoryId', '[0-9]+');

    // Public Shipping Rules Routes (for checkout page)
    Route::get('/shipping-rules', [ShippingRuleController::class, 'index']);
    Route::get('/shipping-rules/{shippingRule}', [ShippingRuleController::class, 'show']);
    Route::get('/users/{id}/orders', [UserOrderController::class, 'userOrders']);
    Route::get('/users/{id}/order-stats', [UserOrderController::class, 'userOrderStats']);

    // Protected admin routes
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/verify', [AdminAuthController::class, 'verify']);
        Route::post('/logout', [AdminAuthController::class, 'logout']);

        // Company Settings Routes
        Route::get('/company', [CompanyController::class, 'index']);
        Route::post('/company', [CompanyController::class, 'store']);

        // Payment Gateway Settings Routes
        Route::get('/payment-gateway-settings', [PaymentGatewaySettingController::class, 'index']);
        Route::post('/payment-gateway-settings', [PaymentGatewaySettingController::class, 'store']);
        Route::get('/payment-gateway-settings/{id}', [PaymentGatewaySettingController::class, 'show']);
        Route::put('/payment-gateway-settings/{id}', [PaymentGatewaySettingController::class, 'update']);
        Route::delete('/payment-gateway-settings/{id}', [PaymentGatewaySettingController::class, 'destroy']);
        Route::get('/payment-gateway-settings/active/{gateway?}', [PaymentGatewaySettingController::class, 'getActive']);

        // Shiprocket Settings Routes
        Route::get('/shiprocket-settings', [ShiprocketSettingController::class, 'index']);
        Route::post('/shiprocket-settings', [ShiprocketSettingController::class, 'store']);
        Route::put('/shiprocket-settings/{id}', [ShiprocketSettingController::class, 'update']);

        // Protected category operations
        Route::post('categories', [CategoryController::class, 'store']);
        Route::put('categories/{category}', [CategoryController::class, 'update']);
        Route::delete('categories/{category}', [CategoryController::class, 'destroy']);

        Route::apiResource('subcategories', SubcategoryController::class);
        Route::patch('products/{id}/toggle-status', [ProductController::class, 'toggleStatus']);
        Route::apiResource('products', ProductController::class); // This already includes show, update, delete
        Route::apiResource('coupons', CouponController::class);
        Route::patch('coupons/{id}/toggle-status', [CouponController::class, 'toggleStatus']);
        Route::post('coupons/validate', [CouponController::class, 'validateCoupon']);

        // Add users route for admin
        Route::get('/users', [RegisterController::class, 'getAllUsers']);
        Route::get('/users/{id}', [RegisterController::class, 'getUserDetails']);


        // Admin Transaction Routes
        Route::prefix('transactions')->group(function () {
            Route::get('/', [AdminTransactionController::class, 'index']);
            Route::post('/', [AdminTransactionController::class, 'store']);
            Route::get('/{id}', [AdminTransactionController::class, 'show']);
            // Alias to fetch transaction by order number/id under admin prefix as well
            Route::get('/orders/{id}', [AdminTransactionController::class, 'show']);
            Route::put('/{id}/status', [AdminTransactionController::class, 'updateStatus']);
            Route::post('/{id}/convert-to-order', [AdminTransactionController::class, 'convertToOrder']);
            Route::get('/statistics/summary', [AdminTransactionController::class, 'getStatistics']);
        });

        // POS routes
        Route::prefix('pos')->group(function () {
            Route::get('/next-order-number', [PosController::class, 'getNextOrderNumber']);
            Route::post('/orders', [PosController::class, 'storeOrder']);
            Route::get('/orders', [PosController::class, 'getOrders']);
            Route::get('/orders/{id}', [PosController::class, 'getOrder']);
            Route::get('/customers', [PosController::class, 'getCustomers']);
            Route::get('/customers/{phone}/orders', [PosController::class, 'getCustomerOrders']);
        });

        // Invoice Settings routes
        Route::prefix('invoice-settings')->group(function () {
            Route::get('/', [InvoiceSettingController::class, 'index']);
            Route::post('/', [InvoiceSettingController::class, 'store']);
            Route::get('/active', [InvoiceSettingController::class, 'getActiveSetting']);
            Route::get('/{id}', [InvoiceSettingController::class, 'show']);
            Route::put('/{id}', [InvoiceSettingController::class, 'update']);
            Route::delete('/{id}', [InvoiceSettingController::class, 'destroy']);
        });

        // Vendor Routes
        Route::apiResource('vendors', VendorController::class);

        // Purchase Entry Routes
        Route::post('/purchase-entries', [PurchaseEntryController::class, 'store']);
        Route::get('/purchase-entries/{purchase_no}', [PurchaseEntryController::class, 'showByNumber']);

        // Banner Routes
        Route::post('banners', [BannerController::class, 'store']);
        Route::delete('banners/{id}', [BannerController::class, 'destroy']);
        Route::post('banners/reorder', [BannerController::class, 'updateOrder']);


        // Offers Management
        Route::get('/offers', [\App\Http\Controllers\OfferController::class, 'index']);
        Route::post('/offers', [\App\Http\Controllers\OfferController::class, 'store']);
        Route::put('/offers/{id}', [\App\Http\Controllers\OfferController::class, 'update']);
        Route::delete('/offers/{id}', [\App\Http\Controllers\OfferController::class, 'destroy']);

        // Protected Shipping Rules Routes (admin only operations)
        Route::post('/shipping-rules', [ShippingRuleController::class, 'store']);
        Route::put('/shipping-rules/{shippingRule}', [ShippingRuleController::class, 'update']);
        Route::delete('/shipping-rules/{shippingRule}', [ShippingRuleController::class, 'destroy']);

        // Order Management Routes
        Route::get('/orders', [App\Http\Controllers\Admin\OrderController::class, 'index']);
        Route::get('/orders/{id}', [App\Http\Controllers\Admin\OrderController::class, 'show']);
        Route::patch('/orders/{id}/status', [App\Http\Controllers\Admin\OrderController::class, 'updateStatus']);
        Route::patch('/orders/{id}/note', [App\Http\Controllers\Admin\OrderController::class, 'updateNote']);

        // Dashboard Route
        Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

        // Test route
        Route::get('/test', function () {
            return response()->json(['status' => 'success', 'message' => 'API is working']);
        });

        // Reports Routes
        Route::prefix('reports')->group(function () {
            Route::get('/dashboard-stats', [\App\Http\Controllers\Admin\ReportsController::class, 'getDashboardStats']);
            Route::get('/sales-trend', [\App\Http\Controllers\Admin\ReportsController::class, 'getSalesTrend']);
            Route::get('/order-status', [\App\Http\Controllers\Admin\ReportsController::class, 'getOrderStatusDistribution']);
            Route::get('/top-products', [\App\Http\Controllers\Admin\ReportsController::class, 'getTopProducts']);
            Route::get('/customer-analytics', [\App\Http\Controllers\Admin\ReportsController::class, 'getCustomerAnalytics']);
            Route::get('/comprehensive', [\App\Http\Controllers\Admin\ReportsController::class, 'getComprehensiveReport']);
            
            // Inventory Reports
            Route::get('/stock-availability', [\App\Http\Controllers\Admin\ReportsController::class, 'getStockAvailabilityReport']);
            Route::get('/moving-items', [\App\Http\Controllers\Admin\ReportsController::class, 'getMovingItemsReport']);
            Route::get('/inventory-valuation', [\App\Http\Controllers\Admin\ReportsController::class, 'getInventoryValuationReport']);
            Route::get('/supplier-report', [\App\Http\Controllers\Admin\ReportsController::class, 'getSupplierReport']);
            
            Route::get('/export', [\App\Http\Controllers\Admin\ReportsController::class, 'exportReport']);
            Route::get('/export-detailed', [\App\Http\Controllers\Admin\ReportsController::class, 'exportDetailedCSV']);
        });

        // Site Reviews Management
        Route::get('/site-reviews', [AdminSiteReviewController::class, 'index']);
        Route::patch('/site-reviews/{id}/approve', [AdminSiteReviewController::class, 'approve']);
        Route::delete('/site-reviews/{id}', [AdminSiteReviewController::class, 'destroy']);

        // Promo Popups (admin)
        Route::apiResource('promo-popups', \App\Http\Controllers\Admin\PromoPopupController::class);
    });
});

// User routes
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [RegisterController::class, 'login']);
Route::post('/verify-otp', [RegisterController::class, 'verifyOtp']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'forgotPassword']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);

// Google OAuth routes
Route::get('/auth/google', [App\Http\Controllers\Auth\GoogleController::class, 'redirectToGoogle']);
Route::get('/auth/google/callback', [App\Http\Controllers\Auth\GoogleController::class, 'handleGoogleCallback']);
Route::post('/auth/google/login', [App\Http\Controllers\Auth\GoogleController::class, 'handleGoogleLogin']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [RegisterController::class, 'logout']);
    Route::get('/user', [RegisterController::class, 'user']);

    // User Profile Routes
    Route::get('/user/profile', [UserProfileController::class, 'getProfile']);
    Route::post('/user/profile/update', [UserProfileController::class, 'updateProfile']);
});

// Add public routes outside admin prefix
Route::get('/products', [PublicProductController::class, 'index']);
Route::get('/products/{id}', [PublicProductController::class, 'show']);
Route::get('/categories', [PublicCategoryController::class, 'index']);
Route::get('/categories/{id}', [PublicCategoryController::class, 'show']);
Route::get('/products/{id}/reviews', [PublicProductController::class, 'getReviews']);
Route::get('/subcategories', [PublicSubcategoryController::class, 'index']);
Route::get('/reviews', [PublicSiteReviewController::class, 'index']);
Route::post('/reviews', [PublicSiteReviewController::class, 'store']);

// Public Promo Popup endpoint
Route::get('/promo-popup', [PublicPromoPopupController::class, 'show']);



// Cart Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'addToCart']);
    Route::patch('/cart/items/{itemId}', [CartController::class, 'updateQuantity']);
    Route::delete('/cart/items/{itemId}', [CartController::class, 'removeItem']);
    Route::delete('/cart/clear', [CartController::class, 'clear']);
});

// Public Banner Routes - Add this outside admin prefix
Route::get('/admin/banners', [App\Http\Controllers\Admin\BannerController::class, 'index']);

// Admin Banner Routes - Keep these inside admin prefix
Route::middleware('auth:sanctum')->group(function () {
    Route::post('admin/banners', [App\Http\Controllers\Admin\BannerController::class, 'store']);
    Route::delete('admin/banners/{id}', [App\Http\Controllers\Admin\BannerController::class, 'destroy']);
    Route::post('admin/banners/reorder', [App\Http\Controllers\Admin\BannerController::class, 'updateOrder']);
});

// Product review routes
Route::get('products/{product}/reviews', [ProductReviewController::class, 'index']);
Route::middleware('auth:sanctum')->post('products/{product}/reviews', [ProductReviewController::class, 'store']);

// Admin review routes
Route::middleware('auth:sanctum')->prefix('admin')->group(function () {
    Route::prefix('reviews')->group(function () {
        Route::get('/', [AdminReviewController::class, 'index']);
        Route::get('/stats', [AdminReviewController::class, 'stats']);
        Route::get('/overall-stats', [AdminReviewController::class, 'overallStats']);
        Route::get('/timeline-stats', [AdminReviewController::class, 'timelineStats']);
        Route::delete('/{review}', [AdminReviewController::class, 'destroy']);
    });
});

Route::get('/reviews/positive', [AdminReviewController::class, 'getPositiveReviews']);

//contact routes
Route::post('/contact', [ContactController::class, 'store']);
Route::get('/company', [CompanyController::class, 'index']);

// Admin Settings Routes
Route::middleware(['auth:admin'])->prefix('admin')->group(function () {
    Route::get('/settings/cloudinary', [SettingsController::class, 'getCloudinarySettings']);
    Route::post('/settings/cloudinary', [SettingsController::class, 'updateCloudinarySettings']);
});
