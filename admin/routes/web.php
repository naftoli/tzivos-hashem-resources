<?php

use App\Http\Controllers\Admin\AdminAccessRequestController;
use App\Http\Controllers\Admin\MashpiaRoleMapController;
use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Api\ContentController as ApiContentController;
use App\Http\Controllers\Api\PageController as ApiPageController;
use App\Http\Controllers\Admin\StatsController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\NoAccessController;
use App\Http\Controllers\PublicFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
    // Real Mashpia sign-in (MASHPIA_AUTH_MODE=real): the button, and where Mashpia sends people back
    Route::get('/login/mashpia', [LoginController::class, 'mashpiaStart'])->name('login.mashpia');
    Route::get('/login/mashpia/return', [LoginController::class, 'mashpiaReturn'])->name('login.mashpia.return');
    Route::view('/forgot-password', 'auth.forgot-password')->name('password.request');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');
    Route::get('/no-access', NoAccessController::class)->name('access.denied');

    Route::middleware('admin.site')->group(function () {
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/account', [\App\Http\Controllers\AccountController::class, 'show'])->name('account');
    Route::get('/account/password', [\App\Http\Controllers\AccountController::class, 'editPassword'])->name('account.password');
    Route::post('/account/password', [\App\Http\Controllers\AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::post('/account/request-admin', [AdminAccessRequestController::class, 'store'])->name('account.request-admin');

    Route::middleware('hq')->group(function () {
        Route::get('/admin/roles', [RoleController::class, 'index'])->name('admin.roles');
        Route::post('/admin/roles', [RoleController::class, 'store'])->name('admin.roles.store');
        Route::put('/admin/roles/{user}', [RoleController::class, 'update'])->name('admin.roles.update');
        Route::post('/admin/roles/{user}/automatic', [RoleController::class, 'reset'])->name('admin.roles.reset');
        Route::post('/admin/roles/{user}/owner', [RoleController::class, 'setOwner'])->name('admin.roles.owner');
        Route::get('/admin/mashpia-roles', [MashpiaRoleMapController::class, 'index'])->name('admin.mashpia-roles');
        Route::post('/admin/mashpia-roles', [MashpiaRoleMapController::class, 'store'])->name('admin.mashpia-roles.store');
        Route::put('/admin/mashpia-roles/{map}', [MashpiaRoleMapController::class, 'update'])->name('admin.mashpia-roles.update');
        Route::delete('/admin/mashpia-roles/{map}', [MashpiaRoleMapController::class, 'destroy'])->name('admin.mashpia-roles.destroy');
        // Admin requests: the Owner only
        Route::middleware('owner')->group(function () {
            Route::get('/admin/admin-requests', [AdminAccessRequestController::class, 'index'])->name('admin.admin-requests');
            Route::post('/admin/admin-requests/{accessRequest}/approve', [AdminAccessRequestController::class, 'approve'])->name('admin.admin-requests.approve');
            Route::post('/admin/admin-requests/{accessRequest}/decline', [AdminAccessRequestController::class, 'decline'])->name('admin.admin-requests.decline');
        });
        Route::get('/admin/sites', [SiteController::class, 'index'])->name('admin.sites');
        Route::post('/admin/sites', [SiteController::class, 'store'])->name('admin.sites.store');
        Route::get('/admin/sites/{site}/edit', [SiteController::class, 'edit'])->name('admin.sites.edit');
        Route::put('/admin/sites/{site}', [SiteController::class, 'update'])->name('admin.sites.update');
        Route::get('/admin/login-settings', fn () => view('admin.login-settings', ['mode' => app(\App\Services\MashpiaAuthService::class)->mode()]))->name('admin.login-settings');
        Route::get('/admin/files', [\App\Http\Controllers\Admin\FileDashboardController::class, 'index'])->name('admin.files');
        Route::put('/admin/files', [\App\Http\Controllers\Admin\FileDashboardController::class, 'update'])->name('admin.files.update');
        Route::delete('/admin/files', [\App\Http\Controllers\Admin\FileDashboardController::class, 'clear'])->name('admin.files.clear');
        Route::post('/admin/files/remove', [\App\Http\Controllers\Admin\FileDashboardController::class, 'remove'])->name('admin.files.remove');
        Route::post('/admin/files/restore', [\App\Http\Controllers\Admin\FileDashboardController::class, 'restore'])->name('admin.files.restore');
        Route::put('/admin/files/visibility', [\App\Http\Controllers\Admin\FileVisibilityController::class, 'update'])->name('admin.files.visibility.update');
        Route::get('/admin/stats', [StatsController::class, 'index'])->name('admin.stats');
        Route::get('/admin/stats.csv', [StatsController::class, 'csv'])->name('admin.stats.csv');
    });

    Route::middleware('role:hq_admin,hq_staff')->group(function () {
        Route::get('/admin/pages', [PageController::class, 'index'])->name('admin.pages');
        Route::get('/admin/pages/{page}', [PageController::class, 'show'])->name('admin.pages.show');
        Route::get('/admin/pages/{page}/blocks/{block}', [PageController::class, 'edit'])->name('admin.pages.block');
        Route::put('/admin/pages/{page}/blocks/{block}', [PageController::class, 'update'])->name('admin.pages.block.update');
        Route::put('/admin/pages/{page}/blocks/{block}/text', [PageController::class, 'updateText'])->name('admin.pages.block.text');
        Route::post('/admin/pages/{page}/blocks/{block}/undo', [PageController::class, 'undo'])->name('admin.pages.block.undo');
        Route::get('/admin/search', [PageController::class, 'search'])->name('admin.pages.search');
        Route::get('/admin/pages/{page}/blocks/{block}/files', [\App\Http\Controllers\Admin\PageFileController::class, 'index'])->name('admin.pages.block.files');
        Route::put('/admin/pages/{page}/blocks/{block}/files', [\App\Http\Controllers\Admin\PageFileController::class, 'update'])->name('admin.pages.block.files.update');
        Route::post('/admin/pages/{page}/blocks/{block}/files/remove', [\App\Http\Controllers\Admin\PageFileController::class, 'remove'])->name('admin.pages.block.files.remove');
        Route::delete('/admin/pages/{page}/blocks/{block}/files', [\App\Http\Controllers\Admin\PageFileController::class, 'clear'])->name('admin.pages.block.files.clear');
        Route::post('/admin/pages/{page}/blocks/{block}/files/restore', [\App\Http\Controllers\Admin\PageFileController::class, 'restore'])->name('admin.pages.block.files.restore');
        // Upload Full Hachayols (its own page)
        Route::get('/admin/hachayols', [\App\Http\Controllers\Admin\HachayolImportController::class, 'index'])->name('admin.hachayols');
        Route::post('/admin/hachayols', [\App\Http\Controllers\Admin\HachayolImportController::class, 'analyze'])->name('admin.hachayols.analyze');
        Route::get('/admin/hachayols/{batch}', [\App\Http\Controllers\Admin\HachayolImportController::class, 'preview'])->name('admin.hachayols.preview');
        Route::get('/admin/hachayols/{batch}/{id}/{page}.jpg', [\App\Http\Controllers\Admin\HachayolImportController::class, 'thumb'])->whereNumber('page')->name('admin.hachayols.thumb');
        Route::post('/admin/hachayols/{batch}/publish', [\App\Http\Controllers\Admin\HachayolImportController::class, 'publish'])->name('admin.hachayols.publish');
        Route::delete('/admin/hachayols/{batch}', [\App\Http\Controllers\Admin\HachayolImportController::class, 'discard'])->name('admin.hachayols.discard');
    });

    Route::middleware('role:hq_admin,hq_staff,teacher,base')->group(function () {
        Route::get('/admin/site-switcher', [SiteController::class, 'search'])->name('admin.sites.search');
        Route::post('/admin/site-switcher', [SiteController::class, 'switch'])->name('admin.sites.switch');
        Route::get('/admin/uploads', [UploadController::class, 'index'])->name('admin.uploads');
        Route::post('/admin/uploads', [UploadController::class, 'store'])->name('admin.uploads.store');
        Route::get('/admin/uploads/{upload}/edit', [UploadController::class, 'edit'])->whereNumber('upload')->name('admin.uploads.edit');
        Route::put('/admin/uploads/{upload}', [UploadController::class, 'update'])->whereNumber('upload')->name('admin.uploads.update');
        Route::delete('/admin/uploads/{upload}', [UploadController::class, 'destroy'])->whereNumber('upload')->name('admin.uploads.destroy');
        Route::post('/admin/uploads/{upload}/restore', [UploadController::class, 'restore'])->withTrashed()->whereNumber('upload')->name('admin.uploads.restore');
        Route::post('/admin/uploads/{upload}/approve', [UploadController::class, 'approve'])->whereNumber('upload')->name('admin.uploads.approve');
        Route::post('/admin/uploads/{upload}/reject', [UploadController::class, 'reject'])->whereNumber('upload')->name('admin.uploads.reject');
        Route::get('/admin/uploads/check-url', [UploadController::class, 'checkUrl'])->name('admin.uploads.check-url');
        Route::get('/admin/uploads/hebrew-date', [UploadController::class, 'hebrew'])->name('admin.uploads.hebrew');
        Route::get('/admin/uploads/site', [UploadController::class, 'siteParts'])->name('admin.uploads.site');
        Route::get('/admin/uploads/site/{key}', [UploadController::class, 'sitePart'])->name('admin.uploads.site.part');
    });
    });
});

// Read-only page content in the same shape as the site's src/data/pages/<slug>.json
// Not public: signed-in HQ admin / HQ staff, or the live site's server with RESOURCES_API_TOKEN
Route::middleware(\App\Http\Middleware\ApiPageAccess::class)->group(function () {
    Route::get('/api/pages/{page}', [ApiPageController::class, 'show'])->name('api.pages.show'); // Resources site (original address)
    Route::get('/api/sites/{site}/pages/{page}', [ApiPageController::class, 'showForSite'])->name('api.sites.pages.show');
    // The public site, through Mashpia's siteApi.php (the token plus who is signed in)
    Route::get('/api/content/pages/{pageId}', [ApiContentController::class, 'page'])->where('pageId', '[^/]+')->name('api.content.page');
    Route::get('/api/content/search', [ApiContentController::class, 'search'])->name('api.content.search');
    Route::get('/api/content/files/{upload}', [ApiContentController::class, 'file'])->whereNumber('upload')->name('api.content.file');
    Route::get('/api/content/bar', [ApiContentController::class, 'bar'])->name('api.content.bar');
});

// Uploaded files, at the address the app gives them (signed in + allowed level + inside its dates)
Route::get('/files/{id}/{name}', [PublicFileController::class, 'file'])->whereNumber('id')->where('name', '.+')->name('files.show');

// The site's own small pictures (badges etc.) that page text points to as /resources/assets/img/...,
// so they show in the editor preview. Exact copies of the live site's files, kept in public/site-preview/img.
Route::get('/resources/assets/img/{file}', [PublicFileController::class, 'sitePicture'])->where('file', '[0-9a-f]{16}\\.(png|jpe?g|gif|svg|webp)')->name('site.picture');

// Older uploads: any path not matched above, e.g. /resources/guides/file.pdf
Route::fallback([PublicFileController::class, 'show']);
