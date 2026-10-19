# Cursos API

API REST de plataforma de cursos em **Laravel 13 + Sanctum**, rodando com Docker (PHP 8.4 + MySQL 8.4 + Redis). Multi-tenant por `company_id`.

## Requisitos

- Docker Desktop instalado e em execução
- Docker Compose v2

## Subir o ambiente

```sh
# 1. Crie o arquivo de ambiente
copy .env.example .env

# 2. Gere a chave da aplicação (primeira vez)
docker compose run --rm app php artisan key:generate

# 3. Instale as dependências do PHP
docker compose run --rm app composer install

# 4. Suba os containers
docker compose up -d --build

# 5. Rode as migrations e os seeders
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

A aplicação fica disponível em: http://localhost:8000

Usuários de demonstração (após `db:seed`):

| Perfil  | E-mail               | Senha      |
| ------- | -------------------- | ---------- |
| Admin   | `admin@cursos.test`  | `password` |
| Aluno   | `aluno@cursos.test`  | `password` |

## Serviços

| Serviço | Container         | Porta host | Porta interna |
| ------- | ----------------- | ---------- | ------------- |
| app     | `cursos_api_app`  | 8000       | 8000          |
| queue   | `cursos_api_queue`| —          | —             |
| db      | `cursos_api_db`   | 3308       | 3306          |
| redis   | `cursos_api_redis`| 6380       | 6379          |

> Dentro dos containers use `DB_HOST=db` e `DB_PORT=3306`.
> Para conectar no MySQL a partir da sua máquina use a porta **3308**.

> O Redis fica disponível em `REDIS_HOST=redis` / `REDIS_PORT=6379` dentro da rede Docker.
> A partir da sua máquina use a porta **6380**.

## Comandos úteis

```sh
# Ver logs
docker compose logs -f

# Ver logs apenas do app
docker compose logs -f app

# Acessar o shell do container
docker compose exec app bash

# Parar os containers (mantém os dados)
docker compose stop

# Parar e remover os containers
docker compose down

# Parar e remover tudo, incluindo o volume do banco
docker compose down -v

# Rebuild da imagem do app
docker compose build --no-cache app
```

## Artisan / Composer / NPM

```sh
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
docker compose exec app composer install
docker compose exec app composer dump-autoload

# Front-end (se necessário)
docker compose exec app npm install
docker compose exec app npm run dev
```

## Banco de dados

```sh
# Abrir o cliente MySQL dentro do container
docker compose exec db mysql -u cursos -psecret cursos_api

# Como root
docker compose exec db mysql -u root -proot cursos_api
```

## Redis

```sh
# Abrir o redis-cli dentro do container
docker compose exec redis redis-cli

# Testar a conexão
docker compose exec redis redis-cli ping

# Testar a partir do app (extensão phpredis)
docker compose exec app php -r "echo (new Redis)->connect('redis', 6379) ? 'ok' : 'fail';"
```

> Por padrão os drivers continuam `file`/`sync`. Para usar o Redis troque no `.env`:
> `CACHE_STORE=redis`, `SESSION_DRIVER=redis` e/ou `QUEUE_CONNECTION=redis`.

## Variáveis de ambiente relevantes

| Variável | Descrição |
| --- | --- |
| `FRONTEND_URL` | base do link de redefinição de senha e dos certificados públicos (default `http://localhost:3000`) |
| `SANCTUM_EXPIRATION` | validade do token em minutos (vazio = nunca expira) |
| `MERCADOPAGO_WEBHOOK_SECRET` | segredo HMAC do webhook do MercadoPago (**obrigatório em produção**) |
| `MANUAL_WEBHOOK_SECRET` | segredo do header `X-Webhook-Secret` do gateway manual (**obrigatório em produção**) |

> Sem os segredos de webhook configurados, **todo** webhook é rejeitado com `401 invalid_webhook_signature` (falha fechada).

## Testes

```sh
docker compose exec app php artisan test
```

## API

- Base: `http://localhost:8000/api/v1`
- Auth: Bearer Token (Sanctum) — `POST /api/v1/auth/login`
- Rotas públicas e webhooks exigem o header `X-Company` (id da empresa).
- Documentação completa: [`docs/API.md`](docs/API.md)
- OpenAPI 3: [`docs/openapi.yaml`](docs/openapi.yaml)
- Arquitetura: [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md)
- Segurança: [`docs/SECURITY.md`](docs/SECURITY.md)
- Performance: [`docs/PERFORMANCE.md`](docs/PERFORMANCE.md)

### Postman (todas as rotas prontas)

1. No Postman: **Import** → selecione [`docs/postman/course-platform-api.json`](docs/postman/course-platform-api.json).
2. Importe também o environment [`docs/postman/course-platform-api.environment.json`](docs/postman/course-platform-api.environment.json) e selecione **"Cursos API - Local"**.
3. Rode a pasta **Setup (rode primeiro)**: `Login (admin)` e `Login (aluno)` preenchem os tokens automaticamente.
4. As requisições de listagem/criação preenchem as variáveis de id (`course_id`, `lesson_id`, `team_id`, ...) sozinhas, então você pode executar na ordem que quiser.

A collection cobre **todas as rotas** (`/api/v1`) e já vem com corpo de exemplo, `Accept: application/json` e o header `X-Company` nas rotas públicas/webhooks.


Exemplo de login:

```sh
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"admin@cursos.test","password":"password"}'
```
