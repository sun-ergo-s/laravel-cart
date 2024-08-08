<?php

namespace SunErgoS\LaravelCart\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Product;

class CartItem extends Model
{
    protected $connection = 'next_mysql';

    public function product()
    {
        return $this->hasOne(Product::class, 'id', 'product_id');
    }

    public function products()
    {
        return $this->hasMany(Product::class, 'id', 'product_id');
    }
}
