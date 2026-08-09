#!/bin/sh
set -e


[ -f /etc/container.env ] && . /etc/container.env

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILE="/backups/${MYSQL_DATABASE}_${TIMESTAMP}.sql"

echo "Gerando backup em ${FILE}..."
mysqldump --no-tablespaces -h ${MYSQL_HOST} -u backup_user -p"${MYSQL_BACKUP_PASSWORD}" "${MYSQL_DATABASE}" \
  > "${FILE}"
echo "Backup concluído: ${FILE}"