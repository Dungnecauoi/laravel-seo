<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Api\V1;

use Duxbo\Seo\Data\NotFoundHit;
use Duxbo\Seo\NotFound\NotFoundLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Lets a separately-deployed front end (a Next.js app with its own router,
 * never proxied through this application) report its own 404s — the only
 * way {@see NotFoundLogger} ever sees real traffic when this Laravel
 * install serves nothing but an API. {@see \Duxbo\Seo\Http\Middleware\HandleNotFound}
 * covers the opposite case: a 404 that actually flowed through *this*
 * application's own router.
 *
 * Uses `Validator::make()->fails()` rather than `$request->validate()` on
 * purpose, unlike most controllers in this package: a validation failure
 * thrown as `ValidationException` only renders as JSON when the request
 * "expects JSON" — a headless server-to-server caller has no reason to set
 * that, and this route carries no session to flash errors into either.
 * Building the response directly sidesteps that regardless of what the
 * caller sends.
 */
final class NotFoundIngestController extends ApiController
{
    public function store(Request $request, NotFoundLogger $logger): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'path' => ['required', 'string', 'max:2048', 'starts_with:/'],
            'referrer' => ['nullable', 'string', 'max:2048'],
            'userAgent' => ['nullable', 'string', 'max:2048'],
        ]);

        if ($validator->fails()) {
            return $this->json(['message' => 'The given data was invalid.', 'errors' => $validator->errors()], 422);
        }

        /** @var array{path: string, referrer?: ?string, userAgent?: ?string} $validated */
        $validated = $validator->validated();

        $logger->log(new NotFoundHit(
            path: $validated['path'],
            referrer: $validated['referrer'] ?? null,
            userAgent: $validated['userAgent'] ?? null,
        ));

        // 202, not 201: sampling/exclude filtering may silently drop this
        // hit inside NotFoundLogger, so "accepted" is the honest claim, not
        // "created."
        return $this->json(['accepted' => true], 202);
    }
}
