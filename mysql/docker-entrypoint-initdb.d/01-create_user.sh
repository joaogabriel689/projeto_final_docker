#!/bin/bash

set -euo pipefail

if [ -z "${MYSQL_ROOT_PASSWORD:-}" ]; then
    echo >&2 "ERRO: MYSQL_ROOT_PASSWORD não definida."
    exit 1
fi

if [ -z "${MYSQL_USER:-}" ]; then
    echo >&2 "ERRO: MYSQL_USER não definida."
    exit 1
fi

if [ -z "${MYSQL_PASSWORD:-}" ]; then
    echo >&2 "ERRO: MYSQL_PASSWORD não definida."
    exit 1
fi

if [ -z "${MYSQL_DATABASE:-}" ]; then
    echo >&2 "ERRO: MYSQL_DATABASE não definida."
    exit 1
fi

echo "Configurando usuário '${MYSQL_USER}'..."

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL

CREATE USER IF NOT EXISTS '${MYSQL_USER}'@'%'
    IDENTIFIED WITH mysql_native_password
    BY '${MYSQL_PASSWORD}';

ALTER USER '${MYSQL_USER}'@'%'
    IDENTIFIED WITH mysql_native_password
    BY '${MYSQL_PASSWORD}';

GRANT ALL PRIVILEGES ON \`${MYSQL_DATABASE}\`.*
    TO '${MYSQL_USER}'@'%';

FLUSH PRIVILEGES;

EOSQL

echo "Usuário '${MYSQL_USER}' configurado com sucesso."

if [ -z "${MYSQL_BACKUP_USER:-}" ]; then
  echo >&2 "ERRO: MYSQL_BACKUP_USER não definida."
  exit 1
fi

if [ -z "${MYSQL_BACKUP_PASSWORD:-}" ]; then
  echo >&2 "ERRO: MYSQL_BACKUP_PASSWORD não definida."
  exit 1
fi

echo "Configurando usuário de backup '${MYSQL_BACKUP_USER}'..."

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
CREATE USER IF NOT EXISTS '${MYSQL_BACKUP_USER}'@'%'
    IDENTIFIED WITH mysql_native_password
    BY '${MYSQL_BACKUP_PASSWORD}';

ALTER USER '${MYSQL_BACKUP_USER}'@'%'
    IDENTIFIED WITH mysql_native_password
    BY '${MYSQL_BACKUP_PASSWORD}';

-- Backup só precisa de SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT
-- (evite GRANT ALL para um usuário só de backup — princípio do menor privilégio)
GRANT SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT
    ON \`${MYSQL_DATABASE}\`.*
    TO '${MYSQL_BACKUP_USER}'@'%';

FLUSH PRIVILEGES;
EOSQL

echo "Usuário de backup configurado com sucesso."

mysql -u root -p"${MYSQL_ROOT_PASSWORD}" \
    -e "SELECT user, host, plugin FROM mysql.user WHERE user = '${MYSQL_USER}';"
