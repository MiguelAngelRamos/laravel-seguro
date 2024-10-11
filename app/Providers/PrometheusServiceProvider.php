<?php

namespace App\Providers;

use Prometheus\Storage\Redis as PrometheusRedis; // Importa la clase Redis de Prometheus
use Prometheus\CollectorRegistry;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Redis as RedisFacade;

class PrometheusServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton(CollectorRegistry::class, function () {
            // Aquí configuramos la conexión a Redis para Prometheus
            $storage = new PrometheusRedis([
                'host' => '127.0.0.1',
                'port' => 6379,
                'timeout' => 0.1
            ]);

            return new CollectorRegistry($storage);
        });
    }
}
