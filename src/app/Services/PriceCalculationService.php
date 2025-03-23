<?php

namespace App\Services;

use App\Models\Product;
use Carbon\Carbon;
use App\Services\PromotionService;

class PriceCalculationService
{
    /**
     * Prezzo "one-shot" per prodotti di categoria 1 nell'ultimo venerdì del mese
     */
    private const ONE_SHOT_PRICE = 25.00;
    
    /**
     * Soglie di sconto per quantità e percentuali relative
     */
    private const QUANTITY_DISCOUNTS = [
        100 => 0.20, // 20% di sconto per quantità > 100
        50  => 0.15, // 15% di sconto per quantità > 50
        25  => 0.10, // 10% di sconto per quantità > 25
        10  => 0.05, // 5% di sconto per quantità > 10
    ];
    
    /**
     * Calcola il prezzo di un prodotto in base alla quantità nel carrello e alla data di checkout
     */
    public function calculatePrice(Product $product, int $quantity, ?Carbon $checkoutDate = null): float
    {
        $date = $checkoutDate ?? Carbon::now();
        
        $categoryId = $product->product_category_id;
        $price = $product->base_price;
        
        // Verifica promozione "one-shot" (ultimo venerdì del mese) per prodotti categoria 1
        $promotionService = app(PromotionService::class);
        if ($categoryId === 1 && $promotionService->isLastFridayOfMonth($date)) {
            // Applica prezzo fisso (non si applica nessun altro tipo di promozione)
            return self::ONE_SHOT_PRICE * $quantity;
        }
        
        // Applica sconto per quantità
        $discountRate = $this->getQuantityDiscountRate($quantity);
        $discountedPrice = $price * (1 - $discountRate);
        
        $totalPrice = $discountedPrice * $quantity;
        
        // Applica promozione categoria 3 "Compra 5 prendi 1 gratis"
        if ($categoryId === 3 && $quantity >= 5) {
            $freeItems = (int)($quantity / 5);
            $effectiveQuantity = $quantity - $freeItems;
            $totalPrice = $discountedPrice * $effectiveQuantity;
        }
        
        return $totalPrice;
    }
    
    /**
     * Ottieni la percentuale di sconto appropriata in base alla quantità
     */
    private function getQuantityDiscountRate(int $quantity): float
    {
        foreach (self::QUANTITY_DISCOUNTS as $threshold => $discount) {
            if ($quantity > $threshold) {
                return $discount;
            }
        }
        
        return 0;
    }
}
