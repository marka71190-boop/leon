# Leon_pro — сайт на PHP 8.3 + Apache (база SQLite и загрузки хранятся в volume)
FROM php:8.3-apache

# Модули Apache: .htaccess-правила, кэш статики, заголовки
RUN a2enmod rewrite expires headers \
 && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Настройки PHP и Apache
COPY docker/php.ini /usr/local/etc/php/conf.d/leonpro.ini
COPY docker/apache.conf /etc/apache2/conf-enabled/leonpro.conf

# Файлы сайта
COPY --chown=www-data:www-data . /var/www/html/

# Папки, куда сайт пишет: база и загруженные файлы (подключаются как volume)
RUN mkdir -p /var/www/html/data /var/www/html/uploads \
 && chown -R www-data:www-data /var/www/html/data /var/www/html/uploads \
 && rm -rf /var/www/html/docker /var/www/html/api /var/www/html/vercel.json

VOLUME ["/var/www/html/data", "/var/www/html/uploads"]
EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD php -r 'exit(@file_get_contents("http://127.0.0.1/") === false ? 1 : 0);'
