<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductCategory extends Model
{
    use HasFactory;
    
    protected $table = 'product_category';
    
    public $timestamps = false;
    
    protected $fillable = ['category'];
    
    // Relazione con Product
    public function products()
    {
        return $this->hasMany(Product::class, 'product_category_id');
    }
}