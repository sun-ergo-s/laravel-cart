<?php

namespace SunErgoS\LaravelCart\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use App\Models\CartInformation;

class Cart extends Model
{

    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $connection = 'next_mysql';

    public $timestamps = false;

    protected $fillable = ['id', 'user_id'];

    public function informations(){
        return $this->hasOne(CartInformation::class, 'cart_id');
    }
    
}
