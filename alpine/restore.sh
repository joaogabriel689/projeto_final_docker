#!/bin/sh
set -e

# Carrega as env vars gravadas pelo entrypoint.sh (mesma razão do backup.sh)
[ -f /etc/container.env ] && . /etc/container.env

BACKUP_DIR="/backups"
DB_HOST="${MYSQL_HOST}"
DB_NAME="${MYSQL_DATABASE}"
LOG_FILE="/var/log/alpine/restore.log"


DB_USER="${MYSQL_USER}"
DB_PASS="${MYSQL_PASSWORD}"



ARG1="$1"
SKIP_CONFIRM=0

if [ "$ARG1" = "--yes" ]; then
  ARG1=""
  SKIP_CONFIRM=1
fi
if [ "$2" = "--yes" ]; then
  SKIP_CONFIRM=1
fi

if [ -n "$ARG1" ]; then
  BACKUP_FILE="$BACKUP_DIR/$ARG1"
else
  BACKUP_FILE=$(ls -t "$BACKUP_DIR"/"${DB_NAME}"_*.sql 2>/dev/null | head -n1)
fi

if [ -z "$BACKUP_FILE" ] || [ ! -f "$BACKUP_FILE" ]; then
  echo "Nenhum backup encontrado em $BACKUP_DIR (padrão: ${DB_NAME}_*.sql)"
  exit 1
fi

echo "Backup selecionado: $BACKUP_FILE"

if [ "$SKIP_CONFIRM" != "1" ]; then
  echo "Isso vai SOBRESCREVER o banco '$DB_NAME'. Digite 'sim' para confirmar:"
  read -r CONFIRM
  if [ "$CONFIRM" != "sim" ]; then
    echo "Cancelado."
    exit 1
  fi
fi

# Snapshot de seguranca do estado atual antes de sobrescrever
SAFETY_FILE="$BACKUP_DIR/pre_restore_${DB_NAME}_$(date +%Y%m%d_%H%M%S).sql"
echo "Criando snapshot de seguranca em $SAFETY_FILE antes de restaurar..."
mysqldump --no-tablespaces -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" > "$SAFETY_FILE"

echo "Restaurando..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASS" "$DB_NAME" < "$BACKUP_FILE"

mkdir -p /var/log/alpine
echo "$(date '+%Y-%m-%d %H:%M:%S') - restore concluido a partir de: $BACKUP_FILE" >> "$LOG_FILE"
echo "Restore concluido com sucesso: $BACKUP_FILE"