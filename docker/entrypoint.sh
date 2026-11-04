#!/bin/sh
set -e

# Em EKS a NEWRELIC_LICENSE_KEY vem do Secret do K8s (secret de organização
# no GitHub). Sem ela (dev local), o agente fica inerte e o PHP sobe normalmente.
if [ -n "$NEWRELIC_LICENSE_KEY" ]; then
  ini_file=$(find /etc /usr/local/etc -iname "newrelic.ini" 2>/dev/null | head -n1)
  if [ -n "$ini_file" ]; then
    sed -i "s/^newrelic.license = .*/newrelic.license = \"${NEWRELIC_LICENSE_KEY}\"/" "$ini_file"
    sed -i "s/^newrelic.appname = .*/newrelic.appname = \"${NEWRELIC_APPNAME:-POS Tech}\"/" "$ini_file"
  fi
  # Não precisa subir o newrelic-daemon manualmente — a extensão faz isso
  # sozinha (spawna o daemon companion na primeira requisição/execução,
  # apontando pro socket em newrelic.daemon.address).
fi

exec "$@"
