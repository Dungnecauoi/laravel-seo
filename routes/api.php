<?php

declare(strict_types=1);

use Duxbo\Seo\Http\Api\V1\AnalyzeController;
use Duxbo\Seo\Http\Api\V1\MetaController;
use Duxbo\Seo\Http\Api\V1\NotFoundController;
use Duxbo\Seo\Http\Api\V1\ResolveController;
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
    });
