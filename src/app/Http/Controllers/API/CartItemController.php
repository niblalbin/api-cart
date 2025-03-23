<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Models\Product;
use App\Services\PriceCalculationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CartItemController extends Controller
{
    protected $priceService;
    
    public function __construct(PriceCalculationService $priceService)
    {
        $this->priceService = $priceService;
    }
    
    /**
     * Lista dei prodotti nel carrello
     */
    public function index($cartId)
    {
        $cart = Cart::where('customer_id', Auth::id())->find($cartId);
        
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrello non trovato o utente non autorizzato'
            ], 404);
        }
        
        $cartItems = CartItem::with(['product.category'])
                           ->where('cart_id', $cartId)
                           ->get();
        
        return response()->json([
            'success' => true,
            'data' => $cartItems
        ]);
    }
    
    /**
     * Aggiunge un nuovo prodotto nel carrello
     */
    public function store(Request $request, $cartId)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $cart = Cart::where('customer_id', Auth::id())->find($cartId);
        
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrello non trovato o non autorizzato'
            ], 404);
        }
        
        if ($cart->cart_status_id == CartStatus::where('status', 'checkout')->first()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Il carrello è già stato completato'
            ], 400);
        }
        
        // Recupero del prodotto indicato dal cliente
        $product = Product::find($request->product_id);
        
        // Verifica se il prodotto è già nel carrello
        $cartItem = CartItem::where('cart_id', $cart->id)
                         ->where('product_id', $request->product_id)
                         ->first();
                         
        if ($cartItem) {
            // Aggiorna la quantità e il prezzo
            $cartItem->quantity += $request->quantity;
            $calculatedPrice = $this->priceService->calculatePrice($product, $cartItem->quantity);
            $cartItem->calculated_price = $calculatedPrice;
            $cartItem->save();
        } else {
            // Aggiunta di un nuovo prodotto
            $calculatedPrice = $this->priceService->calculatePrice($product, $request->quantity);
            $cartItem = CartItem::create([
                'cart_id' => $cart->id,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'calculated_price' => $calculatedPrice
            ]);
        }
        
        // Aggiorna lo stato del carrello
        $cart->cart_status_id = CartStatus::where('status', 'building')->first()->id;
        $cart->save();
        
        // Ricalcola il prezzo totale
        $this->updateCartTotal($cart);
        
        return response()->json([
            'success' => true,
            'message' => 'Prodotto aggiunto al carrello',
            'data' => $cartItem->load('product')
        ], 201);
    }
    
    /**
     * Mostra un prodotto specifico del carrello
     */
    public function show($cartId, $itemId)
    {
        $cart = Cart::where('customer_id', Auth::id())->find($cartId);
        
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrello non trovato o non autorizzato'
            ], 404);
        }
        
        $cartItem = CartItem::with('product.category')
                          ->where('cart_id', $cartId)
                          ->find($itemId);
        
        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item non trovato'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => $cartItem
        ]);
    }
    
    /**
     * Aggiorna la quantità di un prodotto nel carrello
     */
    public function update(Request $request, $cartId, $itemId)
    {
        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:1'
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }
        
        $cart = Cart::where('customer_id', Auth::id())->find($cartId);
        
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrello non trovato o non autorizzato'
            ], 404);
        }
        
        if ($cart->cart_status_id == CartStatus::where('status', 'checkout')->first()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout già effettuato, il carrello non può più essere modificato'
            ], 400);
        }
        
        $cartItem = CartItem::with('product')
                          ->where('cart_id', $cartId)
                          ->find($itemId);
        
        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item non trovato'
            ], 404);
        }
        
        // Aggiorna la quantità e ricalcola il prezzo
        $cartItem->quantity = $request->quantity;
        $calculatedPrice = $this->priceService->calculatePrice($cartItem->product, $request->quantity);
        $cartItem->calculated_price = $calculatedPrice;
        $cartItem->save();
        
        // Ricalcola il prezzo totale del carrello
        $this->updateCartTotal($cart);
        
        return response()->json([
            'success' => true,
            'message' => 'Quantità aggiornata con successo',
            'data' => $cartItem
        ]);
    }
    
    /**
     * Rimuove un prodotto specifico dal carrello
     */
    public function destroy($cartId, $itemId)
    {
        $cart = Cart::where('customer_id', Auth::id())->find($cartId);
        
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrello non trovato o non autorizzato'
            ], 404);
        }
        
        if ($cart->cart_status_id == CartStatus::where('status', 'checkout')->first()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Il carrello è già stato completato e non può essere modificato'
            ], 400);
        }
        
        $cartItem = CartItem::where('cart_id', $cartId)->find($itemId);
        
        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item non trovato'
            ], 404);
        }
        
        $cartItem->delete();
        
        // Ricalcola il prezzo totale del carrello
        $this->updateCartTotal($cart);
        
        return response()->json([
            'success' => true,
            'message' => 'Prodotto rimosso dal carrello'
        ]);
    }
    
    /**
     * Rimuove tutti i prodotti dal carrello
     */
    public function clear($cartId)
    {
        $cart = Cart::where('customer_id', Auth::id())->find($cartId);
        
        if (!$cart) {
            return response()->json([
                'success' => false,
                'message' => 'Carrello non trovato o utente non autorizzato'
            ], 404);
        }
        
        if ($cart->cart_status_id == CartStatus::where('status', 'checkout')->first()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Checkout già effettuato, il carrello non può più essere modificato'
            ], 400);
        }
        
        CartItem::where('cart_id', $cartId)->delete();
        
        // Aggiorna il prezzo totale del carrello
        $cart->total_price = 0;
        $cart->save();
        
        return response()->json([
            'success' => true,
            'message' => 'Il carrello è stato svuotato con successo'
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