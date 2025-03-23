<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CartStatus extends Model
{
    use HasFactory;
    
    protected $table = 'cart_status';
    
    public $timestamps = false;
    
    protected $fillable = ['status'];
    
    // Relazione con Cart
    public function carts()
    {
        return $this->hasMany(Cart::class, 'cart_status_id');
    }
}