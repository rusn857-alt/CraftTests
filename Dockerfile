FROM php:7.4-apache

# Устанавливаем расширения
RUN docker-php-ext-install pdo pdo_mysql

# Включаем mod_rewrite
RUN a2enmod rewrite

# Копируем файлы проекта
COPY . /var/www/html/

# Устанавливаем права
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Создаем скрипт для автоматической установки
RUN echo '#!/bin/bash' > /usr/local/bin/init-system.sh && \
    echo 'sleep 10' >> /usr/local/bin/init-system.sh && \
    echo 'php /var/www/html/install.php --auto' >> /usr/local/bin/init-system.sh && \
    chmod +x /usr/local/bin/init-system.sh

# Запускаем скрипт инициализации при старте
CMD ["sh", "-c", "/usr/local/bin/init-system.sh && apache2-foreground"]