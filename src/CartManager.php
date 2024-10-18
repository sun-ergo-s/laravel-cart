<?php

namespace SunErgoS\LaravelCart;

use App\Helpers;
use App\Models\CartInformation;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use SunErgoS\LaravelCart\Models\Cart;
use SunErgoS\LaravelCart\Models\CartItem;

class CartManager {

    /**
     * 36 long UUID string
     */
    public $cartId;

    public function __construct(){

        self::getCartId();

    }
    
    public function addOrUpdateItem($id, $price, $quantity){

        self::ensureCartExists();

        $cartItem = self::getCartItem($id);

        if(!$cartItem){

            $cartItem = new CartItem;

            $cartItem->cart_id = $this->cartId;
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

    public function cartPrice(){

        $items = self::getCartItems();

        if (count($items) > 0) {

            if (Auth::check()){
                if(Auth::user()->isResaler()){

                    $na_komis = 0;
                    $na_fakturu = 0;

                    foreach ($items as $item) {

                        if( ($item->products[0]->nedavat_na_komis == 0) && $item->products[0]->cenova_kategoria == "A" || $item->products[0]->cenova_kategoria == "B" ){

                            $na_komis += $item->price * $item->quantity;

                        }
                        else {

                            $na_fakturu += $item->price * $item->quantity;
                        }

                    }


                    return $komis_faktura = [
                        'komis' => $na_komis,
                        'faktura' => $na_fakturu,
                    ];

                }
                else {

                    $cart_item_price = 0;

                    foreach ($items as $item) {

                        $cart_item_price += Helpers::uplatnitKuponNaCenu($item, $item->price) * $item->quantity;

                    }

                    return $cart_item_price;
                }
            }
            else {

                $cart_item_price = 0;

                foreach ($items as $item) {
                    $cart_item_price += Helpers::uplatnitKuponNaCenu($item, $item->price) * $item->quantity;
                }

                return $cart_item_price;
            }

            

        }else {
            return 0;
        }

    }

    public function getCartItems(){

        $items = CartItem::with('products', 'product')->where('cart_id', $this->cartId)->get();

        return $items;

    }

    public function getCartItem($id){

        return CartItem::with('product', 'product.discounts')->where([
            ['cart_id', $this->cartId],
            ['product_id', $id]
        ])->first();

    }

    public function deleteItem($id){

        $cartItem = CartItem::find($id);
        $cartItem->delete();

    }

    public function getCart(){

        return Cart::with('informations', 'informations.deliveryPoint', 'vouchers', 'cartItem')->find($this->cartId);

    }

    public function updatePriceOnCartItem($cartItem, $price){

        $cartItem->price = $price;
        $cartItem->save();

    }

    public function mergeCartItems($previousSessionCartItems){

        foreach($previousSessionCartItems as $previousItem){

            $cartItem = CartItem::with('product')->where([
                ['cart_id', $this->cartId],
                ['product_id', $previousItem->product_id]
            ])->first();

            // ak už existuje tovar v košíku, do ktorého účtu sa prihlasujem
            // tak potom len aktualizuje stav, a vymaže tovar priradený
            // k session košíku
            if ($cartItem) {
                
                $totalQuantity = $cartItem->quantity + $previousItem->quantity;
                $cartItem->quantity = $totalQuantity > $cartItem->product->pocet_na_sklade ? $cartItem->product->pocet_na_sklade : $totalQuantity;
                $cartItem->save();

                $previousItem->delete();
                
            }else {

                $previousItem->cart_id = $this->cartId;
                $previousItem->save();

            }

        }

    }

    public function mergeCartInformation($sessionCartInformation, $userCartInformation){

        if(!$sessionCartInformation->is($userCartInformation) && $userCartInformation != null){
            $userCartInformation->delete();
        }

        $sessionCartInformation->cart_id = $this->cartId;
        $sessionCartInformation->save();

        self::saveUserDataToCart($sessionCartInformation);

    }

    public function getCartId(){

        $userId = Auth::id() ?: false;
        $sessionId = Session::get('cart_session_id');
        $cart_for_user = Cart::with('informations', 'cartItem')->where('user_id', $userId)->first();
        $cart_for_session = Cart::with('informations', 'cartItem')->where('id', $sessionId)->first();

        if($cart_for_user){

            return $this->cartId = $cart_for_user->id;

        }

        if($cart_for_session){

            return $this->cartId = $cart_for_session->id;

        }

        return false;
        
    }

    public function mergeCartItemsFromPreviousSession($previousSessionId){

        $cart_for_session = Cart::with('informations', 'cartItem')->where('id', $previousSessionId)->first();

        if(isset($cart_for_session) && count($cart_for_session->cartItem)){
            self::mergeCartItems($cart_for_session->cartItem);
        }

    }

    public function deleteSessionCart($previousSessionId){

        return Cart::where('id', $previousSessionId)->delete();

    }

    public function addUserIdToCartInstance($userId){

        $cart = self::getCart();

        if($cart){
            $cart->user_id = $userId;
            $cart->save();
        }

    }

    public function updateCartInformation(){
        return;
    }

    public function ensureCartExists(){

        if(!$this->cartId){

            $sessionId = Str::uuid();
            Session::put('cart_session_id', $sessionId);

            Cart::create([
                'id' => Session::get('cart_session_id'), 
                'user_id' => Auth::id() ?: NULL
            ]);

            $cart_informations = new CartInformation;
            $cart_informations->cart_id = $sessionId;
            $cart_informations->deliveryCountry = 1;
            $cart_informations->invoiceCountry = 1;
            $cart_informations->save();

            self::saveUserDataToCart($cart_informations);

            $cart = Cart::with('informations')->find($sessionId);

            $this->cartId = $cart->id;
            return $cart->id;

        }else {
            return $this->cartId;
        }

    }

    // pri odoslaní objednávky
    // keď sa odstráni posledný tovar z košíka
    // dorobiť odstránenie aj vecí súvisiacích s tým
    public function destroyCart(){

        $cart = Cart::with("cartItem", "informations")->find($this->cartId);

        if($cart){

            if($cart->informations){
                $cart->informations->delete();
            }

            foreach($cart->cartItem as $item){
				$item->delete();
			}

            $cart->delete();
        }

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

    // DOČASNE
    public function saveUserDataToCart($ci){

		$user = Auth::user();

		if($user){

			// ak nie je zadaná doručovacia adresa -> vyplním len "delivery"
			if(!$user->mailing_address_active){

				$ci->deliveryCountry = $ci->deliveryCountry ? $ci->deliveryCountry : $user->country_id;

				if(!$ci->deliveryCountry){
					$ci->deliveryCountry = 1;
				}

				$ci->deliveryName = $ci->deliveryName ? $ci->deliveryName : $user->name;
				$ci->deliverySurname = $ci->deliverySurname ? $ci->deliverySurname : $user->surname;

				if($user->company_active && $user->company != ""){

					$ci->deliveryCompany = 1;
					$ci->deliveryCompanyname = $user->company;
					
				}

				$ci->deliveryStreet = $ci->deliveryStreet ? $ci->deliveryStreet : ($user->street . " " . $user->street_number);
				$ci->deliveryPsc = $ci->deliveryPsc ? $ci->deliveryPsc : $user->psc;
				$ci->deliveryCity = $ci->deliveryCity ? $ci->deliveryCity : $user->city;

			}else {

				// Doručovacia
				$ci->deliveryCountry = $ci->deliveryCountry ? $ci->deliveryCountry : $user->mailing_country_id;

				if(!$ci->deliveryCountry){
					$ci->deliveryCountry = 1;
				}

				$ci->deliveryName = $ci->deliveryName ? $ci->deliveryName : $user->mailing_name;
				$ci->deliverySurname = $ci->deliverySurname ? $ci->deliverySurname : $user->mailing_surname;

				if($user->mailing_company_active && $user->mailing_company != ""){
					$ci->deliveryCompany = 1;
					$ci->deliveryCompanyname = $user->mailing_company;
				}

				$ci->deliveryStreet = $ci->deliveryStreet ? $ci->deliveryStreet : ($user->mailing_street . " " . $user->mailing_street_number);
				$ci->deliveryPsc = $ci->deliveryPsc ? $ci->deliveryPsc : $user->mailing_psc;
				$ci->deliveryCity = $ci->deliveryCity ? $ci->deliveryCity : $user->mailing_city;

				// Invoice
				$ci->invoiceAddress = 1;
				$ci->invoiceCountry = $ci->invoiceCountry ? $ci->invoiceCountry : $user->country_id;
				$ci->invoiceName = $ci->invoiceName ? $ci->invoiceName : $user->name;
				$ci->invoiceSurname = $ci->invoiceSurname ? $ci->invoiceSurname : $user->surname;

				if($user->company_active && $user->company != ""){
					$ci->invoiceCompany = 1;
					$ci->invoiceCompanyname = $user->company;
				}

				$ci->invoiceStreet = $ci->invoiceStreet ? $ci->invoiceStreet : ($user->street . " " . $user->street_number);
				$ci->invoicePsc = $ci->invoicePsc ? $ci->invoicePsc : $user->psc;
				$ci->invoiceCity = $ci->invoiceCity ? $ci->invoiceCity : $user->city;

				$ci->invoiceIco = $ci->invoiceIco ? $ci->invoiceIco : $user->ico;
				$ci->invoiceDic = $ci->invoiceDic ? $ci->invoiceDic : $user->dic;
				$ci->invoiceIcdph = $ci->invoiceIcdph ? $ci->invoiceIcdph : $user->ic_dph;

			}

			$ci->deliveryEmail = $ci->deliveryEmail ? $ci->deliveryEmail : $user->email;
			$ci->deliveryTel = $ci->deliveryTel ? $ci->deliveryTel : $user->phone;

			return $ci->save();

		}

	}


}