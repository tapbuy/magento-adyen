#!/usr/bin/env bash
# Runs inside the tapbuy-ci-php83 container for the adyen module.
# Expects volumes:
#   /module                      — adyen source (read-only)
#   /tapbuy-redirect-tracking    — redirect-tracking source (read-only)
#   /tapbuy-data-scrubber        — data-scrubber source (read-only)
#   /thirdparty-adyen            — Adyen/adyen-magento2 (read-only)
set -euo pipefail

if [ ! -f /magento/vendor/autoload.php ]; then
    echo "Installing Magento 2.4.7-p5 (first run — this takes a few minutes)..."
    find /magento -mindepth 1 -delete
    composer create-project \
        --repository-url=https://repo.magento.com/ \
        magento/project-community-edition=2.4.7-p5 /magento \
        --no-dev --no-scripts --prefer-dist --no-interaction
    composer -d /magento config audit.block-insecure false
    composer -d /magento require --dev phpunit/phpunit:~9.6.0 \
        --no-scripts --no-interaction
fi

mkdir -p /magento/vendor/tapbuy
rm -rf /magento/vendor/tapbuy/adyen
mkdir -p /magento/vendor/tapbuy/adyen
cp -rT /module /magento/vendor/tapbuy/adyen
rm -rf /magento/vendor/tapbuy/redirect-tracking
mkdir -p /magento/vendor/tapbuy/redirect-tracking
cp -rT /tapbuy-redirect-tracking /magento/vendor/tapbuy/redirect-tracking
rm -rf /magento/vendor/tapbuy/data-scrubber
mkdir -p /magento/vendor/tapbuy/data-scrubber
cp -rT /tapbuy-data-scrubber /magento/vendor/tapbuy/data-scrubber

mkdir -p /magento/vendor/adyen
rm -rf /magento/vendor/adyen/module-payment
mkdir -p /magento/vendor/adyen/module-payment
cp -rT /thirdparty-adyen /magento/vendor/adyen/module-payment

cat > /magento/vendor/tapbuy/bootstrap.php << 'BOOTSTRAP'
<?php
declare(strict_types=1);
require_once __DIR__ . '/../../dev/tests/unit/framework/bootstrap.php';
$autoloader = include __DIR__ . '/../../vendor/autoload.php';
$autoloader->addPsr4('Tapbuy\\Adyen\\', __DIR__ . '/adyen/');
$autoloader->addPsr4('Tapbuy\\RedirectTracking\\', __DIR__ . '/redirect-tracking/');
$autoloader->addPsr4('Tapbuy\\DataScrubber\\', __DIR__ . '/data-scrubber/src/');
$autoloader->addPsr4('Adyen\\Payment\\', __DIR__ . '/../adyen/module-payment/');
BOOTSTRAP

cd /magento
echo ""
echo "========================================================="
echo " PHPUnit -- adyen"
echo "========================================================="
exec php vendor/bin/phpunit \
    --bootstrap vendor/tapbuy/bootstrap.php \
    vendor/tapbuy/adyen/Test/Unit/
