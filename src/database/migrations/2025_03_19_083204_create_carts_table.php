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
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->integer('ecommerce_id')->nullable();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('cart_status_id');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('date_checkout')->nullable();
            $table->decimal('total_price', 10, 2)->default(0);
            
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('customers');
                  
            $table->foreign('cart_status_id')
                  ->references('id')
                  ->on('cart_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};