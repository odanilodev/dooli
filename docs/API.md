# API — Plataforma de Cursos

API REST versionada (`/api/v1`) construída em Laravel 13 + Sanctum, multi-tenant por `company_id`.

Base URL local: `http://localhost:8000`

---

## Convenções

### Envelope de resposta

Sucesso (recurso único):
```json
{ "success": true, "data": { } }
```

Sucesso (lista paginada):
```json
{
  "success": true,
  "data": [],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5,
    "from": 1,
    "to": 20
  }
}
```

Erro de validação (422):
```json
{
  "success": false,
  "message": "Os dados enviados são inválidos.",
  "errors": { "name": ["validation.required"] }
}
```

Erro de autorização (403):
```json
{ "success": false, "message": "Você não possui permissão para realizar esta ação.", "code": "forbidden" }
```

Recurso não encontrado (404):
```json
{ "success": false, "message": "Recurso não encontrado.", "code": "resource_not_found" }
```

Erro de regra de negócio (422): inclui `code` específico (ex.: `invalid_coupon`, `certificate_not_available`).

### Paginação

- `page` (padrão 1)
- `per_page` (padrão 20, **máximo 100**)

### Filtros e ordenação

Endpoints de listagem aceitam: `search`, `status`, `sort`, `direction` (`asc`/`desc`) e filtros específicos documentados por recurso.

### Autenticação

Bearer Token (Sanctum):
```
Authorization: Bearer {token}
```

### Multi-tenant

- Requisições autenticadas: a empresa é sempre a do usuário autenticado (não é possível forjar).
- Rotas públicas e webhooks: a empresa é resolvida pelo header **`X-Company`** (id da empresa). Sem o header, a resposta é `400 company_required`.

### Rate limits

| Limiter   | Limite         | Chave                | Aplicado a |
| --------- | -------------- | -------------------- | ---------- |
| `auth`    | 5/min          | login + IP           | `POST /auth/login` (anti brute force) |
| `public`  | 60/min         | IP                   | Rotas públicas (loja, verify) |
| `api`     | 120/min        | usuário (ou IP)      | Rotas autenticadas |
| `admin`   | 300/min        | usuário              | Rotas administrativas |
| `webhooks`| 600/min        | gateway + IP         | Webhooks de pagamento |

Ao exceder: `429` com `code: too_many_requests`.

---

## Autenticação

### POST /api/v1/auth/login
**Objetivo:** autenticar e obter token.
**Auth:** não. **Rate limit:** `auth`.
**Body:**
```json
{ "email": "admin@cursos.test", "password": "password", "device_name": "web" }
```
Aceita `email` ou `username` (um dos dois é obrigatório).
**Sucesso 200:**
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "Administrador", "email": "admin@cursos.test", "user_type": { "code": "admin" } },
    "token": "1|...",
    "token_type": "Bearer"
  }
}
```
**Erros:** `401 invalid_credentials` (usado também para conta/empresa inativa, para evitar enumeração), `422`, `429`.

### GET /api/v1/auth/me
**Auth:** Bearer. Retorna o usuário autenticado com `user_type` e `teams`.

### POST /api/v1/auth/logout
Revoga o token atual.

### POST /api/v1/auth/logout-all
Revoga todos os tokens do usuário.

### POST /api/v1/auth/forgot-password
**Auth:** não. **Rate limit:** `password` (3/min por IP).
**Body:** `{ "email": "aluno@cursos.test" }`
Envia o e-mail com o link de redefinição (o link aponta para o frontend: `FRONTEND_URL/#/reset-password?token=...&email=...`).
**Sucesso 200** — sempre a mesma resposta, exista ou não o e-mail (evita enumeração de contas).

### POST /api/v1/auth/reset-password
**Auth:** não. **Rate limit:** `password`.
**Body:**
```json
{ "token": "...", "email": "aluno@cursos.test", "password": "nova-senha", "password_confirmation": "nova-senha" }
```
**Sucesso 200.** Ao redefinir, **todos os tokens** do usuário são revogados.
**Erro:** `422 password_reset_failed` (token inválido/expirado).

---

## Perfil do usuário (autenticado)

### GET /api/v1/me/profile
Retorna o próprio perfil (com `user_type` e `teams`).

### PUT /api/v1/me/profile
Atualiza apenas dados do próprio usuário: `name`, `username`, `email`, `image`, `phone`, `address`, `city`.
Campos sensíveis (`status`, `user_type_id`, `password`) são **ignorados** aqui.

### PUT /api/v1/me/password
**Body:** `{ "current_password": "atual", "password": "nova", "password_confirmation": "nova" }`
**Erro:** `422` se `current_password` não conferir. Mantém o token atual e **revoga os demais**.

---

## Rotas públicas (header `X-Company`)

### GET /api/v1/store/products
Lista produtos de loja **ativos e já lançados** (`release_at` nulo ou no passado). Query: `search`, `page`, `per_page`.

### GET /api/v1/store/products/{slug}
Detalhe de um produto de loja ativo e já lançado (404 caso contrário).

### GET /api/v1/certificates/{code}/verify
Verifica a autenticidade de um certificado pelo código público (UUID). Retorna 404 se inválido/invalidado.
**Resposta (sem dados pessoais):**
```json
{ "success": true, "data": { "code": "uuid", "valid": true, "holder_name": "Aluno", "course": "Curso de PHP", "lessons_count": 10, "issued_at": "2026-09-17T12:00:00+00:00" } }
```

---

## Área do aluno (autenticado)

### GET /api/v1/me/courses
Lista apenas os cursos aos quais o aluno tem acesso (turma ativa + produto ativo + matrícula não expirada).

### GET /api/v1/me/courses/{course}
Conteúdo do curso (módulos → tópicos → aulas publicadas) com vídeo, documento e anexos. Retorna `403 unauthorized_course_access` se não houver acesso.

### GET /api/v1/me/courses/{course}/progress
Query opcional: `team_id`.
```json
{
  "success": true,
  "data": {
    "course_id": 1, "team_id": 1,
    "total_lessons": 10, "completed_lessons": 4,
    "percentage": 40.0, "completed_lesson_ids": [1,2,3,4]
  }
}
```

### GET /api/v1/me/courses/{course}/timeline
Histórico de ações do aluno no curso. Query: `team_id`.

### GET /api/v1/me/continue-watching
Lista os cursos **em andamento** com a próxima aula a assistir (1ª aula publicada ainda não concluída, na ordem módulo→tópico→aula).
```json
{
  "success": true,
  "data": [
    { "course_id": 2, "course_name": "Curso de biju", "lesson_id": 7, "lesson_name": "Aula de produção", "module_name": "Macramê", "duration": 1250, "percentage": 33.33 }
  ]
}
```
> `duration` (segundos, do vídeo da aula) pode ser `null`. Cursos com 0% ou 100% não aparecem.

### GET /api/v1/me/courses/{course}/documents/{document}
Baixa (stream) um documento da aula. Exige matrícula no curso (403). 404 se o documento não pertence ao curso ou o arquivo não existe no disco.
> Arquivos ficam privados em `storage/app/private/uploads/{company_id}/{path}`. O download usa o token Bearer (não é link público).

### GET /api/v1/me/courses/{course}/attachments/{attachment}
Baixa (stream) um anexo da aula. Mesmas regras do documento.

### POST /api/v1/me/progress/complete
**Objetivo:** concluir uma aula. O backend valida acesso e recalcula o progresso.
**Body:**
```json
{ "lesson_id": 10, "course_id": 1, "team_id": 1, "module_id": 2 }
```
Idempotente: repetir a conclusão não duplica o registro em `timelines`.
**Resposta:**
```json
{ "success": true, "data": { "already_completed": false, "progress": { "percentage": 10.0 } } }
```

### GET /api/v1/me/certificates
Lista os certificados emitidos para o aluno.

### POST /api/v1/me/certificates/generate
Gera o certificado somente se todos os critérios de conclusão forem atendidos no backend.
**Body:** `{ "course_id": 1, "team_id": 1 }`
**Erro:** `422 certificate_not_available`.

### GET /api/v1/me/notifications
Lista notificações do aluno. `POST /api/v1/me/notifications/{notification}/read` e `POST /api/v1/me/notifications/read-all`.
> Marcar como lida exige que a notificação seja destinada ao próprio usuário (404 caso contrário).

### Pedidos
- `GET /api/v1/me/orders` — pedidos do aluno
- `POST /api/v1/me/orders` — cria pedido; o backend recalcula subtotal/desconto/total
```json
{ "items": [ { "store_product_id": 1, "quantity": 1 } ], "coupon_code": "DESC10" }
```
- `GET /api/v1/me/orders/{order}` — detalhe (apenas do próprio aluno)
- `POST /api/v1/me/orders/{order}/cancel` — cancela pedido pendente

### Comentários e avaliações
- `GET /api/v1/comments?lesson_id=10` — apenas de cursos em que o aluno está matriculado
- `POST /api/v1/comments` — `{ content, lesson_id, module_id, course_id, team_id, image? }`; exige matrícula ativa na turma/curso
- `GET /api/v1/ratings?lesson_id=10` — apenas de cursos em que o aluno está matriculado
- `POST /api/v1/ratings` — `{ rating (0-10), lesson_id, module_id, course_id, team_id, image? }`; exige matrícula ativa; **uma avaliação por aluno/aula** (`409 duplicate_resource` se repetir)
> As listagens retornam apenas `id`, `name`, `username` e `image` do autor (sem e-mail/telefone/endereço).

---

## Área administrativa (`role:admin`)

Prefixo: `/api/v1/admin`. Todas exigem Bearer + role `admin` + rate limit `admin`.

### Cursos (`courses`)
`GET/POST /courses`, `GET/PUT/PATCH/DELETE /courses/{course}`.
Publicação: `POST /courses/{course}/publish`, `POST /courses/{course}/unpublish`.
Módulos do curso: `POST /courses/{course}/modules` (`module_id`, `sort_order`), `DELETE /courses/{course}/modules/{module}`, `PUT /courses/{course}/modules/reorder` (`ids: []`).

### Módulos (`modules`)
CRUD + `PUT /modules/{module}/topics/reorder` (`ids: []`).

### Tópicos (`topics`)
CRUD + `PUT /topics/{topic}/lessons/reorder` (`ids: []`).

### Aulas (`lessons`)
CRUD + `POST /lessons/{lesson}/publish`, `POST /lessons/{lesson}/unpublish`, `attachment_ids[]` opcional.
> Ao publicar/criar aulas em um curso com alunos concluídos, o backend dispara a regra de recálculo de certificados (ver `docs/ARCHITECTURE.md`).

### Turmas (`teams`)
CRUD +
- `GET /teams/{team}/students`
- `POST /teams/{team}/students` (`user_id`, `expired_at?`)
- `DELETE /teams/{team}/students/{user}`
- `GET /teams/{team}/courses`
- `POST /teams/{team}/courses` (`course_id`)
- `DELETE /teams/{team}/courses/{course}`

### Usuários (`users`) e tipos (`user-types`)
CRUD. `user_types.code` define o perfil (`admin`, `instructor`, `student`).

### Cupons (`coupons`)
CRUD. Campos: `code`, `type` (`fixed`/`percent`), `value`, `status`, `starts_at`, `expires_at`, `usage_limit`, `per_user_limit`, `min_order_amount`, `max_discount`, `store_product_ids[]`.
A validação/recalculo é sempre no backend.

### Produtos de loja (`store-products`)
CRUD. Campos: `course_id`, `title`, `slug`, `price`, `sale`, `status`, `release_at`, `team_ids[]` (turmas que a compra libera).

### Pagamentos (`payments`)
- `GET /payments`, `GET /payments/{payment}`
- `POST /payments` — `{ order_id, gateway_id, method, amount, metadata? }`
- `POST /payments/{payment}/refund` — `{ amount?, reason? }`

### Pedidos (`orders`)
- `GET /orders` — lista os pedidos da empresa (filtros: `status`, `user_id`, `search`, `sort`, `direction`)
- `GET /orders/{order}` — detalhe do pedido (itens, pagamentos, cupom, cliente)

### Certificados emitidos (`certificate-users`)
- `GET /certificate-users` — lista os certificados **emitidos** (filtros: `user_id`, `team_id`, `certificate_id`)
- `POST /certificate-users/{certificateUser}/revoke` — invalida um certificado emitido (`{ "reason": "..." }`)
> Após revogar, `GET /certificates/{code}/verify` passa a retornar `404`.

### Moderação
- `GET /comments`, `DELETE /comments/{comment}` — moderação de comentários
- `GET /ratings`, `DELETE /ratings/{rating}` — moderação de avaliações

### Auditoria (`datalogs`)
- `GET /datalogs` — trilha de auditoria (filtros: `module`, `action`, `user_id`, `date_from`, `date_to`)

### Gateways (`gateways`)
CRUD. `credentials`/`settings` são **cifrados em repouso** e **nunca retornados**; a resposta expõe apenas `has_credentials`.

### Vídeos (`videos`)
CRUD + `GET /videos/{video}/playback` (dados seguros de reprodução via adapter da plataforma, sem expor credenciais).

### Documentos, anexos e banners
`documents`, `attachments`, `banners` — CRUD.

### Certificados (modelos) (`certificates`)
CRUD dos templates por curso (`name`, `title`, `course_id`, `grade`, `version`, `time`).

### Notificações (`notifications`)
`GET /notifications`, `GET /notifications/{notification}`.

---

## Webhooks

### POST /api/v1/webhooks/payments/{gateway}
**Auth:** não. **Headers:** `X-Company` obrigatório. **Rate limit:** `webhooks`.
Verifica a **assinatura** conforme o adapter do gateway (sem segredo configurado, rejeita tudo com `401 invalid_webhook_signature`), deriva o tenant do pagamento resolvido, garante **idempotência** por `external_id` e processa via fila (`ProcessPaymentWebhookJob`).
**Resposta 202:** `{ "success": true, "data": { "status": "queued", "payment_id": 1 } }`
> Configure `MERCADOPAGO_WEBHOOK_SECRET` / `MANUAL_WEBHOOK_SECRET` no `.env`.

---

## Erros por código

| HTTP | code | Quando |
| ---- | ---- | ------ |
| 400 | `company_required` | rota pública/webhook sem `X-Company` |
| 401 | `unauthenticated`, `invalid_credentials`, `invalid_webhook_signature` | não autenticado / login inválido / assinatura de webhook inválida |
| 403 | `forbidden`, `unauthorized_course_access` | sem permissão / sem matrícula |
| 404 | `resource_not_found`, `course_not_found`, `company_not_found` | inexistente, de outra empresa ou empresa inativa |
| 409 | `duplicate_resource`, `resource_conflict` | registro duplicado (ex.: avaliação repetida) / registro com vínculos |
| 422 | `invalid_coupon`, `certificate_not_available`, `business_rule`, `payment_error` | regra de negócio/validação |
| 429 | `too_many_requests` | rate limit |

> Exclusões administrativas retornam **204 No Content**.
> A validação `Rule::exists` é sempre restrita ao `company_id` do usuário autenticado.

## Segurança

- Toda consulta é isolada por `company_id` (global scope + policies).
- Credenciais de gateway cifradas e nunca serializadas.
- Validação sempre no backend; valores de desconto/total recalculados no servidor.
- Conclusão de aula e emissão de certificado validados no backend (nunca por payload do frontend).
- Webhooks: assinatura obrigatória (falha fechada sem segredo configurado), tenant derivado do pagamento e idempotentes.
