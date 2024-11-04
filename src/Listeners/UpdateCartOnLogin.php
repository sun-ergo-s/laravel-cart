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
     * Po úspešnej autorizácii
     */
    public function handle(Login $event): void
    {

        // pridá user_id hodnotu
        CartManager::addUserIdToCartInstance($event->user->id);

        // ak existuje košík pred prihlásením
        if(session("cart_session_id")){
            CartManager::mergeCartItemsFromPreviousSession(session("cart_session_id"), $event->user->id);
            CartManager::mergeCartInformation(session("cart_session_id"), $event->user->id);
            // CartManager::deleteSessionCart(session("cart_session_id"), $event->user->id);
        }        
        
        foreach(CartManager::getCartItems() as $cartItem){
            CartManager::updatePriceOnCartItem($cartItem, Helpers::uplatnitKuponNaCenu($cartItem, PriceService::getProductPrice($cartItem->product)["final_price"]) );
        }

    }

}