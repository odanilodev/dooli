# Cursos API

API em Laravel 12 rodando com Docker (PHP 8.4 + MySQL 8.4).

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

# 5. Rode as migrations
docker compose exec app php artisan migrate
```

A aplicação fica disponível em: http://localhost:8000

## Serviços

| Serviço | Container         | Porta host | Porta interna |
| ------- | ----------------- | ---------- | ------------- |
| app     | `cursos_api_app`  | 8000       | 8000          |
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

## Testes

```sh
docker compose exec app php artisan test
```
