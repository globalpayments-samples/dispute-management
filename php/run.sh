#!/bin/bash

set -e

composer install

PORT="${PORT:-8000}"
php -S 0.0.0.0:$PORT router.php
