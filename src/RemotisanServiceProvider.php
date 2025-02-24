<?php

namespace PayMe\Remotisan;

use Illuminate\Support\ServiceProvider;
use PayMe\Remotisan\Console\Commands\ProcessBrokerCommand;

class RemotisanServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application events.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/remotisan.php' => config_path('remotisan.php'),
        ], 'remotisan-config');

        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/remotisan'),
        ], 'remotisan-views');

        $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'remotisan');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->commands([ProcessBrokerCommand::class]);
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/remotisan.php',
            'remotisan'
        );

        $this->app->singleton(Remotisan::class, function ($app) {
            return new Remotisan(new CommandsRepository(), new ProcessExecutor());
        });
    }
}
