# Utiliser l'image PHP 8.3 comme base
FROM php:8.3

# Installer les dépendances requises
RUN apt-get update -y && apt-get install -y \
    openssl \
    zip \
    unzip \
    git \
    libonig-dev \
    libzip-dev \
    libpng-dev \
    libcurl4-openssl-dev \
    pkg-config \
    libssl-dev \
    libpq-dev \
    postgresql-client \
    && docker-php-ext-install pdo_pgsql mbstring

# Installer Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Définir le dossier de travail
WORKDIR /

# Copier les fichiers de votre projet
COPY . .

# Changer les permissions du dossier de travail
RUN chown -R www-data:www-data /app

# Installer les dépendances Composer sans interaction
RUN composer install --no-interaction --prefer-dist --optimize-autoloader --verbose

# Ajouter le package JWT-Auth
RUN composer require php-open-source-saver/jwt-auth

# Commande pour lancer l'application
CMD ["sh", "-c", "php artisan vendor:publish --provider='PHPOpenSourceSaver\\JWTAuth\\Providers\\LaravelServiceProvider' && \
    php artisan key:generate && \
    php artisan migrate:fresh && \
    php artisan jwt:secret && \
    php artisan serve --host=0.0.0.0 --port=8000"]


# Exposer le port pour Docker
EXPOSE 8000
