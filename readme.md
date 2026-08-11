# Projeto Final Docker — Containerização Segura de uma Aplicação Real em Produção Simulada

Stack containerizada de uma aplicação de catálogo de séries, empacotada e orquestrada com Docker Compose seguindo boas práticas de segurança, observabilidade e operação: rede segmentada, usuários não-root, banco sem porta publicada, healthchecks, limites de recursos, logs centralizados, backup/restore automatizado e stack de métricas.

Repositório: [github.com/joaogabriel689/projeto_final_docker](https://github.com/joaogabriel689/projeto_final_docker)

---

## Sumário

- [Stack](#stack)
- [Setup](#setup)
- [Execução](#execução)
- [Arquitetura de rede](#arquitetura-de-rede)
- [Segurança](#segurança)
- [Recursos e healthchecks](#recursos-e-healthchecks)
- [Logs](#logs)
- [Backup e restore](#backup-e-restore)
- [Teste de carga](#teste-de-carga)
- [Monitoramento](#monitoramento)
- [Troubleshooting](#troubleshooting)
- [Rollback](#rollback)
- [Estrutura de diretórios](#estrutura-de-diretórios)

---

## Stack

| Camada | Serviço | Tecnologia | Exposto no host? |
|---|---|---|---|
| Proxy reverso | `nginx` | Nginx (alpine) | `8080` |
| Frontend | `php` | PHP-FPM 8.3 | não |
| API | `api` | FastAPI (Python 3.12) | não |
| Banco de dados | `db` | MySQL 8.4 | não |
| Backup / restore / teste de carga | `alpine` | Alpine 3.20 + dcron + tini + mysql-client + Apache Bench | não |
| Métricas | `prometheus` | Prometheus | `9090` |
| Métricas de containers | `cadvisor` | cAdvisor | `8081` |
| Dashboard | `grafana` | Grafana | `3000` |

Requisitos mínimos do enunciado atendidos: Dockerfile otimizado (multistage na API), `docker-compose.yml`, volume nomeado persistente para o MySQL, proxy reverso (Nginx), banco sem porta publicada, usuários não-root em todos os serviços de aplicação, healthchecks, credenciais via `.env`, limites de CPU/memória por serviço, logs em pasta dedicada por serviço, backup e restore automatizados, e documentação de setup/execução/troubleshooting/rollback (este documento).

Diferenciais implementados: stack de monitoramento (Prometheus + cAdvisor + Grafana) e teste de carga simples com `ab` (Apache Bench). Redis, Traefik, PHPMyAdmin e Portainer não fazem parte deste projeto.

---

## Setup

1. Clone o repositório:
   ```bash
   git clone https://github.com/joaogabriel689/projeto_final_docker.git
   cd projeto_final_docker
   ```

2. Crie o `.env` a partir do exemplo:
   ```bash
   cp .env.example .env
   ```

3. Preencha o `.env`. Variáveis obrigatórias:

   | Variável | Descrição |
   |---|---|
   | `MYSQL_ROOT_PASSWORD` | Senha do usuário `root` do MySQL |
   | `MYSQL_DATABASE` | Nome do schema da aplicação |
   | `MYSQL_USER` | Usuário de aplicação (usado pela API e pelo restore) |
   | `MYSQL_PASSWORD` | Senha do usuário de aplicação |
   | `MYSQL_HOST` | Hostname do banco dentro da rede Docker — use `db` |
   | `MYSQL_PORT` | Porta interna do MySQL — use `3306` |
   | `MYSQL_BACKUP_PASSWORD` | Senha do usuário `backup_user`, usado só pelo `mysqldump` |
   | `BASE_URL` | URL alvo do teste de carga (padrão: `http://nginx:80/`) |
   | `URL_API` | URL que o PHP usa para chamar a API (padrão: `http://api:8000/`) |

   O arquivo `.env` nunca é versionado (está no `.gitignore`); só o `.env.example`, com placeholders, vai para o Git. Essa é a estratégia de segredos adotada no projeto — aceita pelo enunciado como estratégia segura para credenciais.

4. Nenhum passo manual além disso: os usuários do banco (`MYSQL_USER` e `backup_user`) e o schema inicial são criados automaticamente pelos scripts em `mysql/docker-entrypoint-initdb.d/` na primeira subida do container `db`.

---

## Execução

Subir tudo (builda as imagens automaticamente na primeira vez):

```bash
docker compose up -d
```

Forçar rebuild depois de alterar um Dockerfile:

```bash
docker compose up -d --build
```

Acessar a aplicação:

```
http://localhost:8080
```

Ver o status de saúde de cada serviço (útil para confirmar que tudo subiu corretamente, não só que está "rodando"):

```bash
docker compose ps
```

Parar o ambiente mantendo dados e backups:

```bash
docker compose down
```

Resetar completamente o banco (perde todos os dados — os backups em `./alpine/backups` não são afetados, pois é bind mount, não volume nomeado):

```bash
docker compose down -v
```

### Testar a API diretamente (sem passar pelo frontend)

A API não expõe porta para o host — só é alcançável de dentro da rede Docker:

```bash
docker compose exec php sh -c "curl http://api:8000/health"
docker compose exec php sh -c "curl http://api:8000/"
```

---

## Arquitetura de rede

Quatro redes Docker, cada uma conectando só os serviços que precisam se falar. `backend` e `banco_dados` são redes internas (`internal: true`) — não têm rota para a internet nem para fora do host, o que reduz ainda mais a superfície de ataque caso um container dessas redes seja comprometido.

```
Navegador → nginx ─(frontend)─ php ─(backend, internal)─ api ─(banco_dados, internal)─ db
                                                                                          alpine (backup/restore)
prometheus ─(monitoring)─ cadvisor
     └────────────────────────────── grafana
```

| Rede | Serviços | `internal` | Propósito |
|---|---|---|---|
| `frontend` | `nginx`, `php` | não | Única rede com porta publicada (`nginx:8080`). PHP-FPM recebe requisições FastCGI só do Nginx. |
| `backend` | `php`, `api` | sim | PHP chama a API via `http://api:8000`. Nginx e `db` não participam — não há rota direta entre eles. |
| `banco_dados` | `api`, `db`, `alpine` | sim | Só quem precisa falar com o MySQL está aqui: a API (CRUD) e o `alpine` (`mysqldump`/restore). |
| `monitoring` | `prometheus`, `cadvisor`, `grafana` | não | Isolada do resto da stack — métricas de infraestrutura não têm rota para a aplicação. |

`php` está em duas redes (`frontend` + `backend`) e `api` está em duas redes (`backend` + `banco_dados`), porque cada um precisa ser alcançado de um lado e alcançar o próximo do outro. Esse encadeamento garante, por exemplo, que o Nginx nunca tenha rota até o banco — precisaria comprometer primeiro o PHP e depois a API.

---

## Segurança

- **Banco sem porta publicada.** O `db` não declara `ports:` — só existe dentro da rede `banco_dados`, que é `internal`. Para inspecionar em desenvolvimento, use `docker compose exec db mysql -u <user> -p`.
- **Usuários não-root.** `api` roda como `appuser` (criado no Dockerfile multistage), `php` roda como `appuser` (uid 1000), e `alpine` roda `crond` sob `tini` como PID 1 (evita o container rodar como root desnecessariamente em processos longos).
- **Usuário de backup dedicado com privilégio mínimo.** O `mysqldump` do serviço `alpine` usa `backup_user`, criado pelo `mysql-native-password` só com os grants necessários para dump — nunca o `root` do MySQL. O restore, por sua vez, usa `MYSQL_USER` (o usuário de aplicação), suficiente para reescrever os dados sem precisar de credenciais administrativas.
- **`mysql-native-password` ligado propositalmente.** O MySQL 8.4 desativa esse plugin por padrão, mas o `mysql-client` do Alpine (fork MariaDB) não autentica com o plugin padrão (`caching_sha2_password`). Isso é ligado explicitamente via `command:` no `docker-compose.yml`.
- **Segredos via `.env`.** Aceito pelo enunciado como estratégia segura; nenhuma credencial fica hardcoded em Dockerfile, compose ou código-fonte.
- **Build multistage na API.** O estágio `builder` tem `gcc`, `build-essential` e headers `-dev` para compilar dependências com extensões nativas; nenhum desses pacotes vai para a imagem final — só o virtualenv já compilado é copiado para o estágio `runtime`, reduzindo superfície de ataque e tamanho de imagem.
- **Bloqueio de arquivos sensíveis no Nginx.** O `default.conf` nega acesso a dotfiles e arquivos `.bkp` (`location ~ /\.(?!well-known).*|.*\.bkp$`), evitando exposição acidental de `.env`, `.git` etc. caso algum arquivo desses acabe dentro do `webroot`.
- **Segmentação de rede como defesa em profundidade** — ver seção anterior.

---

## Recursos e healthchecks

Todos os serviços de aplicação definem `deploy.resources.limits` (CPU e memória), evitando que um serviço com vazamento ou pico de carga derrube o host inteiro:

| Serviço | CPU | Memória |
|---|---|---|
| `api` | 0.50 | 512M |
| `db` | 1.0 | 1G |
| `php` | 0.50 | 512M |
| `nginx` | 0.25 | 256M |
| `alpine` | 0.25 | 256M |
| `prometheus` | 0.50 | 512M |
| `cadvisor` | 0.50 | 512M |
| `grafana` | 0.50 | 512M |

> Nota: `deploy.resources.limits` é aplicado nativamente pelo Docker Compose (Compose v2) rodando fora do modo Swarm — o Compose já traduz esses limites para `--cpus`/`--memory` do runtime.

Healthchecks garantem que a ordem de subida respeite prontidão real, não só o container estar de pé:

- `db`: `mysqladmin ping` a cada 5s (10 tentativas) — a `api` só sobe depois que o `db` reporta `healthy` (`depends_on: condition: service_healthy`).
- `api`: `curl` no endpoint `/health` a cada 30s — `php` só sobe depois que `db` **e** `api` estão saudáveis.
- `nginx` e `alpine` dependem de `php`/`db` estarem prontos via `depends_on`, sem healthcheck próprio adicional.

---

## Logs

Cada serviço grava logs em uma subpasta dedicada de `./logs/`, montada como bind mount — acessível diretamente do host sem precisar de `docker logs`:

```
logs/
├── api/
├── mysql/       # general.log e error.log, habilitados via `command:` no db
├── php-fpm/
├── nginx/       # access.log e error.log
└── alpine/      # restore.log (gerado pelo restore.sh)
```

Todos os serviços também usam o driver `json-file` com rotação (`max-size: 10m`, `max-file: 3`), evitando que logs cresçam sem limite e encham o disco do host.

Ver logs em tempo real:

```bash
docker compose logs -f            # todos os serviços
docker compose logs -f api        # um serviço específico
```

O log do cron de backup fica em `./alpine/backups/cron.log` (não em `./logs/`, pois está atrelado ao volume de backups):

```bash
docker compose exec alpine cat /backups/cron.log
```

---

## Backup e restore

O serviço `alpine` (container `mysql_backup_cron`) roda `crond` sob `tini`, disparando `backup.sh` a cada 4 horas (`crontab.txt`: `* */4 * * *`). Os dumps `.sql` ficam em `./alpine/backups/`, fora do container.

### Comandos via Makefile

```bash
make backup           # dispara um backup manual imediato
make restore           # restaura o backup mais recente, com confirmação interativa
make restore-latest    # restaura o mais recente SEM pedir confirmação
make restore-file FILE=database_20260808_150501.sql   # restaura um arquivo específico
make logs-restore      # mostra o histórico de restores já executados
```

### Como o restore funciona (`restore.sh`)

1. Resolve qual arquivo restaurar: o passado como argumento, ou o mais recente em `/backups` que bater com o padrão `${MYSQL_DATABASE}_*.sql`.
2. Pede confirmação digitando `sim`, a menos que rodado com `--yes`.
3. **Antes de sobrescrever, cria automaticamente um snapshot de segurança** do estado atual (`pre_restore_<db>_<timestamp>.sql`) — se o restore for para o arquivo errado, ainda dá para reverter a partir desse snapshot.
4. Restaura o dump escolhido com `mysql -h db -u $MYSQL_USER ...` e registra o evento em `/var/log/alpine/restore.log`.

> ⚠️ Restore sobrescreve os dados atuais do banco. O snapshot de segurança automático é a rede de proteção — confirme o nome do arquivo antes de aceitar o prompt.

### Comandos manuais (sem Makefile)

```bash
# Backup manual
docker compose exec alpine sh /backup.sh

# Listar backups disponíveis
docker compose exec alpine ls -lh /backups
ls -lh ./alpine/backups        # equivalente, direto no host (bind mount)

# Restore manual de um arquivo específico
docker compose exec alpine sh /restore.sh database_20260808_150501.sql
```

---

## Teste de carga

O mesmo container `alpine` inclui Apache Bench (`ab`) e um script (`load-test.sh`) que roda três rodadas contra `$BASE_URL` (padrão `http://nginx:80/`, resolvido internamente via rede Docker): 100 requisições/5 conexões, 1000/20 e 5000/50.

```bash
make tests
```

O resultado é salvo em `./alpine/results/latest.txt`:

```bash
cat ./alpine/results/latest.txt
```

---

## Monitoramento

Stack de observabilidade isolada na rede `monitoring`:

- **cAdvisor** (`http://localhost:8081`) — coleta métricas de uso de CPU/memória/rede de cada container, lendo diretamente do host (`/rootfs`, `/sys`, `/var/lib/docker` montados como somente-leitura).
- **Prometheus** (`http://localhost:9090`) — faz scrape de si mesmo e do cAdvisor a cada 15s (`prometheus/prometheus.yml`), com volume nomeado (`prometheus_data`) para persistir a série temporal entre reinícios.
- **Grafana** (`http://localhost:3000`) — dashboard para visualizar as métricas coletadas pelo Prometheus (adicionar o Prometheus como data source, apontando para `http://prometheus:9090`, dentro da rede `monitoring`). Volume nomeado `grafana_data` persiste dashboards e configuração.

---

## Troubleshooting

**`db` nunca fica `healthy` / `api` trava em "waiting"**
Confira `docker compose logs db`. Causas comuns: `MYSQL_ROOT_PASSWORD`/`MYSQL_USER`/`MYSQL_PASSWORD` vazios ou não definidos no `.env` (os scripts de init falham explicitamente com `ERRO: ... não definida.`), ou volume `mysql_data` de uma execução anterior com senha diferente da atual — nesse caso os scripts de `docker-entrypoint-initdb.d/` só rodam na primeira inicialização de um volume vazio, então uma senha trocada no `.env` não é aplicada a um volume já existente.
```bash
docker compose logs db
docker compose exec db mysql -u root -p"$MYSQL_ROOT_PASSWORD" -e "SELECT user, host, plugin FROM mysql.user;"
```

**Erro de autenticação do `mysqldump`/backup (`Authentication plugin ... cannot be loaded`)**
O `db` precisa estar com `--mysql-native-password=ON` (já definido no `command:` do `docker-compose.yml`). Se o problema persistir após recriar o container, o volume `mysql_data` pode ter sido criado antes dessa flag existir — nesse caso, `backup_user` precisa ser recriado com `mysql_native_password` explicitamente.

**PHP retorna 502 Bad Gateway**
Geralmente o `php` ainda não subiu ou caiu. Verifique:
```bash
docker compose ps php
docker compose logs php
```
Se o container está saudável mas o Nginx ainda erra, confira se `fastcgi_pass php:9000` resolve — teste com `docker compose exec nginx getent hosts php`.

**PHP não consegue falar com a API**
`URL_API` no `.env` precisa ser `http://api:8000/` (nome do serviço, não `localhost`). Teste de dentro do container:
```bash
docker compose exec php sh -c "curl -v http://api:8000/health"
```

**Backup não está rodando no horário esperado**
```bash
docker compose exec alpine crontab -l          # confirma o agendamento carregado
docker compose exec alpine cat /backups/cron.log
docker compose logs alpine
```
Rode `make backup` para descartar problema de agendamento e confirmar que o script em si funciona.

**Mudanças no `.env` não têm efeito**
Variáveis de ambiente são lidas na criação do container, não a cada `up`. Depois de editar o `.env`:
```bash
docker compose up -d --force-recreate
```

**Quero ver o estado real de um container que não sobe**
```bash
docker compose logs --tail=100 <serviço>
docker compose exec <serviço> sh   # ou bash, dependendo da imagem
```

---

## Rollback

O `restore.sh` já cria um snapshot de segurança (`pre_restore_*.sql`) automaticamente antes de qualquer restore — esse é o mecanismo de rollback de dados:

```bash
# Reverter para o estado anterior ao último restore
docker compose exec alpine ls -t /backups/pre_restore_*.sql | head -n1
docker compose exec alpine sh /restore.sh <nome_do_snapshot_pre_restore>.sql
```

Para rollback de **código/imagem** (ex.: um `--build` quebrou algo):

```bash
git log --oneline                  # identifica o commit anterior estável
git checkout <commit_ou_tag_anterior>
docker compose up -d --build       # reconstrói as imagens a partir do código revertido
```

Como `db` usa volume nomeado persistente (`mysql_data`), um rollback de código **não** reverte dados — só a aplicação. Para reverter dados junto, combine com o restore de um backup anterior ao problema.

Para descartar completamente o ambiente e recomeçar do zero (perde dados do banco, mantém backups em `./alpine/backups` por serem bind mount):

```bash
docker compose down -v
docker compose up -d --build
```

---

## Estrutura de diretórios

```
projeto_final_docker/
├── docker-compose.yml
├── makefile
├── .env.example
├── api/
│   ├── dockerfile              # multistage: builder (compila deps) + runtime (imagem enxuta, non-root)
│   ├── main.py
│   ├── docker.py
│   ├── requirements.txt
│   ├── database/
│   ├── models/
│   └── schemas/
├── php/
│   ├── dockerfile
│   ├── pages/
│   │   ├── home.php
│   │   ├── serie.php
│   │   ├── criar-editar.php
│   │   ├── delete.php
│   │   └── 404.php
│   ├── public/
│   │   ├── index.php
│   │   └── style.css
│   └── requests/
│       └── requests.php
├── nginx/
│   ├── default.conf
│   └── style.css
├── mysql/
│   ├── dockerfile
│   └── docker-entrypoint-initdb.d/
│       ├── 01-create_user.sh   # cria MYSQL_USER e backup_user com privilégio mínimo
│       └── 02-entrypoint.sql   # schema + seed inicial
├── alpine/
│   ├── dockerfile              # multistage: builder valida scripts com shellcheck
│   ├── entrypoint.sh
│   ├── backup.sh
│   ├── restore.sh
│   ├── load-test.sh
│   ├── crontab.txt
│   ├── backups/                # bind mount — dumps .sql e snapshots pre_restore
│   └── results/                # bind mount — resultado do teste de carga
├── prometheus/
│   └── prometheus.yml
└── logs/                       # bind mount — logs por serviço (api, mysql, php-fpm, nginx)
```
