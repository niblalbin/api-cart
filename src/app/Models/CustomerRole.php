<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerRole extends Model
{
    use HasFactory;
    
    protected $table = 'customers_role';
    
    public $timestamps = false;
    
    protected $fillable = ['customer_role'];
    
    // Relazione con Customer
    public function customers()
    {
        return $this->hasMany(Customer::class, 'customer_role_id');
    }
}