<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Product;
use App\Services\PriceCalculationService;
use App\Services\PromotionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    protected $priceService;
    protected $promotionService;
    
    public function __construct(PriceCalculationService $priceService, PromotionService $promotionService)
    {
        $this->priceService = $priceService;
        $this->promotionService = $promotionService;
    }
    
    /**
     * Lista dei carrelli dell'utente
     */
    public function index()
    {
        $carts = Cart::with('status')
                     ->where('customer_id', Auth::id())
                     ->orderBy('created_at', 'desc')
                     ->get();
        
        $carts->each(function($cart) {
            $cart->items_count = CartItem::where('cart_id', $cart->id)->count();
        });
        
        return response()->json([
            'success' => true,
            'data' => $carts
        ]);
    }
    
    /**
     * Dettaglio di un carrello
     */
    public function show($id)
    {
        // Recupera il carrello
        $cart = Cart::with(['items.product.category', 'status'])
                    ->where('customer_id', Auth::id())
                    ->find($id);
        
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrello non trovato o utente non autorizzato'
            ], 404);
        }
        
        $today = Carbon::now();
        $checkoutDate = $cart->date_checkout ?? $today;
        
        // Itera i prodotti presenti nel carrello per applicare le promozioni basate sulla 
        // data odierna o sulla data in fase di checkout
        $cart->items->each(function($item) use ($checkoutDate) {
            $item->discount_details = $this->promotionService->calculateItemDiscounts(
                $item->product, 
                $item->quantity, 
                $checkoutDate
            );
        });
        
        // Prezzo totale (senza promozioni applicate)
        $originalTotal = $cart->items->sum(function($item) {
            return $item->discount_details['original_price'];
        });
        
        // Prezzo totale (con le promozioni applicate)
        $discountedTotal = $cart->items->sum(function($item) {
            return $item->discount_details['discounted_price'];
        });
        
        $totalSaved = $originalTotal - $discountedTotal;
        $savePercentage = ($originalTotal > 0) ? ($totalSaved / $originalTotal) * 100 : 0;
        
        $cart->summary = [
            'original_total' => $originalTotal,
            'discounted_total' => $discountedTotal,
            'total_saved' => $totalSaved,
            'save_percentage' => $savePercentage
        ];
        
        return response()->json([
            'success' => true,
            'data' => $cart
        ]);
    }
    
    /**
     * Crea un nuovo carrello
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ecommerce_id' => 'nullable|integer'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $statusId = CartStatus::where('status', 'created')->first()->id;
        
        $cart = Cart::create([
            'ecommerce_id' => 1,
            'customer_id' => Auth::id(),
            'cart_status_id' => $statusId,
            'total_price' => 0
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Carrello creato con successo',
            'data' => $cart
        ], 201);
    }
    
    /**
     * Checkout del carrello
     */
    public function checkout($id)
    {
        $cart = Cart::with('items.product')
                    ->where('customer_id', Auth::id())
                    ->find($id);
        
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrello non trovato o utente non autorizzato'
            ], 404);
        }
        
        if ($cart->cart_status_id == CartStatus::where('status', 'checkout')->first()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout già eseguito'
            ], 400);
        }
        
        if ($cart->items->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Il carrello è vuoto'
            ], 400);
        }
        
        // Salva il prezzo totale prima del checkout per il confronto
        $totalBefore = $cart->total_price;
        
        // Aggiorna lo stato e la data checkout
        $cart->cart_status_id = CartStatus::where('status', 'checkout')->first()->id;
        $cart->date_checkout = Carbon::now();
        $cart->save();
        
        $itemDetails = [];
        
        // Ricalcola tutti i prezzi in base alla data del checkout
        foreach ($cart->items as $item) {
            $product = $item->product;
            $qty = $item->quantity;
            $oldPrice = $item->calculated_price;
            
            $calculatedPrice = $this->priceService->calculatePrice(
                $product, 
                $qty, 
                $cart->date_checkout
            );
            
            $item->calculated_price = $calculatedPrice;
            $item->save();
            
            // Aggiungi dettagli sugli sconti applicati
            $discountDetails = $this->promotionService->calculateItemDiscounts(
                $product,
                $qty,
                $cart->date_checkout
            );
            
            $itemDetails[] = [
                'id' => $item->id,
                'product_name' => $product->product_name,
                'quantity' => $qty,
                'old_price' => $oldPrice,
                'new_price' => $calculatedPrice,
                'discount_details' => $discountDetails
            ];
        }
        
        // Aggiorna il prezzo totale
        $this->updateCartTotal($cart);
        
        return response()->json([
            'success' => true,
            'message' => 'Checkout completato con successo',
            'data' => $cart->load('items.product')->find($cart->id),
            'checkout_details' => [
                'previous_total' => $totalBefore,
                'new_total' => $cart->total_price,
                'saved' => $totalBefore - $cart->total_price,
                'items' => $itemDetails
            ]
        ]);
    }
    
    /**
     * Aggiorna il prezzo totale del carrello
     */
    private function updateCartTotal(Cart $cart)
    {
        $totalPrice = CartItem::where('cart_id', $cart->id)
                            ->sum('calculated_price');
        
        $cart->total_price = $totalPrice;
        $cart->save();
        
        return $cart->refresh();
    }
}