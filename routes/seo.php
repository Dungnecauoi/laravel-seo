<?php

declare(strict_types=1);

use Duxbo\Seo\Http\Controllers\IndexNowKeyController;
use Duxbo\Seo\Http\Controllers\RobotsController;
use Duxbo\Seo\Http\Controllers\SitemapController;
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
