set -e

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
FILE="/backups/${MYSQL_DATABASE}_${TIMESTAMP}.sql"

echo "Gerando backup em ${FILE}..."
mysqldump --no-tablespaces -h db -u backup_user -p"${MYSQL_BACKUP_PASSWORD}" "${MYSQL_DATABASE}" \
  > "${FILE}"
echo "Backup concluído: ${FILE}"