<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Customer extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    
    public $timestamps = false;
    
    protected $fillable = [
        'customer_role_id',
        'email',
        'psw'
    ];
    
    protected $hidden = [
        'psw'
    ];
    
    // Relazione con CustomerRole
    public function role()
    {
        return $this->belongsTo(CustomerRole::class, 'customer_role_id');
    }
    
    // Relazione con Cart
    public function carts()
    {
        return $this->hasMany(Cart::class, 'customer_id');
    }
}