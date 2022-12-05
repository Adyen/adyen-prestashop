#!/bin/bash

# Cleanup any leftovers
echo -e "\e[32mCleaning up...\e[0m"
rm -rf ./adyenofficial

# Create deployment source
echo -e "\e[32mSTEP 1:\e[0m Copying plugin source..."
mkdir ./adyenofficial
cp -r ./* ./adyenofficial

# Ensure proper composer dependencies
echo -e "\e[32mSTEP 2:\e[0m Installing composer dependencies..."
# remove resources that will be copied from the core in the post-install script
rm -rf ./adyenofficial/.github
rm -rf ./adyenofficial/tools
rm -rf ./adyenofficial/vendor
rm -rf ./adyenofficial/PluginInstallation
rm -rf ./adyenofficial/adyenofficial

cd ./adyenofficial
composer install --no-dev

# get plugin version
echo -e "\e[32mSTEP 6:\e[0m Reading module version..."

version="$1"
if [ "$version" = "" ]; then
    version=$(php -r "echo json_decode(file_get_contents('src/composer.json'), true)['version'];")
    if [ "$version" = "" ]; then
        echo "Please enter new plugin version (leave empty to use root folder as destination) [ENTER]:"
        read version
    else
      echo -e "\e[35mVersion read from the composer.json file: $version\e[0m"
    fi
fi

# Create plugin archive
cd ..
echo -e "\e[32mSTEP 7:\e[0m Creating new archive..."
zip -r -q  adyenofficial.zip ./adyenofficial

if [ ! -d ./PluginInstallation/ ]; then
  mkdir ./PluginInstallation/
fi
if [ ! -d ./PluginInstallation/"$version"/ ]; then
    mkdir ./PluginInstallation/"$version"/
fi

mv ./adyenofficial.zip ./PluginInstallation/${version}/
echo -e "\e[34;5;40mSUCCESS!\e[0m"
echo -e "\e[93mNew release created under: $PWD/PluginInstallation/$version"
