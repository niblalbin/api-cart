<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;
    
    public $timestamps = false;
    
    protected $fillable = [
        'ecommerce_id',
        'product_sku',
        'product_name',
        'product_category_id',
        'quantity',
        'base_price'
    ];
    
    // Relazione con ProductCategory
    public function category()
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
    
    // Relazione con CartItem
    public function cartItems()
    {
        return $this->hasMany(CartItem::class, 'product_id');
    }
}