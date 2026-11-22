<?php

namespace App\Providers;

use App\Console\Commands\ExamenSetup;
use App\Listeners\OrderPlacedListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class ExamenServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('examen.php'), 'examen');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(resource_path('views/examen'), 'examen');

        if ($this->app->runningInConsole()) {
            $this->commands([ExamenSetup::class]);
        }

        // 1 y 2. Nueva compra -> WhatsApp Business + cupones automaticos.
        Event::listen('checkout.order.save.after', [OrderPlacedListener::class, 'handle']);

        // 3. Chat en vivo Tawk.to en todas las paginas de la tienda.
        Event::listen('bagisto.shop.layout.body.after', function ($viewRenderEventManager) {
            $viewRenderEventManager->addTemplate('examen::tawk');
        });
    }
}
