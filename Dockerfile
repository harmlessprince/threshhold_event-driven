FROM php:8.3-fpm

# Arguments defined in docker-compose.yml
ARG user
ARG uid

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libpq-dev \
    zip \
    unzip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd zip

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Add user for laravel application
RUN groupadd -g ${uid} ${user}
RUN useradd -u ${uid} -ms /bin/bash -g ${user} ${user}

# Copy existing application directory contents
COPY . /var/www

# Copy existing application directory permissions
COPY --chown=${user}:${user} . /var/www

# Change current user to the app user
USER ${user}

# Expose port 9000 and start php-fpm server
EXPOSE 9000
CMD ["php-fpm"]
