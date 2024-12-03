#!/bin/bash

# Cleanup any leftovers
echo -e "\e[32mCleaning up...\e[0m"
rm -rf ./adyenofficial.zip
rm -rf ./adyenofficial

# Create deployment source
echo -e "\e[32mSTEP 1:\e[0m Copying plugin source..."
mkdir adyenofficial
cp -r ./src/* adyenofficial

cd ./adyenofficial
echo -e "\e[32mSTEP 2:\e[0m Installing composer dependencies..."
composer install --no-dev
cd ..

echo -e "\e[32mSTEP 3:\e[0m Removing unnecessary files from final release archive..."
rm -rf ./adyenofficial/tests
rm -rf ./adyenofficial/phpunit.xml
rm -rf ./adyenofficial/config.xml
rm -rf ./adyenofficial/vendor/adyen/integration-core/.gitignore
rm -rf ./adyenofficial/vendor/adyen/integration-core/tests
rm -rf ./adyenofficial/vendor/adyen/integration-core/README.md
rm -rf ./adyenofficial/vendor/adyen/integration-core/.github
rm -rf ./adyenofficial/vendor/adyen/php-webhook-module/.github
rm -rf ./adyenofficial/controllers/front/test.php
rm -rf ./adyenofficial/classes/E2ETest
rm -rf ./adyenofficial/vendor/adyen/integration-core/src/BusinessLogic/E2ETest

echo -e "\e[32mSTEP 4:\e[0m Adding PrestaShop mandatory index.php file to all folders..."
php "$PWD/lib/autoindex/index.php" "$PWD/adyenofficial" >/dev/null

# Create plugin archive
echo -e "\e[32mSTEP 5:\e[0m Creating new archive..."
zip -r -q  adyenofficial.zip ./adyenofficial

echo -e "\e[93mNew plugin archive created: $PWD/adyenofficial.zip"


rm -fR ./adyenofficial