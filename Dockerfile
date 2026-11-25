# StreamFlix — PHP 8.2 + Apache
# Nenhum segredo e embutido na imagem: tudo vem de variaveis de ambiente.
FROM php:8.2-apache

# apt-get update com retry: o espelho do Debian falha de vez em quando e
# derruba o build inteiro. Tenta 3 vezes (com 10s de pausa) antes de desistir.
RUN apt-get update || (sleep 10 && apt-get update) || (sleep 10 && apt-get update) \
    && apt-get install -y --no-install-recommends default-mysql-client \
    && docker-php-ext-install pdo_mysql mysqli curl \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

# O .htaccess do app precisa de AllowOverride All (o padrao e None).
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod 775 /var/www/html/cache /var/www/html/assets/images/system \
    && chmod +x /var/www/html/docker-entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["/var/www/html/docker-entrypoint.sh"]
CMD ["apache2-foreground"]
