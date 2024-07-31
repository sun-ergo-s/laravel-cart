<?php

namespace SunErgoS\LaravelCart\Facades;

use Illuminate\Support\Facades\Facade;

class Cart extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'cart';
    }
}