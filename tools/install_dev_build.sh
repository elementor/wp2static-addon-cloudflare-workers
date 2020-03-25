#!/bin/bash

######################################
##
## Build WP2Static Zip Deployment Addon
##
## script archive_name dont_minify
##
## places archive in $HOME/Downloads
##
######################################

# run script from project root
EXEC_DIR=$(pwd)

TMP_DIR=$HOME/plugintmp

rm -Rf $TMP_DIR
mkdir -p $TMP_DIR

rm -Rf $TMP_DIR/wp2static-addon-cloudflare-workers
mkdir $TMP_DIR/wp2static-addon-cloudflare-workers


# clear dev dependencies
#rm -Rf $EXEC_DIR/vendor/*
# load prod deps and optimize loader
#composer install --no-dev --optimize-autoloader


# cp all required sources to build dir
cp -r $EXEC_DIR/wp2static-addon-cloudflare-workers.php $TMP_DIR/wp2static-addon-cloudflare-workers/
cp -r $EXEC_DIR/src $TMP_DIR/wp2static-addon-cloudflare-workers/
cp -r $EXEC_DIR/composer.json $TMP_DIR/wp2static-addon-cloudflare-workers/
cp -r $EXEC_DIR/README.txt $TMP_DIR/wp2static-addon-cloudflare-workers/
cp -r $EXEC_DIR/views $TMP_DIR/wp2static-addon-cloudflare-workers/

cd $TMP_DIR/wp2static-addon-cloudflare-workers

ls -lah

composer install --no-dev --prefer-dist --optimize-autoloader -vvv --profile

# tidy permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;

rm $TMP_DIR/wp2static-addon-cloudflare-workers.zip

cd $TMP_DIR/wp2static-addon-cloudflare-workers

zip -r -9 $TMP_DIR/wp2static-addon-cloudflare-workers.zip .

cd /var/www/htdocs

doas rm -Rf /var/www/htdocs/wp-content/plugins/wp2static-addon-cloudflare-workers

wp plugin install --activate $TMP_DIR/wp2static-addon-cloudflare-workers.zip
