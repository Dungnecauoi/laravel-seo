<?php

declare(strict_types=1);

namespace Duxbo\Seo\Tests\Feature;

use Duxbo\Seo\Tests\TestCase;

/**
 * With no ingest_token configured, the route must not exist at all — a 404,
 * not a 401 — so a project that never opts in exposes no write surface for
 * an unauthenticated caller to even discover.
 */
final class NotFoundIngestDisabledTest extends TestCase
{
    public function test_the_ingest_route_does_not_exist_when_no_token_is_configured(): void
    {
        $this->postJson('/api/seo/v1/not-found', ['path' => '/tu-nextjs'])
            ->assertStatus(404);
    }
}
