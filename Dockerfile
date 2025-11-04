# Use official PHP image
FROM php:8.1-cli

# Set working directory
WORKDIR /var/www/html

# Copy all PHP files to the container
COPY . /var/www/html/

# Set permissions
RUN chmod -R 755 /var/www/html

# Create startup script that uses PHP built-in server with PORT
RUN echo '#!/bin/bash' > /start.sh && \
    echo 'PORT=${PORT:-80}' >> /start.sh && \
    echo 'php -S 0.0.0.0:$PORT -t /var/www/html' >> /start.sh && \
    chmod +x /start.sh

# Expose port (Render will set PORT env variable)
EXPOSE 80

# Start PHP built-in server
CMD ["/start.sh"]
