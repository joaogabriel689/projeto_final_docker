#!/bin/sh

set -eu

URL="${TARGET_URL:-http://nginx:80/}"

mkdir -p /results

echo "Teste de carga iniciado em $(date)" > /results/latest.txt
echo "URL: $URL" >> /results/latest.txt

echo "" >> /results/latest.txt
echo "===== TESTE 100 / 5 =====" >> /results/latest.txt
ab -n 100 -c 5 "$URL" >> /results/latest.txt

echo "" >> /results/latest.txt
echo "===== TESTE 1000 / 20 =====" >> /results/latest.txt
ab -n 1000 -c 20 "$URL" >> /results/latest.txt

echo "" >> /results/latest.txt
echo "===== TESTE 5000 / 50 =====" >> /results/latest.txt
ab -n 5000 -c 50 "$URL" >> /results/latest.txt

echo ""
echo "Resultado salvo em /results/latest.txt"