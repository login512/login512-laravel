<?php

declare(strict_types=1);

namespace Login512\Laravel;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Login512\Laravel\Http\Middleware\VerifyLogin512Token;
use Login512\Login512;

class Login512ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/login512.php', 'login512');

        $this->app->singleton(Login512Client::class, function ($app) {
            $config = $app['config'];
            return new Login512Client(
                (string) $config->get('login512.secret_key', ''),
                (string) ($config->get('login512.endpoint') ?: Login512::DEFAULT_ENDPOINT)
            );
        });
        $this->app->alias(Login512Client::class, 'login512');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/login512.php' => config_path('login512.php'),
            ], 'login512-config');
        }

        $this->app['router']->aliasMiddleware('login512.verified', VerifyLogin512Token::class);

        // Blade: @login512Scripts in <head>, then @login512Button or @login512Button('face').
        // The button fires a DOM event 'l512:success' with event.detail.token; POST that token to your controller.
        Blade::directive('login512Scripts', function () {
            return '<script src="https://login512.com/sdk/login512-sdk.js"></script>' .
                   '<link rel="stylesheet" href="https://login512.com/sdk/login512-sdk.css">';
        });

        Blade::directive('login512Button', function ($expression) {
            $mode = trim((string) $expression) !== '' ? $expression : "'auto'";
            return '<login512-button publishable-key="<?php echo e(config(\'login512.publishable_key\')); ?>"'
                . ' endpoint="<?php echo e(config(\'login512.endpoint\')); ?>"'
                . ' mode="<?php echo e(' . $mode . '); ?>"></login512-button>';
        });
    }
}
