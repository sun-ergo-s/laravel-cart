<?php

namespace SunErgoS\LaravelCart\Listeners;

use Illuminate\Auth\Events\Login;
use SunErgoS\LaravelCart\Facades\CartManager;
use App\Facades\PriceService;
use App\Helpers;

class UpdateCartOnLogin {

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        // ...
    }

    /**
     * Handle the event.
     */
    public function handle(Login $event): void
    {

        // merge cart items
        // merge informations
        // a ak existuje teda cart zo session, vymaže aj cart a informácie s tým spojené - dorobiť
        CartManager::mergeCartItemsFromPreviousSession(session("cart_session_id"));
        CartManager::deleteSessionCart(session("cart_session_id"));
        
        foreach(CartManager::getCartItems() as $cartItem){

            CartManager::updatePriceOnCartItem($cartItem, Helpers::uplatnitKuponNaCenu($cartItem, PriceService::getProductPrice($cartItem->product)["final_price"]) );

        }

    }

}