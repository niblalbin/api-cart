#!/bin/bash

# Imposta la directory di lavoro
cd /var/www/html

# Verifica se le dipendenze sono già installate
if [ ! -d "vendor" ]; then
    echo "Installazione delle dipendenze con Composer..."
    composer install --no-interaction --optimize-autoloader
    echo "Dipendenze installate con successo!"
else
    echo "Le dipendenze sono già installate."
fi

# Crea il file .env se non esiste
if [ ! -f ".env" ]; then
    echo "Creazione del file .env..."
    cp .env.example .env
    
    # Configurazione dell'ambiente
    sed -i 's/DB_HOST=127.0.0.1/DB_HOST=mysql/g' .env
    sed -i 's/DB_DATABASE=laravel/DB_DATABASE=cart_api/g' .env
    sed -i 's/DB_USERNAME=root/DB_USERNAME=cart_user/g' .env
    sed -i 's/DB_PASSWORD=/DB_PASSWORD=cart_psw/g' .env
    
    # Genera la chiave dell'applicazione
    php artisan key:generate
    
    echo "File .env creato con successo!"
else
    echo "Il file .env esiste già."
fi

echo "Attesa per MySQL..."
until mysqladmin ping -h mysql --silent -u cart_user -pcart_psw; do
    echo "MySQL non ancora disponibile - attendi..."
    sleep 2
done
echo "MySQL è pronto!"

# Verifica l'esistenza del database e crealo se necessario
echo "Verifico l'esistenza del database..."
if ! mysql -h mysql -u cart_user -pcart_psw -e "USE cart_api;" 2>/dev/null; then
    echo "Il database non esiste, lo creo..."
    mysql -h mysql -u root -proot_cart_psw -e "CREATE DATABASE IF NOT EXISTS cart_api;"
    mysql -h mysql -u root -proot_cart_psw -e "GRANT ALL PRIVILEGES ON cart_api.* TO 'cart_user'@'%';"
    mysql -h mysql -u root -proot_cart_psw -e "FLUSH PRIVILEGES;"
    echo "Database cart_api creato e permessi assegnati."
fi

# Esecuzione migrazioni e seeder
echo "Esecuzione migrazioni e seeder..."
php artisan migrate:fresh --seed --force
echo "Migrazioni e seeder eseguiti!"

# Imposta permessi
echo "Impostazione permessi..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html/storage
chmod -R 755 /var/www/html/bootstrap/cache

echo "Inizializzazione completata!"

# Avvio Apache
apache2-foreground