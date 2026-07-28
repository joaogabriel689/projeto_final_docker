# API Séries — Infraestrutura Docker

Projeto de exemplo de infraestrutura containerizada com proxy reverso, frontend em PHP, API em Python e banco de dados, seguindo boas práticas de segurança e performance (redes segregadas, resolução DNS interna, builds multistage, variáveis de ambiente, healthchecks).

## Stack

| Camada | Tecnologia |
|---|---|
| Proxy reverso | Nginx |
| Frontend | PHP-FPM |
| API | FastAPI (Python) |
| Banco de dados | MySQL |

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
   Edite o `.env` com as credenciais desejadas (usuário/senha do banco, etc.).

3. Suba os containers:
   ```bash
   docker compose up -d
   ```

   O `-d` roda em background. Na primeira vez, o Docker também vai buildar as imagens (`php`, `api`) automaticamente. Se quiser forçar rebuild (por exemplo, depois de alterar um `Dockerfile`):
   ```bash
   docker compose up -d --build
   ```

4. Acesse `http://localhost:8080` no navegador.

## Como parar o ambiente

```bash
docker compose down
```

Isso para e remove os containers e a rede, mas **mantém os volumes** — os dados do banco continuam intactos pra próxima vez que você subir o projeto.

## Como apagar volumes

Se quiser resetar o banco do zero (perde todos os dados):

```bash
docker compose down -v
```

A flag `-v` remove também os volumes nomeados declarados no `docker-compose.yml` (o volume de dados do MySQL). Use com cuidado — não tem como desfazer.

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
```

Sem o `-f` (follow), mostra o histórico e sai, em vez de continuar acompanhando:
```bash
docker compose logs api
```

---

## Como funciona a rede frontend

A rede `frontend-network` conecta **apenas** os serviços que precisam conversar com o mundo externo ou entre si na camada de apresentação: `nginx` e `php`.

- O `nginx` é o único serviço com porta publicada pro host (`8080:80`).
- O `php` (PHP-FPM) fica nessa rede pra receber requisições FastCGI do `nginx`, mas **não tem porta publicada** — só é alcançável pelo próprio `nginx`, via o nome do serviço (`php:9000`) resolvido pelo DNS interno do Docker.

Isso isola a camada de apresentação: só o Nginx é exposto externamente; o PHP nunca é acessado diretamente de fora.

## Como funciona a rede backend

A rede `backend-network` conecta os serviços que lidam com lógica de negócio e dados: `php`, `api` e `db`.

- O `php` está nas **duas redes** (frontend e backend), porque precisa: (1) receber requisições do Nginx (rede frontend) e (2) fazer chamadas HTTP pra API (rede backend).
- A `api` só está na rede backend — não tem porta publicada, só é alcançável de dentro da rede Docker.
- O `db` (MySQL) só está na rede backend — mais isolado ainda, só acessível pela `api`.

Essa segregação (frontend/backend) segue o princípio de menor privilégio: cada serviço só enxerga os outros serviços que realmente precisa acessar. Um serviço comprometido na camada de apresentação (`nginx`) não tem acesso direto ao banco de dados, por exemplo.

## Por que o banco não tem porta publicada

O `db` (MySQL) não define `ports:` no `docker-compose.yml` — só existe dentro da rede interna Docker (`backend-network`).

Motivos:

1. **Superfície de ataque reduzida.** Se a porta do MySQL fosse publicada (`3306:3306`), qualquer processo ou usuário na máquina host (ou, em produção, qualquer host que alcance a rede) poderia tentar se conectar diretamente ao banco, contornando toda a lógica de validação e autorização da API.
2. **Não é necessário.** O único serviço que precisa falar com o banco é a `api`, e ela já está na mesma rede Docker (`backend-network`) — a comunicação acontece via o nome do serviço (`db:3306`), resolvido pelo DNS interno, sem precisar expor nada pro host.
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

O Docker Compose cria automaticamente uma **rede interna com resolução DNS** entre os serviços que compartilham uma mesma rede declarada. Isso significa que, de dentro de qualquer container na mesma rede (`backend-network`), o hostname `api` resolve automaticamente pro IP interno do container da API — sem precisar hardcodar IPs (que mudam a cada `docker compose up`) e sem precisar publicar porta nenhuma pro host.

Por isso o PHP faz suas chamadas para:
```
http://api:8000
```
em vez de `http://localhost:8000` (que apontaria pro próprio container do PHP, não pra API) ou um IP fixo (que quebraria a cada rebuild).

A porta `8000` é a porta **interna** que o Uvicorn expõe dentro do container da API (`EXPOSE 8000` no `Dockerfile` da API) — não precisa estar mapeada pro host, porque a comunicação PHP → API acontece inteiramente dentro da rede Docker.

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
└── db/
    └── init/               # scripts de entrypoint (schema + seed inicial)
```

## Healthchecks

Cada serviço define um healthcheck no `docker-compose.yml`, permitindo que o Docker Compose saiba quando um serviço está de fato pronto pra receber tráfego (não só "rodando", mas "respondendo corretamente"). Isso é usado com `depends_on: condition: service_healthy`, garantindo que, por exemplo, a `api` só sobe depois que o `db` está saudável, e o `php`/`nginx` só sobem depois que a `api` está de pé.

Pra ver o status de saúde de cada container:
```bash
docker compose ps
```