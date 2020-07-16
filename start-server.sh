#!/bin/sh
php -v
composer install;
pwd;
php -S localhost:8000 -t public
