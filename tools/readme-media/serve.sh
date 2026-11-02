#!/bin/bash
# Test sitesi (8080), sahte guncelleme sunucusu (8091) ve sahte Evolution API (8092) baslatir.
H=$(cd "$(dirname "$0")" && pwd); W=${WPENV:-/tmp/wpenv}
up(){ curl -s -o /dev/null -m 2 "http://127.0.0.1:$1/" ; }
up 8080 || (cd "$W/site" && PHP_CLI_SERVER_WORKERS=4 nohup php -S 127.0.0.1:8080 > "$W/server.log" 2>&1 &)
up 8091 || (cd "$H/env/mock-update" && nohup php -S 127.0.0.1:8091 router.php > /dev/null 2>&1 &)
up 8092 || (cd "$H/env/mock-evolution" && nohup php -S 127.0.0.1:8092 router.php > /dev/null 2>&1 &)
sleep 1; echo "serve: 8080 8091 8092"
