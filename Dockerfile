FROM php:8.2-cli

# Install MySQL extensions
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Set working directory
WORKDIR /app

# Copy project files
COPY . .

# Railway provides PORT automatically
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t ."]
