<?php

declare(strict_types=1);

use Duxbo\Seo\Http\Api\V1\NotFoundIngestController;
use Duxbo\Seo\Http\Controllers\IndexNowKeyController;
use Duxbo\Seo\Http\Controllers\RobotsController;
use Duxbo\Seo\Http\Controllers\SitemapController;
use Duxbo\Seo\Http\Middleware\VerifyNotFoundIngestToken;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public SEO routes
|--------------------------------------------------------------------------
|
| Registered only when enabled in config. A project already serving a static
| public/robots.txt or public/sitemap.xml should turn the matching route off
| rather than have two sources of truth.
|
*/

if (config('seo.sitemap.enabled', true) === true) {
    Route::get('sitemap.xml', [SitemapController::class, 'index'])
        ->name('seo.sitemap.index');

    // sitemap-posts.xml, and sitemap-posts-2.xml for the overflow.
    Route::get('sitemap-{name}-{part}.xml', [SitemapController::class, 'source'])
        ->where('name', '[A-Za-z0-9_-]+')
        ->where('part', '[0-9]+')
        ->name('seo.sitemap.part');

    Route::get('sitemap-{name}.xml', [SitemapController::class, 'source'])
        ->where('name', '[A-Za-z0-9_-]+')
        ->name('seo.sitemap.source');
}

if (config('seo.robots.enabled', true) === true) {
    Route::get('robots.txt', RobotsController::class)->name('seo.robots');
}

$indexNowKey = config('seo.indexnow.key');

if (config('seo.indexnow.enabled', false) === true && is_string($indexNowKey) && $indexNowKey !== '') {
    // The literal key, not a {key} wildcard — IndexNow only ever checks the
    // one file it named in the submission, and a wildcard here would swallow
    // any other *.txt route the host application registers.
    Route::get($indexNowKey.'.txt', IndexNowKeyController::class)->name('seo.indexnow.key');
}

$notFoundIngestToken = config('seo.not_found.ingest_token');

// Independent of seo.api.enabled on purpose: a project may want 404
// ingestion from a decoupled front end (a Next.js app with its own
// router, never proxied through this application) without opening the
// rest of the admin JSON API. Same prefix that API already uses, but
// behind its own bare-token middleware instead of the viewSeoPanel Gate —
// the caller here is a server with no Laravel session to gate on.
if (is_string($notFoundIngestToken) && $notFoundIngestToken !== '') {
    Route::prefix(config('seo.api.prefix', 'api/seo/v1'))->group(static function (): void {
        Route::post('not-found', [NotFoundIngestController::class, 'store'])
            // Token check first, throttle second: an anonymous caller with
            // no (or the wrong) token is refused before it ever consumes a
            // slot in the per-IP throttle bucket — otherwise anyone
            // sharing a NAT/proxy IP with the legitimate front end's
            // backend (or simply spamming 401s from that IP) could burn
            // through the real caller's own budget for the window.
            ->middleware([VerifyNotFoundIngestToken::class, 'throttle:120,1'])
            ->name('seo.not-found.ingest');
    });
}
