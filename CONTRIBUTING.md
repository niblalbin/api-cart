## Cart API – Analisi e sviluppo

### Obiettivo del progetto
Il cliente richiede la creazione di un sistema per la gestione del flusso d’acquisto nell’e-commerce.  
La parte più laboriosa e complicata è il **calcolo del prezzo**, che deve essere definito in base a regole di discount per arrivare al prezzo finale per cliente.

### Funzionalità richieste
- **Connessione:** Autenticazione del cliente tramite email e password.
- **Identificazione Cliente:** Distinzione tra cliente *privato* e *business*.
- **Visualizzazione Prezzi:** Mostrare la lista dei prezzi in base al tipo di cliente.
- **Scelta e Configurazione Prodotto:** Selezione e personalizzazione del prodotto.
- **Selezione Quantità:** Scelta del numero di unità.
- **Gestione Carrello:** Aggiunta del prodotto al carrello.
- **Checkout:** Acquisto dei prodotti presenti nel carrello.

**Riferimento prodotti:**  
[Visualizza Prodotti](https://arbo.it/ricambi/ricambi-commerciali-caldaie-e-bruciatori/bruciatori/)

### Struttura del database

- **customers:**  
  - `id` (PK)  
  - `customer_role_id` (FK)  
  - `email` (string)  
  - `psw` (string)

- **customers_role:**  
  - `id` (PK)  
  - `customer_role` (valori: "private" o "business")

- **carts:**  
  - `id` (PK)  
  - `ecommerce_id`  
  - `customer_id` (FK)  
  - `cart_status_id` (FK)  
  - `created_at`  
  - `updated_at`  
  - `date_checkout`  
  - `total_price`

- **cart_items:**  
  - `id` (PK)  
  - `cart_id` (FK)  
  - `product_id` (FK)  
  - `quantity`  
  - `calculated_price`

- **cart_status:**  
  - `id` (PK)  
  - `status` ("created" (1), "building" (2), "checkout" (3))

- **products:**  
  - `id` (PK)  
  - `ecommerce_id`  
  - `product_sku`  
  - `product_name`  
  - `product_category_id` (FK)  
  - `quantity`  
  - `base_price`

- **product_category:**  
  - `id` (PK)  
  - `category` ("spare_parts" (1), "refrigeration" (2), "photovoltaic" (3))

**Note:**
- L’autenticazione avviene tramite email e password.
- Per semplificare, sono state create tabelle specifiche (es. `cart_status` per gli stati del carrello).
- La tabella `products` serve a tenere traccia del catalogo e viene referenziata in `cart_items`.
- La tabella `product_category` gestisce le categorie dei prodotti.
- Ho valutato l’idea di creare una tabella `discounts` o `promotions` per gli sconti, ma in fase di analisi la logica si è rivelata troppo complessa, quindi l'ho gestita direttamente nel backend.
- Non è definita la differenza di prezzo tra clienti *private* e *business*.  
- Avrei potuto esternalizzare anche l'`ecommerce_id` con una tabella apposita, ma per risparmiare tempo ho evitato.
- Sarebbe utile anche avere un campo `order_id` nella tabella `carts` (o in una tabella separata) per identificare l'ordine una volta effettuato il checkout.

---

### Stack e tecnologie
La soluzione è sviluppata con un'architettura containerizzata.  
**Container previsti:**
- **MySQL:** Gestione del database.
- **Web server:** Esecuzione del PHP con server Apache integrato.
  
**Backend:**
- **Laravel:** Framework PHP per lo sviluppo delle API.
- **Sanctum:** Sistema di autenticazione per le API già integrato in Laravel.

#### Docker compose
Il file `docker-compose.yml` definisce i servizi necessari:
- **MySQL:**  
    - Contenitore dedicato al database.  
    - Variabili d'ambiente (password, database, utente) configurate.
    - Volume per la persistenza dei dati.
    - Healthcheck per verificare la disponibilità.
- **Web:**  
    - Contenitore per il backend basato su PHP e Apache.
    - Il codice sorgente, nella directory `src`, è montato nel container.

#### Dockerfile
Il `Dockerfile` si occupa di:
- Installare le dipendenze necessarie.
- Abilitare il modulo `mod_rewrite` per Apache.
- Installare Composer per gestire le dipendenze PHP.
- Configurare PHP per lo sviluppo (visualizzazione errori, etc.).
- Impostare la directory di lavoro su `/var/www/html`.
- Copiare lo script di inizializzazione `init-project.sh` e renderlo eseguibile.
- Avviare lo script di inizializzazione come comando principale.

#### Script di inizializzazione (`init-project.sh`)
Questo script, posizionato nella directory `scripts` alla radice del progetto, automatizza il setup del backend:
- Imposta la directory di lavoro.
- Installa le dipendenze con Composer (se non già presenti).
- Crea e configura il file `.env` copiando `.env.example` e aggiornando i parametri di connessione al database; genera anche la chiave dell'applicazione.
- Attende che MySQL sia disponibile.
- Verifica e, se necessario, crea il database e assegna i permessi.
- Esegue migrazioni e seeders con `php artisan migrate:fresh --seed --force`.
- Imposta i permessi delle directory (storage, bootstrap/cache).
- Avvia Apache in modalità foreground.

---

### Interfaccia utente
Ho creato un'interfaccia da terminale per fare le operazioni base.  
Si accede all'interfaccia tramite artisan con il comando:  
`php artisan cart:shell`

Dalla schermata principale, l'utente può:
- Effettuare il login (l'autenticazione è "fittizia" per simulare un contesto web da terminale).
- Visualizzare l'elenco dei prodotti.
- Chiudere l'interfaccia ed uscire dall'applicazione.

Per acquistare, aggiungere un prodotto al carrello e usare le altre funzioni, l'utente deve prima autenticarsi.  
L'autenticazione si basa su Sanctum, che gestisce le sessioni e valida le richieste (tramite un middleware in api.php) prima di indirizzarle al DB.

### Struttura del progetto
Seguendo la logica di Laravel, ho usato i comandi di Artisan per generare i model di tutte le tabelle, definendo in ogni file i campi, i tipi e le relazioni.  
Il passo successivo è stato generare le migrations e poi i seeders per popolare alcune tabelle con dati "fittizi" per i test.

  Es. creazione di un model: `php artisan make:model Customer`  
  Es. creazione di una migration: `php artisan make:migration create_customers_table`  
  Es. creazione di un seeder: `php artisan make:seeder CustomerSeeder`

#### Gestione delle operazioni 
  - **AuthController:** Gestisce il login e il logout.  
  - **CartController:** Coordina la creazione, visualizzazione e checkout del carrello, compreso il ricalcolo dei prezzi al checkout.  
  - **CartItemController:** Gestisce l’aggiunta, la modifica e la rimozione dei prodotti nel carrello.  
  - **ProductController:** Fornisce l’elenco dei prodotti e i dettagli, incluse le promozioni attive.

#### Servizi di business
- **PriceCalculationService:** Calcola il prezzo applicando sconti basati su quantità, categorie e promozioni (es. prezzo fisso l'ultimo venerdì del mese).  
- **PromotionService:** Gestisce la logica per individuare e applicare le promozioni attive, sia per quantità che per categorie, mostrando il risparmio al cliente.

---

### Workspace postman
Ho configurato un workspace in Postman, organizzato in directory (Auth, Cart, Products) per mantenere una struttura chiara in base alle aree funzionali.
- **Auth:** Contiene gli endpoint per il login ed il logout.
- **Cart:** Raccoglie gli endpoint per la gestione del carrello, dalla creazione al checkout.
- **Products:** Include gli endpoint per ottenere i dettagli dei prodotti e le promozioni attive.
Le chiamate sono collegate tra loro tramite degli script che impostano delle variabili globali, usate poi nelle varie richieste.