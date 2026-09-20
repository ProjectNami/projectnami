#!/bin/bash
# Azure App Service Linux PHP 8.x: persist nginx WordPress rewrites across restarts.
# Portal → Configuration → General settings → Startup Command:
#   /bin/bash /home/site/wwwroot/startup.sh

set -e
SRC="/home/site/wwwroot/nginx.azure.conf"

if [ ! -f "$SRC" ]; then
    echo "nginx.azure.conf not found at $SRC" >&2
    exit 1
fi

if [ -e /etc/nginx/sites-enabled/default ]; then
    cp "$SRC" /etc/nginx/sites-enabled/default
elif [ -e /etc/nginx/conf.d/default.conf ]; then
    cp "$SRC" /etc/nginx/conf.d/default.conf
else
    echo "Could not find nginx site config to replace" >&2
    exit 1
fi

nginx -t
nginx -s reload 2>/dev/null || service nginx reload
