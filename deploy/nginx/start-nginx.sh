#!/bin/sh
set -e

PHP_FPM="$(command -v php-fpm84 || command -v php-fpm)"
"$PHP_FPM" -F &
exec nginx -g 'daemon off;'
