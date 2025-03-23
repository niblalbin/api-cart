<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'ecommerce_id',
        'customer_id',
        'cart_status_id',
        'date_checkout',
        'total_price'
    ];
    
    protected $dates = [
        'date_checkout',
        'created_at',
        'updated_at'
    ];
    
    // Relazione con Customer
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }
    
    // Relazione con CartStatus
    public function status()
    {
        return $this->belongsTo(CartStatus::class, 'cart_status_id');
    }
    
    // Relazione con CartItem
    public function items()
    {
        return $this->hasMany(CartItem::class, 'cart_id');
    }
}