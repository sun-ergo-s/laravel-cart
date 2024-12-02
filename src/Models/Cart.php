<?php

namespace SunErgoS\LaravelCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\CartInformation;
use App\Models\Coupon;
use App\Models\User;
use App\Models\GiftCard;
use SunErgoS\LaravelCart\Models\CartItem;

class Cart extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $connection = 'next_mysql';

    public $timestamps = false;

    protected $fillable = ['id', 'user_id'];

    public function informations(){
        return $this->hasOne(CartInformation::class, 'cart_id', 'id');
    }

    public function vouchers(){
        return $this->belongsToMany(Coupon::class)->orderBy('id', 'ASC');
    }

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function cartItem()
    {
        return $this->hasMany(CartItem::class);
    }

    public function giftCards()
    {
        return $this->hasMany(GiftCard::class, "cart_id", "id");
    }
    
}
