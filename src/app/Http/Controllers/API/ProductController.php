<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Services\PromotionService;

class ProductController extends Controller
{
    /**
     * Lista di tutti i prodotti
     */
    public function index(Request $request)
    {
        $products = Product::with('category')->get();
        
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }
    
    /**
     * Dettagli di un prodotto specifico
     */
    public function show($id)
    {
        $product = Product::with('category')->find($id);
        
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Prodotto non trovato'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }
}