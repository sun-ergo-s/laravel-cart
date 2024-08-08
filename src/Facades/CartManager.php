<?php

namespace SunErgoS\LaravelCart\Facades;

use Illuminate\Support\Facades\Facade;

class CartManager extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cartManager';
    }
}