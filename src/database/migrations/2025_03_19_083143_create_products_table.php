<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->integer('ecommerce_id')->nullable();
            $table->string('product_sku', 100);
            $table->string('product_name', 255);
            $table->unsignedBigInteger('product_category_id');
            $table->integer('quantity')->default(0);
            $table->decimal('base_price', 10, 2);
            
            $table->foreign('product_category_id')
                  ->references('id')
                  ->on('product_category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};