#!/bin/sh
set -e

# O Docker injeta as env vars (MYSQL_DATABASE, MYSQL_BACKUP_PASSWORD etc.)
# so no processo principal (PID 1). O crond, ao disparar jobs agendados,
# NAO herda esse ambiente automaticamente - por isso os backups via cron
# saiam vazios enquanto o "docker compose exec" funcionava normalmente.
#
# Aqui gravamos o ambiente atual num arquivo que backup.sh/restore.sh
# vao "sourcear" no inicio de cada execucao, seja via cron ou manual.
printenv | sed -n "s/^\(MYSQL_[A-Z_]*\)=\(.*\)$/export \1='\2'/p" > /etc/container.env
chmod 600 /etc/container.env

exec "$@"