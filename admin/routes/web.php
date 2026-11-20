<?php

use App\Http\Controllers\Admin\PageController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SiteController;
use App\Http\Controllers\Api\PageController as ApiPageController;
use App\Http\Controllers\Admin\UploadController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PublicFileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
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
    Route::view('/dashboard', 'dashboard')->name('dashboard');
    Route::get('/account', [\App\Http\Controllers\AccountController::class, 'show'])->name('account');
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::middleware('hq')->group(function () {
        Route::get('/admin/roles', [RoleController::class, 'index'])->name('admin.roles');
        Route::put('/admin/roles/{user}', [RoleController::class, 'update'])->name('admin.roles.update');
        Route::get('/admin/sites', [SiteController::class, 'index'])->name('admin.sites');
        Route::post('/admin/sites', [SiteController::class, 'store'])->name('admin.sites.store');
        Route::get('/admin/sites/{site}/edit', [SiteController::class, 'edit'])->name('admin.sites.edit');
        Route::put('/admin/sites/{site}', [SiteController::class, 'update'])->name('admin.sites.update');
        Route::get('/admin/login-settings', fn () => view('admin.login-settings', ['mode' => app(\App\Services\MashpiaAuthService::class)->mode()]))->name('admin.login-settings');
    });

    Route::middleware('role:hq_admin,hq_staff')->group(function () {
        Route::get('/admin/pages', [PageController::class, 'index'])->name('admin.pages');
        Route::get('/admin/pages/{page}', [PageController::class, 'show'])->name('admin.pages.show');
        Route::get('/admin/pages/{page}/blocks/{block}', [PageController::class, 'edit'])->name('admin.pages.block');
        Route::put('/admin/pages/{page}/blocks/{block}', [PageController::class, 'update'])->name('admin.pages.block.update');
        Route::put('/admin/pages/{page}/blocks/{block}/text', [PageController::class, 'updateText'])->name('admin.pages.block.text');
        Route::get('/admin/search', [PageController::class, 'search'])->name('admin.pages.search');
    });

    Route::middleware('role:hq_admin,hq_staff,teacher')->group(function () {
        Route::get('/admin/site-switcher', [SiteController::class, 'search'])->name('admin.sites.search');
        Route::post('/admin/site-switcher', [SiteController::class, 'switch'])->name('admin.sites.switch');
        Route::get('/admin/uploads', [UploadController::class, 'index'])->name('admin.uploads');
        Route::post('/admin/uploads', [UploadController::class, 'store'])->name('admin.uploads.store');
        Route::get('/admin/uploads/{upload}/edit', [UploadController::class, 'edit'])->whereNumber('upload')->name('admin.uploads.edit');
        Route::put('/admin/uploads/{upload}', [UploadController::class, 'update'])->whereNumber('upload')->name('admin.uploads.update');
        Route::get('/admin/uploads/check-url', [UploadController::class, 'checkUrl'])->name('admin.uploads.check-url');
        Route::get('/admin/uploads/hebrew-date', [UploadController::class, 'hebrew'])->name('admin.uploads.hebrew');
        Route::get('/admin/uploads/site', [UploadController::class, 'siteParts'])->name('admin.uploads.site');
        Route::get('/admin/uploads/site/{key}', [UploadController::class, 'sitePart'])->name('admin.uploads.site.part');
    });
});

// Read-only page content in the same shape as the site's src/data/pages/<slug>.json
// Not public: signed-in HQ admin / HQ staff, or the live site's server with RESOURCES_API_TOKEN
Route::middleware(\App\Http\Middleware\ApiPageAccess::class)->group(function () {
    Route::get('/api/pages/{page}', [ApiPageController::class, 'show'])->name('api.pages.show'); // Resources site (original address)
    Route::get('/api/sites/{site}/pages/{page}', [ApiPageController::class, 'showForSite'])->name('api.sites.pages.show');
});

// Uploaded files, at the address the app gives them (signed in + allowed level + inside its dates)
Route::get('/files/{id}/{name}', [PublicFileController::class, 'file'])->whereNumber('id')->where('name', '.+')->name('files.show');

// The site's own small pictures (badges etc.) that page text points to as /resources/assets/img/...,
// so they show in the editor preview. Exact copies of the live site's files, kept in public/site-preview/img.
Route::get('/resources/assets/img/{file}', [PublicFileController::class, 'sitePicture'])->where('file', '[0-9a-f]{16}\\.(png|jpe?g|gif|svg|webp)')->name('site.picture');

// Older uploads: any path not matched above, e.g. /resources/guides/file.pdf
Route::fallback([PublicFileController::class, 'show']);