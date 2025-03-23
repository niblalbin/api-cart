<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    use HasFactory;
    
    public $timestamps = false;
    
    protected $fillable = [
        'cart_id',
        'product_id',
        'quantity',
        'calculated_price'
    ];
    
    // Relazione con Cart
    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id');
    }
    
    // Relazione con Product
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}