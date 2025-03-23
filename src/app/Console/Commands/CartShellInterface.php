<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartStatus;
use App\Services\PriceCalculationService;
use App\Services\PromotionService;
use App\Http\Controllers\API\CartController;
use App\Http\Controllers\API\CartItemController;
use App\Http\Controllers\API\ProductController;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CartShellInterface extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cart:shell';

    protected $currentCart = null;
    protected $currentCustomer = null;
    protected $priceService;
    protected $promotionService;
    protected $cartController;
    protected $cartItemController;
    protected $productController;
    protected $actionStack = [];

    /**
     * Create a new command instance.
     */
    public function __construct(
        PriceCalculationService $priceService,
        PromotionService $promotionService,
        CartController $cartController,
        CartItemController $cartItemController,
        ProductController $productController
    ) {
        parent::__construct();
        $this->priceService = $priceService;
        $this->promotionService = $promotionService;
        $this->cartController = $cartController;
        $this->cartItemController = $cartItemController;
        $this->productController = $productController;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== Cart API Testing Shell ===');
        $this->info('Benvenuto nell\'interfaccia di test per il Cart API');
        
        while (true) {
            $this->showStatus();
            
            $menuOptions = [];
            
            // Menu per utente non loggato
            if (!$this->currentCustomer) {
                $menuOptions = [
                    'Login come cliente',
                    'Visualizza prodotti disponibili',
                ];
            } 
            // Menu per utente loggato
            else {
                // Opzioni base per utente loggato
                $menuOptions = [
                    'Visualizza prodotti disponibili',
                ];
                
                // Se non ha un carrello attivo
                if (!$this->currentCart) {
                    $menuOptions[] = 'Crea un nuovo carrello';
                    $menuOptions[] = 'Visualizza i miei carrelli';
                } 
                // Se ha un carrello attivo
                else {
                    $menuOptions[] = 'Aggiungi prodotto al carrello';
                    $menuOptions[] = 'Visualizza carrello';
                    $menuOptions[] = 'Modifica carrello';
                    
                    // Solo se il carrello non è già in checkout
                    if ($this->currentCart->cart_status_id != CartStatus::where('status', 'checkout')->first()->id) {
                        $menuOptions[] = 'Checkout carrello';
                    }
                    
                    $menuOptions[] = 'Cambia carrello';
                }
                
                // Opzione logout sempre disponibile per utenti loggati
                $menuOptions[] = 'Logout';
            }
            
            // Aggiungi l'opzione "Indietro" se ci sono azioni nello stack
            if (!empty($this->actionStack)) {
                $menuOptions[] = 'Indietro';
            }
            
            // Aggiungi sempre l'opzione "Esci" alla fine
            $menuOptions[] = 'Esci';
            
            $choice = $this->choice(
                'Cosa vuoi fare?',
                $menuOptions
            );
            
            // Salva l'azione corrente prima di eseguirla (per l'opzione Indietro)
            if ($choice !== 'Indietro' && $choice !== 'Esci') {
                $this->actionStack[] = [
                    'customer' => $this->currentCustomer ? clone $this->currentCustomer : null,
                    'cart' => $this->currentCart ? $this->currentCart->id : null
                ];
                
                // Limita lo stack a 10 azioni al massimo
                if (count($this->actionStack) > 10) {
                    array_shift($this->actionStack);
                }
            }
            
            switch ($choice) {
                case 'Login come cliente':
                    $this->loginAsCustomer();
                    break;
                case 'Crea un nuovo carrello':
                    $this->createNewCart();
                    break;
                case 'Visualizza prodotti disponibili':
                    $this->showAvailableProducts();
                    break;
                case 'Aggiungi prodotto al carrello':
                    $this->addProductToCart();
                    break;
                case 'Visualizza carrello':
                    $this->viewCart();
                    break;
                case 'Visualizza i miei carrelli':
                    $this->listUserCarts();
                    break;
                case 'Cambia carrello':
                    $this->switchCart();
                    break;
                case 'Modifica carrello':
                    $this->editCart();
                    break;
                case 'Checkout carrello':
                    $this->checkoutCart();
                    break;
                case 'Logout':
                    $this->logout();
                    break;
                case 'Indietro':
                    $this->goBack();
                    break;
                case 'Esci':
                    $this->info('Grazie per aver usato l\'interfaccia di test.');
                    return;
            }
        }
    }
    
    /**
     * Mostra lo stato corrente (cliente e carrello)
     */
    protected function showStatus()
    {
        $this->line('');
        $this->line('=== Stato corrente ===');
        
        if ($this->currentCustomer) {
            $role = $this->currentCustomer->role->customer_role;
            $this->info("Cliente: {$this->currentCustomer->email} (Tipo: {$role})");
        } else {
            $this->warn('Esegui il login prima di procedere.');
        }
        
        if ($this->currentCart) {
            $status = $this->currentCart->status->status;
            $this->info("Carrello ID: {$this->currentCart->id} (Stato: {$status})");
            $this->info("Numero di articoli: " . $this->currentCart->items->count());
            $this->info("Prezzo totale: €" . number_format($this->currentCart->total_price, 2));
        }

        $this->line('');
    }
    
    /**
     * Login come cliente
     */
    protected function loginAsCustomer()
    {
        $this->info('=== Clienti disponibili ===');
        $customers = Customer::all();
        
        $customerMap = [];
        $options = ['Annulla'];
        
        foreach ($customers as $customer) {
            $role = $customer->role->customer_role;
            $label = "Cliente {$customer->id}: {$customer->email} ({$role})";
            $options[] = $label;
            $customerMap[$label] = $customer->id;
        }
        
        $choice = $this->choice('Seleziona un cliente', $options, 0);
        
        if ($choice === 'Annulla') {
            $this->info('Operazione annullata.');
            return;
        }
        
        $customerId = $customerMap[$choice];
        
        $this->currentCustomer = Customer::find($customerId);
        $this->info("Hai eseguito il login come: {$this->currentCustomer->email}");
        
        // Seleziona automaticamente un carrello con stato "created" o "building" se esiste
        try {
            $activeCart = Cart::where('customer_id', $customerId)
                             ->whereIn('cart_status_id', function($query) {
                                 $query->select('id')
                                      ->from('cart_status')
                                      ->whereIn('status', ['created', 'building']);
                             })
                             ->latest()
                             ->first();
            
            if ($activeCart) {
                $this->currentCart = $activeCart;
                $this->info("Carrello attivo ID: {$this->currentCart->id} selezionato automaticamente.");
            } else {
                $this->currentCart = null;
            }
        } catch (\Exception $e) {
            $this->warn("Impossibile caricare i carrelli: " . $e->getMessage());
            $this->currentCart = null;
        }
    }
    
    /**
     * Simula l'autenticazione dell'utente
     */
    protected function simulateAuthentication($customer)
    {
        // Data l'assenza di un contesto tipico web dall'interfaccia shell,
        // viene simulata un autenticazione "fittizia"
        Auth::shouldReceive('id')->andReturn($customer->id);
    }
    
    /**
     * Logout dell'utente corrente
     */
    protected function logout()
    {
        if (!$this->currentCustomer) {
            $this->warn('Nessun cliente connesso.');
            return;
        }
        
        $customerEmail = $this->currentCustomer->email;
        
        // Reset del cliente e del carrello (anche qui si tratta di un logout "fittizio")
        $this->currentCustomer = null;
        $this->currentCart = null;
        
        $this->info("Logout eseguito correttamente per {$customerEmail}.");
    }
    
    /**
     * Torna all'azione precedente
     */
    protected function goBack()
    {
        if (empty($this->actionStack)) {
            $this->warn('Non ci sono azioni precedenti.');
            return;
        }
        
        $previousAction = array_pop($this->actionStack);
        
        $this->currentCustomer = $previousAction['customer'];
        
        if ($previousAction['cart']) {
            $this->currentCart = Cart::find($previousAction['cart']);
        } else {
            $this->currentCart = null;
        }
    }
    
    /**
     * Crea un nuovo carrello
     */
    protected function createNewCart()
    {
        if (!$this->currentCustomer) {
            $this->error('Devi prima eseguire il login come cliente.');
            return;
        }
        
        $request = new Request();
        $request->merge(['ecommerce_id' => 1]);
        
        // Simula l'autenticazione dell'utente
        $this->simulateAuthentication($this->currentCustomer);
        
        $response = $this->cartController->store($request);
        $responseData = json_decode($response->getContent());
        
        if ($responseData->success) {
            $this->currentCart = Cart::find($responseData->data->id);
            $this->info("Nuovo carrello creato con ID: {$this->currentCart->id}");
        } else {
            $this->error("Errore nella creazione del carrello: {$responseData->message}");
        }
    }
    
    /**
     * Mostra prodotti disponibili
     */
    protected function showAvailableProducts()
    {
        $this->info('=== Prodotti disponibili ===');
        
        // Mostra le promozioni disponibili
        $this->showCurrentPromotions();
        
        // Recupero dei prodotti
        $request = new Request();
        $response = $this->productController->index($request);
        $responseData = json_decode($response->getContent());
        
        $products = $responseData->data;
        
        $headers = ['ID', 'SKU', 'Nome', 'Categoria', 'Quantità disponibile', 'Prezzo base', 'Promozioni'];
        $rows = [];
        
        foreach ($products as $product) {
            // Ottieni le promozioni per questo prodotto
            $productObj = Product::find($product->id);
            $promotions = $this->promotionService->getProductPromotions($productObj);
            
            $promoDescriptions = [];
            foreach ($promotions as $promo) {
                $promoDescriptions[] = $promo['description'];
            }
            
            $rows[] = [
                $product->id,
                $product->product_sku,
                $product->product_name,
                $product->category->category,
                $product->quantity,
                '€' . number_format($product->base_price, 2),
                implode(", ", $promoDescriptions)
            ];
        }
        
        $this->table($headers, $rows);
        
        // Se l'utente è loggato e ha un carrello attivo
        if ($this->currentCustomer && $this->currentCart) {
            if ($this->confirm('Vuoi aggiungere un prodotto al carrello?', false)) {
                $this->addProductToCart();
            }
        }

        // Se l'utente è loggato ma non ha un carrello attivo
        elseif ($this->currentCustomer && !$this->currentCart) {
            if ($this->confirm('Vuoi creare un carrello per acquistare questi prodotti?', false)) {
                $this->createNewCart();
            }
        }

        // Se l'utente non è loggato
        elseif (!$this->currentCustomer) {
            if ($this->confirm('Vuoi effettuare il login per poter acquistare questi prodotti?', false)) {
                $this->loginAsCustomer();
            }
        }
    }
    
    /**
     * Mostra le promozioni attualmente disponibili
     */
    protected function showCurrentPromotions()
    {
        $promotions = $this->promotionService->getActivePromotions();
        
        $this->info("\nPROMOZIONI ATTIVE");
        
        // Sconti per quantità
        $this->line("SCONTI PER QUANTITÀ:");
        foreach ($promotions['quantity_discounts'] as $discount) {
            $this->line("  • {$discount['description']}");
        }
        
        // Promozioni per categoria
        $this->line("\nFOTOVOLTAICO - COMPRA 5 PRENDI 6:");
        foreach ($promotions['category_promotions'] as $promo) {
            $this->line("  • {$promo['description']}");
        }
        
        // Promozione ultimo venerdì del mese
        if ($promotions['last_friday_promotion']['active']) {
            $this->line("\nPROMOZIONE SPECIALE - ULTIMO VENERDÌ DEL MESE!");
            $this->line("  • {$promotions['last_friday_promotion']['description']}");
            $this->line("  • Valido solo per oggi " . Carbon::now()->format('d/m/Y') . "!");
        } else if (isset($promotions['next_last_friday'])) {
            // Mostra quando sarà il prossimo ultimo venerdì
            $this->line("\nPROSSIMA PROMOZIONE ONE-SHOT:");
            $this->line("  • {$promotions['next_last_friday']['description']}");
        }
        
        $this->line("");
    }
    
    /**
     * Aggiungi prodotto al carrello
     */
    protected function addProductToCart()
    {
        if (!$this->currentCustomer) {
            $this->error('Devi prima eseguire il login come cliente.');
            return;
        }
        
        if (!$this->currentCart) {
            if ($this->confirm('Non hai un carrello attivo. Vuoi crearne uno nuovo?', true)) {
                $this->createNewCart();
            } else {
                return;
            }
        }
        
        // Mostra le promozioni disponibili come informazione per l'utente
        $this->info('=== Prodotti disponibili ===');
        $this->showCurrentPromotions();
        
        $request = new Request();
        $this->simulateAuthentication($this->currentCustomer);
        $response = $this->productController->index($request);
        $responseData = json_decode($response->getContent());
        
        $products = $responseData->data;
        
        $headers = ['ID', 'SKU', 'Nome', 'Categoria', 'Quantità disponibile', 'Prezzo base', 'Promozioni'];
        $rows = [];
        
        foreach ($products as $product) {
            $productObj = Product::find($product->id);
            $promotions = $this->promotionService->getProductPromotions($productObj);
            
            $promoDescriptions = [];
            foreach ($promotions as $promo) {
                $promoDescriptions[] = $promo['description'];
            }
            
            $rows[] = [
                $product->id,
                $product->product_sku,
                $product->product_name,
                $product->category->category,
                $product->quantity,
                '€' . number_format($product->base_price, 2),
                implode(", ", $promoDescriptions)
            ];
        }
        
        $this->table($headers, $rows);
        
        $productMap = [];
        $options = ['Annulla'];
        
        foreach ($products as $product) {
            $label = "Prodotto {$product->id}: {$product->product_name} ({$product->product_sku})";
            $options[] = $label;
            $productMap[$label] = $product->id;
        }
        
        $choice = $this->choice('Seleziona un prodotto da aggiungere', $options, 0);
        
        if ($choice === 'Annulla') {
            $this->info('Operazione annullata.');
            return;
        }
        
        $productId = $productMap[$choice];
        $quantity = $this->ask('Inserisci la quantità', 1);
        
        $request = new Request();
        $request->merge([
            'product_id' => $productId,
            'quantity' => $quantity
        ]);
        
        // Chiamata alla funzione store per aggiungere il prodotto nel carrello
        $response = $this->cartItemController->store($request, $this->currentCart->id);
        $responseData = json_decode($response->getContent());
        
        if ($responseData->success) {
            // Ricarica il carrello corrente
            $this->currentCart = Cart::with(['items.product.category', 'status'])
                                    ->find($this->currentCart->id);
            
            $product = Product::find($productId);
            $this->info("Prodotto {$product->product_name} aggiunto al carrello.");
        } else {
            $this->error("Errore nell'aggiunta del prodotto: {$responseData->message}");
        }
    }
    
    /**
     * Visualizza il carrello
     */
    protected function viewCart()
    {
        if (!$this->currentCart) {
            if ($this->currentCustomer) {
                $this->listUserCarts();
                return;
            } else {
                $this->error('Nessun cliente selezionato.');
                return;
            }
        }
        
        // Simula l'autenticazione dell'utente
        $this->simulateAuthentication($this->currentCustomer);
        
        $response = $this->cartController->show($this->currentCart->id);
        $responseData = json_decode($response->getContent());
        
        if (!$responseData->success) {
            $this->error("Errore nel caricamento del carrello: {$responseData->message}");
            return;
        }
        
        $cart = $responseData->data;
        
        $this->info("=== Dettaglio Carrello ID: {$cart->id} ===");
        $this->info("Cliente: {$this->currentCustomer->email}");
        $this->info("Stato: {$cart->status->status}");
        $this->info("Data creazione: {$cart->created_at}");
        
        if (isset($cart->date_checkout) && $cart->date_checkout) {
            $this->info("Data checkout: {$cart->date_checkout}");
        }
        
        // Mostra le promozioni
        $this->showCurrentPromotions();
        
        // Mostra gli articoli nel carrello
        if (empty($cart->items)) {
            $this->warn('Il carrello è vuoto.');
            return;
        }
        
        $headers = ['ID', 'Prodotto', 'Categoria', 'Quantità', 'Prezzo Base', 'Prezzo calcolato', 'Promozioni'];
        $rows = [];
        
        foreach ($cart->items as $item) {
            $product = $item->product;
            $category = $product->category->category;
            $qty = $item->quantity;
            $baseTotal = $item->discount_details->original_price;
            $calculatedPrice = $item->calculated_price;
            
            // Elenco delle promozioni applicate
            $promotionsText = "Nessuna";
            if (!empty($item->discount_details->applied_promotions)) {
                $promotionsDescriptions = [];
                foreach ($item->discount_details->applied_promotions as $promo) {
                    $promotionsDescriptions[] = $promo->description;
                }
                $promotionsText = implode(", ", $promotionsDescriptions);
            }
            
            $rows[] = [
                $item->id,
                $product->product_name,
                $category,
                $qty,
                '€' . number_format($baseTotal, 2),
                '€' . number_format($calculatedPrice, 2),
                $promotionsText
            ];
        }
        
        $this->table($headers, $rows);
        
        // Riepilogo degli sconti
        $totalSaved = $cart->summary->total_saved;
        $savePercentage = $cart->summary->save_percentage;
        
        $this->info("Prezzo totale (originale): €" . number_format($cart->summary->original_total, 2));
        $this->info("Prezzo totale (scontato): €" . number_format($cart->summary->discounted_total, 2));
        
        if ($totalSaved > 0) {
            $this->info("Hai risparmiato: €" . number_format($totalSaved, 2) . " (" . number_format($savePercentage, 1) . "%)");
        }
    }
    
    /**
     * Lista tutti i carrelli dell'utente
     */
    protected function listUserCarts()
    {
        if (!$this->currentCustomer) {
            $this->error('Devi prima eseguire il login come cliente.');
            return;
        }
        
        // Simula l'autenticazione dell'utente
        $this->simulateAuthentication($this->currentCustomer);
        
        $response = $this->cartController->index();
        $responseData = json_decode($response->getContent());
        
        if (!$responseData->success) {
            $this->error("Errore nel recupero dei carrelli: " . ($responseData->message ?? "Errore sconosciuto"));
            return;
        }
        
        $carts = $responseData->data;
        
        // Numero dei prodotti presenti nel carrello
        foreach ($carts as $cart) {
            $cart->items_count = CartItem::where('cart_id', $cart->id)->count();
        }
        
        if (empty($carts)) {
            $this->warn('Non hai ancora creato nessun carrello.');
            if ($this->confirm('Vuoi creare un nuovo carrello ora?', true)) {
                $this->createNewCart();
            }
            return;
        }
        
        $this->info("\n=== I tuoi carrelli ===");
        
        $headers = ['ID', 'Stato', 'Articoli', 'Valore', 'Data creazione', 'Checkout'];
        $rows = [];
        
        foreach ($carts as $cart) {
            $rows[] = [
                $cart->id,
                $cart->status->status,
                $cart->items_count ?? 0,
                '€' . number_format($cart->total_price, 2),
                $cart->created_at,
                isset($cart->date_checkout) && $cart->date_checkout ? $cart->date_checkout : '-'
            ];
        }
        
        $this->table($headers, $rows);
        
        if ($this->confirm('Vuoi selezionare uno di questi carrelli?', true)) {
            $this->switchCart();
        }
    }
    
    /**
     * Cambia il carrello attualmente selezionato
     */
    protected function switchCart()
    {
        if (!$this->currentCustomer) {
            $this->error('Devi prima eseguire il login come cliente.');
            return;
        }
        
        // Simula l'autenticazione dell'utente
        $this->simulateAuthentication($this->currentCustomer);
        
        $response = $this->cartController->index();
        $responseData = json_decode($response->getContent());
        
        if (!$responseData->success) {
            $this->error("Errore nel recupero dei carrelli: " . ($responseData->message ?? "Errore sconosciuto"));
            return;
        }
        
        $carts = $responseData->data;
        
        // Numero dei prodotti presenti nel carrello
        foreach ($carts as $cart) {
            $cart->items_count = CartItem::where('cart_id', $cart->id)->count();
        }
        
        if (empty($carts)) {
            $this->warn('Non hai ancora creato nessun carrello.');
            return;
        }
        
        $cartMap = [];
        $options = ['Annulla'];
        
        foreach ($carts as $cart) {
            $status = $cart->status->status;
            $items = $cart->items_count ?? 0;
            $label = "Carrello {$cart->id}: {$status} - {$items} articoli - €" . number_format($cart->total_price, 2);
            if (isset($cart->date_checkout) && $cart->date_checkout) {
                $label .= " (checkout: " . date('d/m/Y H:i', strtotime($cart->date_checkout)) . ")";
            }
            $options[] = $label;
            $cartMap[$label] = $cart->id;
        }
        
        $choice = $this->choice('Seleziona un carrello', $options, 0);
        
        if ($choice === 'Annulla') {
            $this->info('Operazione annullata.');
            return;
        }
        
        $cartId = $cartMap[$choice];
        $this->currentCart = Cart::with(['items.product.category', 'status'])
                                ->find($cartId);
        
        $this->info("Hai selezionato il carrello ID: {$this->currentCart->id}");
    }
    
    /**
     * Checkout del carrello
     */
    protected function checkoutCart()
    {
        if (!$this->currentCart) {
            $this->error('Nessun carrello selezionato.');
            return;
        }
        
        if ($this->currentCart->items->isEmpty()) {
            $this->error('Il carrello è vuoto. Aggiungi prodotti prima di effettuare il checkout.');
            return;
        }
        
        if ($this->currentCart->cart_status_id == CartStatus::where('status', 'checkout')->first()->id) {
            $this->error('Checkout già eseguito, carrello non disponibile.');
            return;
        }
        
        // Mostra riepilogo del carrello prima del checkout
        $this->info("\n=== RIEPILOGO ORDINE ===");
        $this->viewCart();
        
        if (!$this->confirm("\nSei sicuro di voler procedere con il checkout?", true)) {
            $this->info('Checkout annullato.');
            return;
        }
        
        // Simula l'autenticazione dell'utente
        $this->simulateAuthentication($this->currentCustomer);
        
        $response = $this->cartController->checkout($this->currentCart->id);
        $responseData = json_decode($response->getContent());
        
        if ($responseData->success) {
            // Ricarica il carrello con i dati aggiornati
            $cart = $responseData->data;
            
            $this->line("\n=== Riepilogo finale ===");
            $this->info("Checkout completato con successo!");
            $this->info("Data checkout: {$cart->date_checkout}");
            $this->info("Prezzo totale finale: €" . number_format($cart->total_price, 2));
            
            $this->info("\nOrdine #{$cart->id} completato con successo!");
            
            // Genera ID di ordine fittizio
            $orderNumber = 'ORD-' . date('Ymd') . '-' . $cart->id;
            $this->info("Numero ordine: {$orderNumber}");
            $this->info("Grazie per il tuo acquisto!");
            
            // Dopo il checkout, imposta il carrello corrente a null
            $this->currentCart = null;
        } else {
            $this->error("Errore durante il checkout: {$responseData->message}");
        }
    }
    
    /**
     * Modifica il contenuto del carrello
     */
    protected function editCart()
    {
        if (!$this->currentCart) {
            $this->error('Nessun carrello selezionato.');
            return;
        }
        
        if ($this->currentCart->cart_status_id == CartStatus::where('status', 'checkout')->first()->id) {
            $this->error('Questo carrello è già stato completato e non può essere modificato.');
            return;
        }
        
        // Mostra il contenuto del carrello prima di modificarlo
        $this->info("\n=== Contenuto del carrello ===");
        
        // Simula l'autenticazione dell'utente
        $this->simulateAuthentication($this->currentCustomer);
        
        // Recupera i prodotti presenti ne carrello
        $response = $this->cartItemController->index($this->currentCart->id);
        $responseData = json_decode($response->getContent());
        
        if (!$responseData->success) {
            $this->error("Errore nel caricamento degli elementi del carrello: {$responseData->message}");
            return;
        }
        
        $items = $responseData->data;
        
        if (empty($items)) {
            $this->warn('Il carrello è vuoto.');
            return;
        }
        
        $itemMap = [];
        $i = 1;
        
        $this->line("\nArticoli nel carrello:");
        foreach ($items as $item) {
            $this->line("{$i}. {$item->product->product_name} - {$item->quantity} pz - €" . 
                      number_format($item->calculated_price, 2));
            $itemMap[$i] = $item;
            $i++;
        }
        
        $options = [
            'Modifica quantità di un prodotto',
            'Rimuovi un prodotto',
            'Svuota carrello',
            'Indietro'
        ];
        
        $choice = $this->choice('Cosa vuoi fare?', $options, 0);
        
        switch ($choice) {
            case 'Modifica quantità di un prodotto':
                $this->editCartItemQuantity($itemMap);
                break;
                
            case 'Rimuovi un prodotto':
                $this->removeCartItem($itemMap);
                break;
                
            case 'Svuota carrello':
                if ($this->confirm('Sei sicuro di voler svuotare il carrello?', false)) {
                    $response = $this->cartItemController->clear($this->currentCart->id);
                    $responseData = json_decode($response->getContent());
                    
                    if ($responseData->success) {
                        // Ricarica il carrello
                        $this->currentCart = Cart::find($this->currentCart->id);
                        $this->info('Carrello svuotato con successo.');
                    } else {
                        $this->error("Errore durante lo svuotamento del carrello: {$responseData->message}");
                    }
                } else {
                    $this->info('Operazione annullata.');
                }
                break;
                
            case 'Indietro':
                $this->info('Operazione annullata.');
                break;
        }
    }
    
    /**
     * Modifica la quantità di un prodotto nel carrello
     */
    protected function editCartItemQuantity($itemMap)
    {
        $itemNumbers = array_keys($itemMap);
        $itemChoices = array_map(function($num) use ($itemMap) {
            $item = $itemMap[$num];
            return "{$num}. {$item->product->product_name} ({$item->quantity} pz)";
        }, $itemNumbers);
        
        $itemChoices[] = 'Annulla';
        
        $choice = $this->choice('Seleziona il prodotto da modificare', $itemChoices);
        
        if ($choice === 'Annulla') {
            $this->info('Operazione annullata.');
            return;
        }
        
        $itemNumber = (int) substr($choice, 0, strpos($choice, '.'));
        $item = $itemMap[$itemNumber];
        
        $newQuantity = $this->ask('Inserisci la nuova quantità', $item->quantity);
        
        if ($newQuantity <= 0) {
            if ($this->confirm('La quantità è 0 o negativa. Vuoi rimuovere questo prodotto dal carrello?', true)) {
                $response = $this->cartItemController->destroy($this->currentCart->id, $item->id);
                $responseData = json_decode($response->getContent());
                
                if ($responseData->success) {
                    // Ricarica il carrello
                    $this->currentCart = Cart::find($this->currentCart->id);
                    $this->info("Prodotto rimosso dal carrello.");
                } else {
                    $this->error("Errore durante la rimozione del prodotto: {$responseData->message}");
                }
            } else {
                $this->info('Operazione annullata.');
                return;
            }
        } else {
            $request = new Request();
            $request->merge(['quantity' => $newQuantity]);
            
            // Simula l'autenticazione dell'utente
            $this->simulateAuthentication($this->currentCustomer);
            
            $response = $this->cartItemController->update($request, $this->currentCart->id, $item->id);
            $responseData = json_decode($response->getContent());
            
            if ($responseData->success) {
                // Ricarica il carrello
                $this->currentCart = Cart::find($this->currentCart->id);
                $this->info("Quantità aggiornata a {$newQuantity}.");
            } else {
                $this->error("Errore durante l'aggiornamento della quantità: {$responseData->message}");
            }
        }
    }
    
    /**
     * Rimuove un prodotto dal carrello
     */
    protected function removeCartItem($itemMap)
    {
        $itemNumbers = array_keys($itemMap);
        $itemChoices = array_map(function($num) use ($itemMap) {
            $item = $itemMap[$num];
            return "{$num}. {$item->product->product_name} ({$item->quantity} pz)";
        }, $itemNumbers);
        
        $itemChoices[] = 'Annulla';
        
        $choice = $this->choice('Seleziona il prodotto da rimuovere', $itemChoices);
        
        if ($choice === 'Annulla') {
            $this->info('Operazione annullata.');
            return;
        }
        
        $itemNumber = (int) substr($choice, 0, strpos($choice, '.'));
        $item = $itemMap[$itemNumber];
        
        if ($this->confirm("Sei sicuro di voler rimuovere \"{$item->product->product_name}\" dal carrello?", true)) {
            // Simula l'autenticazione dell'utente
            $this->simulateAuthentication($this->currentCustomer);
            
            $response = $this->cartItemController->destroy($this->currentCart->id, $item->id);
            $responseData = json_decode($response->getContent());
            
            if ($responseData->success) {
                // Ricarica il carrello
                $this->currentCart = Cart::find($this->currentCart->id);
                $this->info("Prodotto rimosso dal carrello.");
            } else {
                $this->error("Errore durante la rimozione del prodotto: {$responseData->message}");
            }
        } else {
            $this->info('Operazione annullata.');
        }
    }
}