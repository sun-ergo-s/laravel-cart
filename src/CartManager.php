<?php

namespace SunErgoS\LaravelCart;

use App\Helpers;
use App\Models\CartInformation;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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

        
    /**
     * Method __construct
     *
     * @return void
     */
    public function __construct(){

        self::getCartId();

    }
        
    /**
     * Pridá/alebo upraví tovar v košíku
     *
     * @param int $id
     * @param float $price
     * @param int $quantity
     *
     * @return void
     */
    public function addOrUpdateItem(int $id, float $price, int $quantity, $finalQuantity = true): array
    {

        self::ensureCartExists();

        $cartItem = self::getCartItem($id);

        // ak neexistuje tovar v košíku
        if(!$cartItem){

            $cartItem = new CartItem;

            $cartItem->cart_id = $this->cartId;
            $cartItem->product_id = $id;
            $cartItem->price = $price;
            $cartItem->quantity = $quantity;

            $cartItem->save();

        }else {

            if(!$finalQuantity){
                $quantity = $quantity + $cartItem->quantity;
            }

            /**
             * Ak je súčet aktuálneho množstva produktu a pridávaného množstva produktu väčší, 
             * ako je skutočný počet produktu na sklade, pridá max. počet a hodnotu $quantity_limit
             */
            if ( ($quantity) > $cartItem->product->pocet_na_sklade) {

                $cartItem->quantity = $cartItem->product->pocet_na_sklade;
                $quantity_limit = true;
                $message = array(
                    "type" => "info",
                    "value" => "Požadovaný počet sme vám upravili podľa aktuálnych skladových zásob."
                );
                
            }else {
                $cartItem->quantity = $quantity;
            }

            $cartItem->price = $price;

            $cartItem->save();

        }

        return [
            "message" => isset($message) ? $message : NULL,
            "item" => $cartItem,
            "same_quantity" => $quantity_limit ?? false
        ];

    }
    
    /**
     * Vypočíta hodnotu košíka
     *
     * @return float|array
     */
    public function cartPrice(): float|array
    {

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
    
    /**
     * Obsah košíka
     *
     * @return CartItem[]|null
     */
    public function getCartItems(): Collection
    {

        return CartItem::with('products', 'product')->where('cart_id', $this->cartId)->get();

    }
    
    /**
     * Konkrétny tovar v košíku
     *
     * @param int $id
     *
     * @return CartItem|null
     */
    private function getCartItem(int $id): ?CartItem
    {

        return CartItem::with('product', 'product.discounts')->where([
            ['cart_id', $this->cartId],
            ['product_id', $id]
        ])->first();

    }
    
    /**
     * Vymaže tovar z košíka
     *
     * @param int $id
     *
     * @return void
     */
    public function deleteItem(int $id): void
    {

        CartItem::find($id)?->delete();

    }
    
    /**
     * Košík pre daného užívateľa s relačnými dátami
     *
     * @return Cart|null
     */
    public function getCart(): ?Cart
    {

        return Cart::with('informations', 'informations.deliveryPoint', 'vouchers', 'cartItem')->find($this->cartId);

    }
    
    /**
     * Upraví cenu tovaru v košíku (po prihlásení)
     *
     * @param CartItem $cartItem
     * @param float $price
     *
     * @return void
     */
    public function updatePriceOnCartItem(CartItem $cartItem, float $price): void
    {

        $cartItem->price = $price;
        $cartItem->save();

    }
    
    /**
     * Spojí tovar v košíku (po prihlásení)
     *
     * @param CartItem[] $previousSessionCartItems
     *
     * @return void
     */
    public function mergeCartItems(Collection $previousSessionCartItems): void
    {

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
    
    /**
     * Spojí informácie týkajúce sa košíka (po prihlásení)
     *
     * @param string $sessionCartId
     * @param int $userId
     *
     * @return void
     */
    public function mergeCartInformation(string $sessionCartId, int $userId): void
    {

        // $sessionCart = Cart::with("informations")->where("id", $sessionCartId)->first();

        $sessionCartInformations = CartInformation::where("cart_id", $sessionCartId)->first();
        $cartInformations = CartInformation::where("cart_id", $this->cartId)->first();

        if(
            $cartInformations && 
            $sessionCartInformations && 
            !$sessionCartInformations->is($cartInformations) )
        {

            $sessionCartInformations->cart_id = $this->cartId;
            $sessionCartInformations->save();
            self::saveUserDataToCart($sessionCartInformations);

            $cartInformations->delete();
        }

    }
    
    /**
     * Id košíka
     *
     * @return false|string
     */
    public function getCartId(): false|string
    {

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
    
    /**
     * Spojí tovar v košíkoch (po prihlásení)
     *
     * @param string $previousSessionId
     * @param int $userId
     *
     * @return void
     */
    public function mergeCartItemsFromPreviousSession(string $previousSessionId, int $userId): void
    {

        $cart_for_session = Cart::with('informations', 'cartItem')
            ->where('id', $previousSessionId)
            ->where('user_id', NULL)
            ->first();
                
        if(isset($cart_for_session) && count($cart_for_session->cartItem)){
            self::mergeCartItems($cart_for_session->cartItem);
        }

    }
    
    /**
     * Vymaže košík zo session
     *
     * @param string $previousSessionId
     * @param int $userId
     *
     * @return void
     */
    public function deleteSessionCart(string $previousSessionId, int $userId): void
    {

        Cart::where('id', $previousSessionId)->where('user_id', NULL)->delete();

    }
    
    /**
     * Pridá číslo zákazníka k existujúcemu košíku
     *
     * @param int $userId
     *
     * @return void
     */
    public function addUserIdToCartInstance(int $userId): void
    {

        $cart = self::getCart();

        if($cart && !$cart->user_id){
            $cart->user_id = $userId;
            $cart->save();
        }

    }
    
    /**
     * Zabezpečí, že košík existuje (pri pridaní tovaru)
     *
     * @return void
     */
    private function ensureCartExists(): void
    {

        if(!$this->cartId){

            // vytvorenie nové session_id
            $sessionId = Str::uuid();
            Session::put('cart_session_id', $sessionId);

            // vytvorenie košíka
            $cart = Cart::create([
                'id' => Session::get('cart_session_id'), 
                'user_id' => Auth::id() ?: NULL
            ]);

            $this->cartId = $cart->id;

            // vytvorenie informácií košíka
            $cart_informations = new CartInformation;
            $cart_informations->cart_id = $sessionId;
            $cart_informations->deliveryCountry = 1;
            $cart_informations->invoiceCountry = 1;
            $cart_informations->save();

            // aktualizácia informácií košíka na základe údajov prihláseného užívateľa
            self::saveUserDataToCart($cart_informations);

        }

    }
    
    /**
     * Odstráni košík aj iné vzťahy
     * - ak je košík prázdny
     * - ak sa objednávka odošle
     *
     * @return void
     */
    public function destroyCart(): void
    {

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
    
    /**
     * Odstrániť košíky staršie ako 30 dní
     *
     * @return void
     */
    public function removeOldCarts(): void
    {

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

	}
    
    /**
     * Priradí informácie užívateľa k informáciam košíka
     *
     * @param CartInformation $ci
     *
     * @return void
     */
    private function saveUserDataToCart(CartInformation $ci): void
    {

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

			$ci->save();

		}

	}


}