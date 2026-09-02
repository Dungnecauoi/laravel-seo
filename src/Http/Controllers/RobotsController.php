<?php

declare(strict_types=1);

namespace Duxbo\Seo\Http\Controllers;

use Duxbo\Seo\Robots\RobotsTxt;
use Illuminate\Http\Response;

final class RobotsController
{
    public function __construct(private readonly RobotsTxt $robots)
    {
    }

    public function __invoke(): Response
    {
        return new Response($this->robots->render(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
