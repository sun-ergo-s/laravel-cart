<?php

namespace SunErgoS\LaravelCart;

use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use SunErgoS\LaravelCart\Models\Cart;
use SunErgoS\LaravelCart\Models\CartItem;
use Illuminate\Support\Str;
use App\Models\CartInformation;
use Illuminate\Support\Facades\Session;

class CartManager {
    
    // addItemToCart()
    // addItemToCartRedirect()
    // addResaleProductsToCart()
    // addToCart()
    public function addOrUpdateItem($id, $price, $quantity){

        $cartItem = self::getCartItem($id);

        if(!$cartItem){

            $cartItem = new CartItem;

            $cartItem->cart_id = self::getCartId();
            $cartItem->product_id = $id;
            $cartItem->price = $price;
            $cartItem->quantity = $quantity;

            $cartItem->save();

        }else {

            /**
             * Ak je súčet aktuálneho množstva produktu a pridávaného množstva produktu väčší, 
             * ako je skutočný počet produktu na skalde
             */
            if ( ($quantity + $cartItem->quantity) > $cartItem->product->pocet_na_sklade) {
                $cartItem->quantity = $cartItem->product->pocet_na_sklade;
                $quantity_limit = true;
            }else {
                $cartItem->quantity += $quantity;
            }

            $cartItem->price = $price;

            $cartItem->save();

        }

        return [
            "item" => $cartItem,
            "same_quantity" => $quantity_limit ?? false
        ];

    }

    public function getCartItems(){

        return CartItem::with('products')->where('cart_id', self::getCartId())->get();

    }

    public function getCartItem($id){

        return CartItem::with('product', 'product.discounts')->where([
            ['cart_id', self::getCartId()],
            ['product_id', $id]
        ])->first();

    }

    public function deleteItem($id){

        $cartItem = CartItem::find($id);
        $cartItem->delete();

    }

    public function getCartId(){

        $userId = Auth::id() ?: false;

        /**
         * Ak existuje košík pre užívateľa
         * id košíka sa použije ako cart_session_id
         * aby sa nevytváral nový košík a aby sa zachoval tovar
         */
        $cart_for_user = Cart::where('user_id', $userId)->first();

        if($cart_for_user){
            Session::put('cart_session_id', $cart_for_user->id);
        }

        /**
         * V tomto prípade už aj cart_session_id, ktoré bolo
         * pre prihláseného užívateľa použité v minulosti
         */
        $sessionId = Session::get('cart_session_id');

        if (!$sessionId) {

            $sessionId = Str::uuid();
            Session::put('cart_session_id', $sessionId);

            if ($userId) {

                $cart = Cart::firstOrCreate([
                    'id' => Session::get('cart_session_id'), 
                    'user_id' => $userId
                ]);

                dd($cart);

            } else {

                $cart = Cart::firstOrCreate([
                    'id' => Session::get('cart_session_id')
                ]);

            }

        }

        $cart = Cart::with('informations')->find(Session::get('cart_session_id'));

        /*
        if(!$cart->informations){
            $cart_informations = new CartInformation;
			$cart_informations->cart_id = $cart->id;
			$cart_informations->deliveryCountry = 1;
			$cart_informations->invoiceCountry = 1;
			$cart_informations->save();
        }
        */

        return $cart->id;
        
    }

    public function addUserToCart($user_id){

        $cart = Cart::where('id', session()->get('cart_session_id'))->first();

        if($cart){
            $cart->user_id = $user_id;
            $cart->save();
        }

    }

    // pri odoslaní objednávky
    // keď sa odstráni posledný tovar z košíka
    // dorobiť odstránenie aj vecí súvisiacích s tým
    public function destroyCart(){

        $cart = Cart::find(self::getCartId());
        $cart->delete();

        session()->forget('cart_session_id');

    }

    public function destroyOldCarts(){

        Cart::where('created_at', '<', Carbon::now()->subDay(30))->delete();

    }

    public function removeOldCarts(){

		/**
		 * Vymaže košíky staršie ako 30 dní
		 */
		$carts = Cart::with('informations', 'cartItem')->where('created_at', '<', Carbon::now()->subDay(30))->get();

		foreach($carts as $cart){

			foreach($cart->cartItem as $item){
				$item->delete();
			}

			if($cart->informations){
				$cart->informations->delete();
			}
			
			$cart->delete();

		}

		/**
		 * Vymaže produkty v košíkoch, ktoré už nemáme na sklade
		 */
		$carts = Cart::with('informations', 'cartItem', 'cartItem.product')->get();

		foreach($carts as $cart){
			foreach($cart->cartItem as $item){
				if($item->product->pocet_na_sklade === 0){
					$item->delete();
				}
			}
		}

		/**
		 * Vymaže košíky, ktoré už nemajú žiadne produkty vzhľadom k vymazaniu produktov, ktoré nie sú na sklade
		 */
		$carts = Cart::with('informations', 'cartItem', 'cartItem.product')->get();

		foreach($carts as $cart){
			if(count($cart->cartItem) === 0){
				if($cart->informations){
					$cart->informations->delete();
				}
				$cart->delete();
			}
		}

		return true;

	}

}