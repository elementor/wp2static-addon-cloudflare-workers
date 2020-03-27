#!/bin/bash

##########################################
##
## Copy src files without composer install
##
## script archive_name dont_minify
##
## places archive in $HOME/Downloads
##
##########################################

# run script from project root
EXEC_DIR=$(pwd)
PLUGIN_DIR=/var/www/htdocs/wp-content/plugins/

mkdir -p $PLUGIN_DIR/wp2static-addon-cloudflare-workers/

# cp all required sources to build dir
cp -r $EXEC_DIR/wp2static-addon-cloudflare-workers.php $PLUGIN_DIR/wp2static-addon-cloudflare-workers/
cp -r $EXEC_DIR/src $PLUGIN_DIR/wp2static-addon-cloudflare-workers/
cp -r $EXEC_DIR/README.txt $PLUGIN_DIR/wp2static-addon-cloudflare-workers/
cp -r $EXEC_DIR/views $PLUGIN_DIR/wp2static-addon-cloudflare-workers/
cp -r $EXEC_DIR/vendor $PLUGIN_DIR/wp2static-addon-cloudflare-workers/

cd $PLUGIN_DIR/wp2static-addon-cloudflare-workers

# tidy permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
