<?php

declare(strict_types=1);

use Duxbo\Seo\Http\Controllers\PanelController;
use Duxbo\Seo\Http\Controllers\Panel\ContentController;
use Duxbo\Seo\Http\Controllers\Panel\DashboardController;
use Duxbo\Seo\Http\Controllers\Panel\DynamicSettingsController;
use Duxbo\Seo\Http\Controllers\Panel\NotFoundMonitorController;
use Duxbo\Seo\Http\Controllers\Panel\RedirectsController;
use Duxbo\Seo\Http\Controllers\Panel\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Blade admin panel
|--------------------------------------------------------------------------
|
| Disabled by default, same as the REST API — this surface writes site-wide
| metadata. Session and CSRF under the `web` middleware group, not the token
| auth the REST API expects: a same-origin admin page already has both, and
| routing its own calls through bearer tokens would mean standing up Sanctum
| just for this page.
|
| Fixed-segment routes (dashboard, content, redirects, not-found, settings)
| are registered before the {type}/{id} catch-all below. They cannot collide
| in practice — {type}/{id} always needs two segments, so /redirects and
| /content never match it regardless of order — but the order still reads as
| "the shell's own pages, then the per-record editor".
|
*/

Route::prefix(config('seo.panel.prefix', 'seo/panel'))
    ->middleware(config('seo.panel.middleware', ['web', 'can:viewSeoPanel']))
    ->name('seo.panel.')
    ->group(static function (): void {
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('content', ContentController::class)->name('content');

        Route::prefix('redirects')->name('redirects.')->group(static function (): void {
            Route::get('/', [RedirectsController::class, 'index'])->name('index');
            Route::post('/', [RedirectsController::class, 'store'])->name('store');
            Route::patch('{id}/toggle', [RedirectsController::class, 'toggle'])->whereNumber('id')->name('toggle');
            Route::delete('{id}', [RedirectsController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        Route::prefix('not-found')->name('not-found.')->group(static function (): void {
            Route::get('/', [NotFoundMonitorController::class, 'index'])->name('index');
            Route::post('prune', [NotFoundMonitorController::class, 'prune'])->name('prune');
            Route::post('{id}/redirect', [NotFoundMonitorController::class, 'redirect'])->whereNumber('id')->name('redirect');
            Route::delete('{id}', [NotFoundMonitorController::class, 'destroy'])->whereNumber('id')->name('destroy');
        });

        Route::get('settings', SettingsController::class)->name('settings');
        Route::put('settings', [DynamicSettingsController::class, 'update'])->name('settings.update');

        Route::get('{type}/{id}', [PanelController::class, 'show'])->name('show');
        Route::get('{type}/{id}/data', [PanelController::class, 'data'])->name('data');
        Route::put('{type}/{id}/data', [PanelController::class, 'update'])->name('update');

        // Real CPU work per request with no cost control the way the AI
        // budget has one — a buggy or malicious authenticated client could
        // otherwise hammer it.
        Route::post('{type}/{id}/analyze', [PanelController::class, 'analyze'])
            ->middleware('throttle:'.config('seo.analysis.rate_limit', '30,1'))
            ->name('analyze');
    });
