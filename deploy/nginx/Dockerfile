FROM itfloworg/itflow:latest

# Replace Apache/mod_php with nginx + php-fpm. The base image stays the source of
# PHP 8.4 and the ITFlow extensions; only the HTTP server changes.
RUN apk add --no-cache nginx php84-fpm \
    && mkdir -p /run/nginx /var/lib/php84/sessions \
    && chown -R apache:apache /var/lib/php84/sessions

COPY nginx.conf /etc/nginx/nginx.conf
COPY php-fpm.conf /etc/php84/php-fpm.d/www.conf
COPY start-nginx.sh /usr/bin/start-nginx.sh
RUN chmod +x /usr/bin/start-nginx.sh

EXPOSE 8080
CMD ["/usr/bin/start-nginx.sh"]
