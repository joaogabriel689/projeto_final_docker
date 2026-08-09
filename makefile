.PHONY: restore restore-latest backup logs-restore

# Restaura o backup mais recente (com confirmação interativa)
restore:
	docker compose exec alpine sh /restore.sh

# Restaura o backup mais recente SEM pedir confirmação (cuidado!)
restore-latest:
	docker compose exec alpine sh /restore.sh --yes

# Restaura um backup específico: make restore-file FILE=backup_20260101_020000.sql.gz
restore-file:
	docker compose exec alpine sh /restore.sh $(FILE)

# Dispara um backup manual (assumindo que seu backup.sh já existe no container)
backup:
	docker compose exec alpine sh /backup.sh

# Mostra o log de restores já feitos
logs-restore:
	docker compose exec alpine cat /var/log/alpine/restore.log