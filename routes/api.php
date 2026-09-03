<?php

declare(strict_types=1);

use Duxbo\Seo\Http\Api\V1\AnalyzeController;
use Duxbo\Seo\Http\Api\V1\ContentController;
use Duxbo\Seo\Http\Api\V1\DashboardController;
use Duxbo\Seo\Http\Api\V1\MetaController;
use Duxbo\Seo\Http\Api\V1\NotFoundController;
use Duxbo\Seo\Http\Api\V1\RedirectsController;
use Duxbo\Seo\Http\Api\V1\ResolveController;
use Duxbo\Seo\Http\Api\V1\SettingsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SEO API v1
|--------------------------------------------------------------------------
|
| Disabled by default. This surface writes site-wide metadata, so it is opted
| into rather than exposed by installing the package.
|
| The version in the path is the API's own and does not track the package
| version: a SPA deploys on its own schedule, and v1 keeps its shape across
| package majors.
|
*/

Route::prefix(config('seo.api.prefix', 'api/seo/v1'))
    ->middleware(config('seo.api.middleware', ['api', 'can:viewSeoPanel']))
    ->group(static function (): void {
        Route::get('resolve', ResolveController::class);

        // Real CPU work per request with no cost control the way the AI
        // budget has one — a buggy or malicious authenticated client could
        // otherwise hammer it.
        Route::post('analyze', AnalyzeController::class)
            ->middleware('throttle:'.config('seo.analysis.rate_limit', '30,1'));

        Route::get('meta/{type}/{id}', [MetaController::class, 'show']);
        Route::put('meta/{type}/{id}', [MetaController::class, 'update']);
        Route::delete('meta/{type}/{id}', [MetaController::class, 'destroy']);

        Route::get('not-found', [NotFoundController::class, 'index']);
        Route::delete('not-found/{id}', [NotFoundController::class, 'destroy']);
        Route::post('not-found/prune', [NotFoundController::class, 'prune']);
        Route::post('not-found/{id}/redirect', [NotFoundController::class, 'redirect']);

        // The admin-shell surfaces behind the same Gate: a dashboard, a
        // content list, and redirect CRUD — the JSON twin of the Blade
        // panel's own routes, for a React/Vue front end instead.
        Route::get('dashboard', DashboardController::class);
        Route::get('content', ContentController::class);
        Route::get('settings', SettingsController::class);

        Route::prefix('redirects')->group(static function (): void {
            Route::get('/', [RedirectsController::class, 'index']);
            Route::post('/', [RedirectsController::class, 'store']);
            Route::patch('{id}/toggle', [RedirectsController::class, 'toggle'])->whereNumber('id');
            Route::delete('{id}', [RedirectsController::class, 'destroy'])->whereNumber('id');
        });
    });
