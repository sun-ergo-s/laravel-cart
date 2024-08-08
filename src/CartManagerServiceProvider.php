<?php

namespace SunErgoS\LaravelCart;

use Illuminate\Support\ServiceProvider;
use SunErgoS\LaravelCart\Cart;
use Illuminate\Contracts\Http\Kernel;
use SunErgoS\LaravelCart\Http\Middleware\HandleCartSession;
use Illuminate\Support\Facades\Config;

class CartManagerServiceProvider extends ServiceProvider {

    /**
     * Bootstrap any package services.
     *
     * @return void
     */
    public function boot()
    {
        // $this->app->make(Kernel::class)->pushMiddleware(HandleCartSession::class);
    }

    public function register(): void
    {

        $this->mergeConfigFrom(
            __DIR__.'/../config/database_connections.php', 'database.connections'
        );

        $this->app->singleton('cartManager', function() {
            return new CartManager();
        });

    }

}