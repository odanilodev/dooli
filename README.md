<div align="center">

# 🎓 Delta Class

**Plataforma multiempresa de cursos online, com loja, pagamentos e área do aluno.**

![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.3-777BB4?logo=php&logoColor=white)
![Vue](https://img.shields.io/badge/Vue-3-4FC08D?logo=vuedotjs&logoColor=white)
![Vite](https://img.shields.io/badge/Vite-5-646CFF?logo=vite&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-8.4-4479A1?logo=mysql&logoColor=white)
![Redis](https://img.shields.io/badge/Redis-7-DC382D?logo=redis&logoColor=white)
![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)

</div>

---

## ✨ Visão geral

API REST em **Laravel 13 + Sanctum** com SPA em **Vue 3**, isolada por empresa (`company_id`). Cada escola tem sua própria loja, domínio, alunos, gateways e configurações de e-mail.

## 🚀 Funcionalidades

- 📚 **Conteúdo:** cursos, módulos, tópicos, aulas (vídeo ou documento), anexos e aulas ao vivo
- 👥 **Alunos:** turmas, certificados, comentários, avaliações e relatórios
- 🛒 **Loja por empresa:** produtos (pagamento único ou assinatura), cupons, banners e vitrine
- 💳 **Pagamentos:** Mercado Pago (PIX e cartão), assinaturas e webhooks com assinatura HMAC
- 🤝 **Comercial:** vendedores e comissões
- 🤖 **Robôs / DeltaStats:** coleta diária e intradiária de dados de mercado (brapi.dev, Binance)
- 🔔 **Notificações:** e-mails transacionais via Resend, configuráveis por empresa
- 🧭 **Menus dinâmicos** por tipo de usuário e painel administrativo completo

## 🧱 Stack

| Camada | Tecnologias |
| --- | --- |
| Backend | PHP 8.3, Laravel 13, Sanctum, Resend |
| Frontend | Vue 3, Vue Router, Pinia, Vite, Chart.js |
| Infra | Docker, MySQL 8.4, Redis 7, Nginx/PHP-FPM, Apache (cPanel) |

## 📁 Estrutura

```text
app/        Controllers, Models, Services (regras de negócio) e Commands
frontend/   SPA Vue 3 (Vite)
database/   Migrations, seeders e factories
docs/       API, arquitetura, domínio, segurança, deploy e Postman/OpenAPI
docker/     Configurações de PHP/Nginx (imagem de produção)
.github/    Workflow de deploy automático (GitHub Actions)
deploy-prod.sh  Script de deploy executado no servidor
tests/      Testes automatizados (PHPUnit)
```

---

## Requisitos

- Docker Desktop instalado e em execução
- Docker Compose v2

## Ambientes

| Ambiente | Compose | Env example | Env real |
| --- | --- | --- | --- |
| Local | `docker-compose.yml` | `.env.example` | `.env` |
| Produção | `docker-compose.production.yml` | `.env.production.example` | `.env.production` |

O arquivo `.env.production` é ignorado pelo Git. Nunca versione valores reais.

## Subir o ambiente local

```sh
# 1. Crie os arquivos de ambiente
Copy-Item .env.example .env
Copy-Item frontend/.env.example frontend/.env

# 2. Gere a chave da aplicação (primeira vez)
docker compose run --rm app php artisan key:generate

# 3. Instale as dependências do PHP
docker compose run --rm app composer install

# 4. Suba os containers
docker compose up -d --build

# 5. Rode as migrations e os seeders
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed

# 6. Crie o link público de storage usado pelo Laravel local
docker compose exec app php artisan storage:link
```

- API: http://localhost:8000
- Frontend: http://localhost:5174
- Mailpit: http://localhost:8025

Usuários de demonstração (após `db:seed`):

| Perfil  | E-mail               | Senha      |
| ------- | -------------------- | ---------- |
| Admin   | `admin@cursos.test`  | `password` |
| Aluno   | `aluno@cursos.test`  | `password` |

## Produção

Produção roda em um servidor dedicado com cPanel/WHM. O Apache do cPanel faz o SSL (AutoSSL) e o proxy para o container `web` (nginx + SPA + PHP-FPM), publicado só em `127.0.0.1:9100`. MySQL e Redis não têm portas públicas.

```text
Internet ─443─► Apache (cPanel) ─► 127.0.0.1:9100 ─► web (nginx) ─► app (php-fpm)
                                                     + queue + scheduler + MySQL + Redis
```

**Deploy automático:** todo push na `main` dispara o workflow `.github/workflows/deploy-prod.yml`, que envia o código por SSH para o servidor e executa `deploy-prod.sh`: build das imagens, subida dos containers, health check, `migrate --force`, caches e `queue:restart`. O script aborta se o `.env.production` estiver incompleto (`CHANGE_ME`/sem `APP_KEY`) e tem uma trava contra criar um banco novo e vazio por engano.

Secrets necessários no GitHub: `PROD_HOST`, `PROD_USER`, `PROD_PORT`, `PROD_SSH_KEY`, `PROD_APP_DIR`.

**Domínios de clientes:** o cliente aponta o DNS para o servidor, o domínio é adicionado como alias no cPanel (o AutoSSL emite o certificado) e cadastrado na empresa (`companies.domain`, sem `www`). Não precisa de configuração nova no Apache.

- Não rode `db:seed` em produção: ele cria usuários demo com senha conhecida.
- Passo a passo completo do servidor (Apache, chave SSH, primeiro deploy, backup): [docs/DEPLOY.md](docs/DEPLOY.md).

## Serviços

| Serviço | Container            | Porta host | Porta interna |
| ------- | -------------------- | ---------- | ------------- |
| app     | `cursos_api_app`     | 8000       | 8000          |
| queue   | `cursos_api_queue`   | —          | —             |
| scheduler | `cursos_api_scheduler` | —       | —             |
| db      | `cursos_api_db`      | 3308       | 3306          |
| redis   | `cursos_api_redis`   | 6380       | 6379          |
| frontend | `cursos_api_frontend` | 5174     | 5173          |
| mailpit | `cursos_api_mailpit` | 8025/1025  | 8025/1025     |

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

# Front-end (o serviço frontend já roda o Vite em modo dev)
docker compose exec frontend npm install
docker compose exec frontend npm run build
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

> Os exemplos novos usam Redis para cache, sessão e fila:
> `CACHE_STORE=redis`, `SESSION_DRIVER=redis` e `QUEUE_CONNECTION=redis`.
> Se o seu `.env` local antigo ainda usa `sync`, o worker ficará ocioso até você trocar.

## Variáveis de ambiente relevantes

| Variável | Descrição |
| --- | --- |
| `APP_URL` | URL pública da API/plataforma; em prod use o mesmo domínio do frontend |
| `FRONTEND_URL` | fallback para links de empresas sem domínio próprio |
| `FRONTEND_SCHEME` / `FRONTEND_PORT` | esquema/porta usados nos links por domínio |
| `PLATFORM_DOMAINS` | domínios da plataforma que nunca são tratados como tenant |
| `TRUSTED_PROXIES` | proxies confiáveis para HTTPS/host/IP atrás do Apache/Nginx (`*` em prod) |
| `CORS_ALLOWED_ORIGINS` | origens permitidas; em produção use somente o domínio da plataforma |
| `SANCTUM_EXPIRATION` | validade do token em minutos (vazio = nunca expira; recomendado 43200 em produção) |
| `MERCADOPAGO_WEBHOOK_SECRET` | segredo HMAC do webhook do MercadoPago (**obrigatório em produção**) |
| `MANUAL_WEBHOOK_SECRET` | segredo do header `X-Webhook-Secret` do gateway manual (**obrigatório em produção**) |
| `RESEND_API_KEY` | chave da conta Resend padrão usada quando a empresa não tem configuração própria |
| `VITE_*` | variáveis públicas do frontend, aplicadas no build Docker; não coloque segredos |

> Sem os segredos de webhook configurados, **todo** webhook é rejeitado com `401 invalid_webhook_signature` (falha fechada).

## Testes

```sh
docker compose exec app php artisan test
```

## API

- Base: `http://localhost:8000/api/v1`
- Auth: Bearer Token (Sanctum) — `POST /api/v1/auth/login` e `POST /api/v1/auth/register`
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

## Loja por empresa (multiempresa)

Cada empresa tem um slug publico e uma loja isolada:

```text
http://localhost:5174/e/{slug}/loja
```

- Produtos pertencem a empresa e devem ser vinculados a pelo menos uma turma.
- O admin escolhe pagamento unico ou assinatura mensal.
- Compra unica aceita PIX e cartao; assinatura usa cartao recorrente.
- Produtos de compra unica podem ter acesso permanente ou por quantidade de dias, contando da confirmacao do pagamento.
- O cadastro do aluno acontece no checkout e cria a conta na empresa do slug.
- O mesmo e-mail pode ser usado em empresas diferentes.

O admin encontra o link em Produtos ou em Empresas (superadmin). As credenciais do Mercado Pago ficam em Admin > Gateways.
