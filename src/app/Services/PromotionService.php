<?php

namespace App\Services;

use App\Models\Product;
use Carbon\Carbon;

class PromotionService
{
    private $priceService;
    
    /**
     * PromotionService constructor.
     */
    public function __construct(PriceCalculationService $priceService)
    {
        $this->priceService = $priceService;
    }
    
    /**
     * Ottiene tutte le promozioni attive
     */
    public function getActivePromotions(): array
    {
        $today = Carbon::now();
        $isLastFriday = $this->isLastFridayOfMonth($today);
        
        $promotions = [
            'quantity_discounts' => [
                [
                    'threshold' => 10,
                    'discount_percentage' => 5,
                    'description' => 'Acquista più di 10 pezzi: SCONTO 5%'
                ],
                [
                    'threshold' => 25,
                    'discount_percentage' => 10,
                    'description' => 'Acquista più di 25 pezzi: SCONTO 10%'
                ],
                [
                    'threshold' => 50,
                    'discount_percentage' => 15,
                    'description' => 'Acquista più di 50 pezzi: SCONTO 15%'
                ],
                [
                    'threshold' => 100,
                    'discount_percentage' => 20,
                    'description' => 'Acquista più di 100 pezzi: SCONTO 20%'
                ]
            ],
            'category_promotions' => [
                [
                    'category_id' => 3,
                    'category_name' => 'PHOTOVOLTAIC',
                    'description' => 'Per ogni 5 prodotti della categoria PHOTOVOLTAIC, 1 è GRATIS!',
                    'promo_type' => '5+1'
                ]
            ],
            'last_friday_promotion' => [
                'active' => $isLastFriday,
                'category_id' => 1,
                'category_name' => 'SPARE_PARTS',
                'special_price' => 25.00,
                'description' => 'Oggi tutti i prodotti della categoria SPARE_PARTS hanno un prezzo fisso di €25.00!'
            ]
        ];
        
        if (!$isLastFriday) {
            // Trova l'ultimo venerdì del mese corrente
            $lastDayOfMonth = $today->copy()->endOfMonth();
            $lastFriday = $lastDayOfMonth->copy();
            
            while ($lastFriday->dayOfWeek !== Carbon::FRIDAY) {
                $lastFriday->subDay();
            }
            
            $promotions['next_last_friday'] = [
                'date' => $lastFriday->format('Y-m-d'),
                'formatted_date' => $lastFriday->format('d/m/Y'),
                'description' => "L'ultimo venerdì del mese ({$lastFriday->format('d/m/Y')}) tutti i prodotti ".
                                 "della categoria SPARE_PARTS avranno un prezzo fisso di €25.00!"
            ];
        } else {
            // Se è l'ultimo venerdì, imposta la promozione come attiva
            $promotions['last_friday_promotion']['active'] = true;
        }
        
        return $promotions;
    }
    
    /**
     * Ottiene le promozioni attive per un prodotto specifico
     */
    public function getProductPromotions(Product $product): array
    {
        $today = Carbon::now();
        $isLastFriday = $this->isLastFridayOfMonth($today);
        $categoryId = $product->product_category_id;
        
        $promotions = [];
        
        // Promozione per categoria 3 (photovoltaic)
        if ($categoryId === 3) {
            $promotions[] = [
                'type' => 'category',
                'description' => '5+1 GRATIS',
                'details' => 'Per ogni 5 prodotti acquistati, 1 è gratuito'
            ];
        }
        
        // Promozione ultimo venerdì per categoria 1 (spare_parts)
        if ($categoryId === 1 && $isLastFriday) {
            $promotions[] = [
                'type' => 'one_shot',
                'description' => 'ONE-SHOT €25!',
                'details' => 'Prezzo fisso di €25.00 solo per oggi ' . $today->format('d/m/Y'),
                'special_price' => 25.00
            ];
        }
        
        // Promozioni per quantità (valide per tutti i prodotti)
        $promotions[] = [
            'type' => 'quantity',
            'description' => 'Sconto fino al 20% in base alla quantità',
            'details' => [
                'Acquista più di 10 pezzi: SCONTO 5%',
                'Acquista più di 25 pezzi: SCONTO 10%',
                'Acquista più di 50 pezzi: SCONTO 15%',
                'Acquista più di 100 pezzi: SCONTO 20%'
            ]
        ];
        
        return $promotions;
    }
    
    /**
     * Calcola i dettagli degli sconti applicati a un item del carrello
     */
    public function calculateItemDiscounts(Product $product, int $quantity, ?Carbon $date = null): array
    {
        $date = $date ?? Carbon::now();
        $isLastFriday = $this->isLastFridayOfMonth($date);
        $categoryId = $product->product_category_id;
        
        $basePrice = $product->base_price;
        $baseTotal = $basePrice * $quantity;
        $appliedPromotions = [];
        
        // Prezzo calcolato con le promozioni
        $calculatedPrice = $this->priceService->calculatePrice($product, $quantity, $date);
        
        // Promozione ultimo venerdì per categoria 1 (spare_parts)
        if ($categoryId === 1 && $isLastFriday) {
            $appliedPromotions[] = [
                'type' => 'one_shot',
                'description' => 'Prezzo speciale €25.00 (One-shot)',
                'saved' => $baseTotal - (25.00 * $quantity),
                'applied_value' => 25.00 * $quantity
            ];
            
            // Se si applica one-shot, non si applicano altri sconti
            return [
                'original_price' => $baseTotal,
                'discounted_price' => $calculatedPrice,
                'saved' => $baseTotal - $calculatedPrice,
                'discount_percentage' => ($baseTotal > 0) ? (($baseTotal - $calculatedPrice) / $baseTotal) * 100 : 0,
                'applied_promotions' => $appliedPromotions
            ];
        }
        
        // Promozione quantità
        $discountPercentage = 0;
        if ($quantity > 100) {
            $discountPercentage = 20;
            $appliedPromotions[] = [
                'type' => 'quantity',
                'description' => 'Sconto 20% per quantità > 100',
                'saved' => $baseTotal * 0.20,
                'discount_percentage' => 20
            ];
        } elseif ($quantity > 50) {
            $discountPercentage = 15;
            $appliedPromotions[] = [
                'type' => 'quantity',
                'description' => 'Sconto 15% per quantità > 50',
                'saved' => $baseTotal * 0.15,
                'discount_percentage' => 15
            ];
        } elseif ($quantity > 25) {
            $discountPercentage = 10;
            $appliedPromotions[] = [
                'type' => 'quantity',
                'description' => 'Sconto 10% per quantità > 25',
                'saved' => $baseTotal * 0.10,
                'discount_percentage' => 10
            ];
        } elseif ($quantity > 10) {
            $discountPercentage = 5;
            $appliedPromotions[] = [
                'type' => 'quantity',
                'description' => 'Sconto 5% per quantità > 10',
                'saved' => $baseTotal * 0.05,
                'discount_percentage' => 5
            ];
        }
        
        // Promozione categoria 3 (photovoltaic)
        if ($categoryId === 3 && $quantity >= 5) {
            $freeItems = (int)($quantity / 5);
            $savedByFreeItems = $basePrice * $freeItems * (1 - ($discountPercentage / 100));
            
            $appliedPromotions[] = [
                'type' => 'category',
                'description' => "{$freeItems} prodotti gratis (5+1)",
                'saved' => $savedByFreeItems,
                'free_items' => $freeItems
            ];
        }
        
        return [
            'original_price' => $baseTotal,
            'discounted_price' => $calculatedPrice,
            'saved' => $baseTotal - $calculatedPrice,
            'discount_percentage' => ($baseTotal > 0) ? (($baseTotal - $calculatedPrice) / $baseTotal) * 100 : 0,
            'applied_promotions' => $appliedPromotions
        ];
    }
    
    /**
     * Verifica se la data specificata è l'ultimo venerdì del mese
     */
    public function isLastFridayOfMonth(Carbon $date): bool
    {
        $checkDate = $date->copy();
        
        // Verifica se è un venerdì
        if ($checkDate->dayOfWeek !== 5) {
            return false;
        }
        
        $lastDay = $checkDate->copy()->endOfMonth();
        $lastFriday = $lastDay->copy()->previous(Carbon::FRIDAY);
        
        // Confronta se il venerdì in questione è l'ultimo del mese
        return $checkDate->isSameDay($lastFriday);
    }
}