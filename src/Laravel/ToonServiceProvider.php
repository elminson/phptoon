<?php

namespace PhpToon\Laravel;

use Illuminate\Support\ServiceProvider;
use PhpToon\ToonEncoder;
use PhpToon\Support\EncodeOptions;

class ToonServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/toon.php',
            'toon'
        );

        $this->app->singleton('toon', function ($app) {
            $config = $app['config']->get('toon', []);

            $options = new EncodeOptions(
                indent: $config['indent'] ?? '  ',
                delimiter: $config['delimiter'] ?? ',',
                lengthMarker: $config['length_marker'] ?? true
            );

            return new ToonEncoder($options);
        });

        $this->app->alias('toon', ToonEncoder::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/toon.php' => config_path('toon.php'),
            ], 'toon-config');
        }

        // Register response macro
        if (class_exists('\Illuminate\Http\Response')) {
            \Illuminate\Http\Response::macro('toon', function ($data, $status = 200, array $headers = []) {
                $headers['Content-Type'] = 'text/toon; charset=utf-8';

                return response(
                    ToonEncoder::encode($data),
                    $status,
                    $headers
                );
            });
        }

        // Register collection macro
        if (class_exists('\Illuminate\Support\Collection')) {
            \Illuminate\Support\Collection::macro('toToon', function () {
                return ToonEncoder::encode($this->all());
            });
        }
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return ['toon', ToonEncoder::class];
    }
}
