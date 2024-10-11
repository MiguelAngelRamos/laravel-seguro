<?php

namespace App\Http\Controllers;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

class MetricsController extends Controller
{
    public function __invoke()
    {
        $registry = app(CollectorRegistry::class);
        $renderer = new RenderTextFormat();

        return response($renderer->render($registry->getMetricFamilySamples()), 200)
            ->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}
