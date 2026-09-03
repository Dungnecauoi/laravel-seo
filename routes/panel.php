<?php

declare(strict_types=1);

use Duxbo\Seo\Http\Controllers\PanelController;
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
*/

Route::prefix(config('seo.panel.prefix', 'seo/panel'))
    ->middleware(config('seo.panel.middleware', ['web', 'can:viewSeoPanel']))
    ->name('seo.panel.')
    ->group(static function (): void {
        Route::get('{type}/{id}', [PanelController::class, 'show'])->name('show');
        Route::get('{type}/{id}/data', [PanelController::class, 'data'])->name('data');
        Route::put('{type}/{id}/data', [PanelController::class, 'update'])->name('update');
        Route::post('{type}/{id}/analyze', [PanelController::class, 'analyze'])->name('analyze');
    });
