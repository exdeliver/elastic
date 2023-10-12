<?php

namespace Exdeliver\Elastic;

use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

class ElasticServiceProvider extends ServiceProvider
{
    /**
     * Perform post-registration booting of services.
     */
    public function boot(): void
    {
        // Publishing is only necessary when using the CLI.
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    /**
     * Register any package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/elastic.php', 'elastic');

        // Register the service the package provides.
//        $this->app->singleton('backpack-maps', static function ($app) {
//            return new BackpackMaps();
//        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return ['elastic'];
    }

    /**
     * Console-specific booting.
     */
    protected function bootForConsole(): void
    {
        // Publishing the configuration file.
        $this->publishes([
            __DIR__ . '/../config/elastic.php' => config_path('elastic.php'),
        ], 'elastic.config');

        // Publishing the views.
        /*$this->publishes([
            __DIR__.'/../resources/views' => base_path('resources/views/vendor/exdeliver'),
        ], 'backpack-maps.views');*/

        // Publishing assets.
        /*$this->publishes([
            __DIR__.'/../resources/assets' => public_path('vendor/exdeliver'),
        ], 'backpack-maps.views');*/

        // Publishing the translation files.
        /*$this->publishes([
            __DIR__.'/../resources/lang' => resource_path('lang/vendor/exdeliver'),
        ], 'backpack-maps.views');*/

        // Registering package commands.
        // $this->commands([]);
    }
}
