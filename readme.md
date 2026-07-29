# API Séries — Infraestrutura Docker

Projeto de exemplo de infraestrutura containerizada com proxy reverso, frontend em PHP, API em Python, banco de dados e backup automatizado, seguindo boas práticas de segurança e performance (redes segregadas, resolução DNS interna, builds multistage, variáveis de ambiente, healthchecks).

## Stack

| Camada | Tecnologia |
|---|---|
| Proxy reverso | Nginx |
| Frontend | PHP-FPM |
| API | FastAPI (Python) |
| Banco de dados | MySQL 8.4 |
| Backup | Alpine + dcron + mysqldump |

---

## Como subir o ambiente

1. Clone o repositório:
   ```bash
   git clone <url-do-repositorio>
   cd api_series
   ```

2. Crie o arquivo `.env` a partir do exemplo:
   ```bash
   cp .env.example .env
   ```
   Edite o `.env` com as credenciais desejadas (usuário/senha do banco, senha do usuário de backup, etc.).

3. Suba os containers:
   ```bash
   docker compose up -d
   ```

   O `-d` roda em background. Na primeira vez, o Docker também vai buildar as imagens (`php`, `api`, `alpine`) automaticamente. Se quiser forçar rebuild (por exemplo, depois de alterar um `Dockerfile`):
   ```bash
   docker compose up -d --build
   ```

4. Acesse `http://localhost:8080` no navegador.

## Como parar o ambiente

```bash
docker compose down
```

Isso para e remove os containers e as redes, mas **mantém os volumes** — os dados do banco e os backups já gerados continuam intactos pra próxima vez que você subir o projeto.

## Como apagar volumes

Se quiser resetar o banco do zero (perde todos os dados, incluindo o schema criado pelos scripts de init):

```bash
docker compose down -v
```

A flag `-v` remove também os volumes nomeados declarados no `docker-compose.yml` (o volume de dados do MySQL). Use com cuidado — não tem como desfazer. Os arquivos de backup em `./alpine/backups` **não** são apagados por isso, já que esse é um bind mount pra uma pasta local, não um volume nomeado.

Pra remover um volume específico sem derrubar tudo:
```bash
docker volume ls
docker volume rm <nome-do-volume>
```

---

## Como acessar o frontend

O Nginx expõe a porta `8080` do host, mapeada pra porta `80` interna do container:

```
http://localhost:8080
```

Todas as rotas (`/home`, `/serie`, `/criar-editar`, `/delete`) passam por aí. O Nginx recebe a requisição, serve arquivos estáticos diretamente (CSS, imagens) e repassa requisições `.php` pro PHP-FPM via FastCGI na rede interna.

## Como testar a API internamente

A API **não expõe porta pro host** — só é acessível de dentro da rede Docker. Pra testar diretamente, sem passar pelo frontend:

```bash
docker exec -it php sh
curl http://api:8000/health
```

Ou pra listar as séries:
```bash
curl http://api:8000/
```

Também dá pra rodar pelo container da própria API:
```bash
docker exec -it api sh
curl http://localhost:8000/health
```

A documentação interativa do FastAPI (Swagger) também só é acessível de dentro da rede:
```bash
docker exec -it php sh
curl http://api:8000/docs
```

## Como ver logs

Logs de todos os serviços, em tempo real:
```bash
docker compose logs -f
```

Logs de um serviço específico:
```bash
docker compose logs -f api
docker compose logs -f php
docker compose logs -f nginx
docker compose logs -f db
docker compose logs -f alpine
```

Sem o `-f` (follow), mostra o histórico e sai, em vez de continuar acompanhando:
```bash
docker compose logs api
```

---

## Como funcionam as redes

O projeto usa **três redes Docker isoladas**, cada uma conectando apenas os serviços que realmente precisam se comunicar entre si. Nenhum serviço enxerga uma rede da qual não faz parte — é esse encadeamento que garante que, por exemplo, o Nginx nunca alcance o banco diretamente.

```
Navegador → nginx ─┬─ (frontend) ─┬─ php ─┬─ (backend) ─┬─ api ─┬─ (banco_dados) ─┬─ db
                    └──────────────┘       └─────────────┘       └──────────────────┘
```

### Rede `frontend`

Conecta **apenas** `nginx` e `php`.

- O `nginx` é o único serviço com porta publicada pro host (`8080:80`).
- O `php` (PHP-FPM) fica nessa rede pra receber requisições FastCGI do `nginx`, mas **não tem porta publicada** — só é alcançável pelo próprio `nginx`, via o nome do serviço (`php:9000`) resolvido pelo DNS interno do Docker.

Isola a camada de apresentação: só o Nginx é exposto externamente; o PHP nunca é acessado diretamente de fora.

### Rede `backend`

Conecta **apenas** `php` e `api`.

- O `php` está em **duas redes** (`frontend` e `backend`), porque precisa: (1) receber requisições do Nginx (rede `frontend`) e (2) fazer chamadas HTTP pra API (rede `backend`).
- A `api` está em **duas redes** também (`backend` e `banco_dados`), pelo mesmo motivo: precisa ser alcançada pelo `php` e, ao mesmo tempo, alcançar o banco.
- O `nginx` e o `db` **não** estão nessa rede — cada um só participa das redes que sua função exige.

### Rede `banco_dados`

Conecta `api`, `db` e o serviço de backup (`alpine`).

- A `api` está aqui pra poder consultar/gravar dados no `db`.
- O `db` (MySQL) fica isolado nessa rede — não é alcançado por `nginx` nem por `php`, só pela `api` e pelo serviço de backup.
- O `alpine` (container de backup) também precisa estar nessa rede, já que ele roda `mysqldump` diretamente contra o `db`, fora do fluxo normal de requisições da aplicação.

Essa segmentação em três camadas segue o princípio de menor privilégio: um serviço comprometido no `nginx`, por exemplo, não tem rota de rede nenhuma até o `db` — precisaria primeiro comprometer o `php` e depois a `api` pra sequer alcançar a rede onde o banco vive.

## Por que o banco não tem porta publicada

O `db` (MySQL) não define `ports:` no `docker-compose.yml` — só existe dentro da rede interna Docker (`banco_dados`).

Motivos:

1. **Superfície de ataque reduzida.** Se a porta do MySQL fosse publicada (`3306:3306`), qualquer processo ou usuário na máquina host (ou, em produção, qualquer host que alcance a rede) poderia tentar se conectar diretamente ao banco, contornando toda a lógica de validação e autorização da API.
2. **Não é necessário.** Os únicos serviços que precisam falar com o banco são a `api` e o serviço de backup (`alpine`), e ambos já estão na mesma rede Docker (`banco_dados`) — a comunicação acontece via o nome do serviço (`db:3306`), resolvido pelo DNS interno, sem precisar expor nada pro host.
3. **Princípio de menor privilégio.** Cada camada só deve poder acessar exatamente o que precisa. O banco de dados é o recurso mais sensível da stack (contém todos os dados); mantê-lo inacessível de fora da rede Docker é uma das defesas mais baratas e eficazes que existem.

Pra inspecionar o banco durante desenvolvimento, sem publicar a porta, use `docker exec` pra entrar no container:
```bash
docker exec -it db mysql -u <user> -p<senha> <database>
```

## Por que o PHP usa http://api:8000

Dentro do `docker-compose.yml`, o serviço da API é declarado com o nome `api`:

```yaml
services:
  api:
    build: ./api
    ...
```

O Docker Compose cria automaticamente uma **rede interna com resolução DNS** entre os serviços que compartilham uma mesma rede declarada. Isso significa que, de dentro de qualquer container na rede `backend`, o hostname `api` resolve automaticamente pro IP interno do container da API — sem precisar hardcodar IPs (que mudam a cada `docker compose up`) e sem precisar publicar porta nenhuma pro host.

Por isso o PHP faz suas chamadas para:
```
http://api:8000
```
em vez de `http://localhost:8000` (que apontaria pro próprio container do PHP, não pra API) ou um IP fixo (que quebraria a cada rebuild).

A porta `8000` é a porta **interna** que o Uvicorn expõe dentro do container da API (`EXPOSE 8000` no `Dockerfile` da API) — não precisa estar mapeada pro host, porque a comunicação PHP → API acontece inteiramente dentro da rede Docker.

---

## Backup automatizado do banco

O serviço `alpine` (container `mysql_backup_cron`) roda um daemon `crond` que dispara `mysqldump` periodicamente contra o `db`, salvando os dumps em `.sql` num volume local (`./alpine/backups`), fora do container.

### Como funciona

- **Imagem**: Alpine mínima, com `mysql-client` (cliente MariaDB, usado pelo `mysqldump`), `tzdata` (timezone correto pro agendamento), `dcron` (daemon de cron) e `tini` (init leve, evita erro de `setpgid` ao rodar `crond` como PID 1).
- **Agendamento**: definido em `crontab.txt`, copiado pra `/etc/crontabs/root` dentro do container.
- **Script**: `backup.sh` roda o `mysqldump` com um usuário dedicado (`backup_user`), gera um arquivo `.sql` com timestamp e grava em `/backups` (mapeado pro host via volume).
- **Rede**: o `alpine` está na rede `banco_dados`, a única que dá acesso ao `db` — nenhuma outra rede consegue alcançá-lo.

### Usuário de backup dedicado

O backup **não usa o `root`** do MySQL. Existe um usuário `backup_user`, criado no script de inicialização do banco, com permissão mínima necessária pro `mysqldump` funcionar (`SELECT`, `LOCK TABLES`, `SHOW VIEW`, `EVENT`, `TRIGGER`) — sem privilégios de escrita ou administração.

Dois detalhes importantes de compatibilidade, caso o `db` precise ser recriado do zero algum dia:

1. O MySQL 8.4 desativa o plugin `mysql_native_password` por padrão. O `docker-compose.yml` liga esse plugin explicitamente no serviço `db` (`command: --mysql-native-password=ON`), porque o `mysql-client` do Alpine (que é, na verdade, um fork do MariaDB) não sabe autenticar com o plugin padrão do MySQL 8 (`caching_sha2_password`).
2. `database` é o nome do schema usado no ambiente de exemplo — e é também uma palavra reservada em SQL. Por isso o `GRANT` no script de init referencia o nome do banco entre crases (`` `database` ``), não sem escape.

### Testar o backup manualmente

Sem esperar o próximo disparo do cron:

```bash
docker compose exec alpine sh -c \
  "mysqldump --no-tablespaces -h db -u backup_user -p'${MYSQL_BACKUP_PASSWORD}' ${MYSQL_DATABASE} > /tmp/teste.sql && echo OK"
```

### Ver os backups gerados

```bash
docker compose exec alpine ls -lh /backups
```

Ou diretamente no host, já que é um bind mount:
```bash
ls -lh ./alpine/backups
```

### Ver o log do cron

```bash
docker compose exec alpine cat /backups/cron.log
```

### Restaurar um backup

```bash
docker compose exec -i alpine sh -c \
  "gunzip -c /backups/<nome_do_arquivo>.sql.gz | mysql -h db -u root -p'${MYSQL_ROOT_PASSWORD}' ${MYSQL_DATABASE}"
```

(ou, se o arquivo não estiver compactado, troque `gunzip -c arquivo.sql.gz |` por `< arquivo.sql`)

⚠️ Restaurar um backup **sobrescreve** os dados atuais do banco — não existe confirmação automática nesse comando, então confira o nome do arquivo com cuidado antes de rodar.

---

## Estrutura de diretórios (referência)

```
api_series/
├── docker-compose.yml
├── .env.example
├── api/
│   ├── Dockerfile          # multistage: builder (compila deps) + runtime (imagem enxuta)
│   ├── main.py
│   ├── requirements.txt
│   ├── database/
│   ├── models/
│   └── schemas/
├── php/
│   ├── Dockerfile
│   └── pages/
│       ├── home.php
│       ├── serie.php
│       ├── criar-editar.php
│       ├── delete.php
│       └── 404.php
├── nginx/
│   └── default.conf
├── alpine/
│   ├── Dockerfile
│   ├── backup.sh
│   ├── crontab.txt
│   └── backups/            # volume local com os dumps gerados
└── db/
    └── init/                # scripts de entrypoint (schema, seed inicial e criação do backup_user)
```

## Healthchecks

Cada serviço com dependência crítica define um healthcheck no `docker-compose.yml`, permitindo que o Docker Compose saiba quando um serviço está de fato pronto pra receber tráfego (não só "rodando", mas "respondendo corretamente"). Isso é usado com `depends_on: condition: service_healthy`, garantindo que, por exemplo, a `api` só sobe depois que o `db` está saudável, e o `php`/`nginx` só sobem depois que a `api` está de pé.

Pra ver o status de saúde de cada container:
```bash
docker compose ps
```