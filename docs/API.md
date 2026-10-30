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
| `auth`    | 5/min          | login/e-mail + IP    | `POST /auth/login` e `POST /auth/register` (anti brute force) |
| `verification` | 5/min    | e-mail + IP          | Confirmação e reenvio de código de e-mail |
| `public`  | 60/min         | IP                   | Rotas públicas (loja, verify, página do certificado) |
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
**Erros:** `401 invalid_credentials` (usado também para conta/empresa inativa, para evitar enumeração), `403 email_not_verified` (senha correta, mas e-mail pendente), `422`, `429`.

### POST /api/v1/public/companies/{companySlug}/auth/register
**Objetivo:** criar uma conta de aluno e enviar o código de confirmação de e-mail.
**Auth:** não. **Rate limit:** `auth`. No domínio da empresa, use `POST /api/v1/public/tenant/auth/register`.
**Body:**
```json
{
  "name": "Novo Aluno",
  "email": "novo.aluno@example.com",
  "password": "senha-segura",
  "password_confirmation": "senha-segura",
  "terms": true,
  "referral_code": "joao123"
}
```
O `username` é gerado automaticamente a partir do e-mail (com sufixo numérico em caso de conflito). O tipo de usuário é sempre `student`, vinculado à empresa do header. Contas criadas pelo admin manualmente são verificadas automaticamente e não recebem código.
`referral_code` Ǹ opcional e registra a atribui��ǜo ao vendedor por 30 dias.
**Sucesso 201:**
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "name": "Novo Aluno", "email": "novo.aluno@example.com" },
    "verification_required": true
  }
}
```
A conta ainda **não** está autenticada; nenhum token é retornado antes da confirmação.
**Erros:** `400 company_required`, `422` (e-mail duplicado, senha/confirmação inválida ou termos não aceitos), `429`.

### POST /api/v1/public/companies/{companySlug}/auth/verify-email
**Objetivo:** confirmar o código de 6 dígitos e autenticar o aluno.
**Auth:** não. **Rate limit:** `verification`. No domínio da empresa, use `POST /api/v1/public/tenant/auth/verify-email`.
**Body:** `{ "email": "novo.aluno@example.com", "code": "123456", "device_name": "web" }`
**Sucesso 200:** mesmo payload do login (`user`, `token`, `token_type`).
**Erros:** `422 invalid_verification_code` (código inválido, expirado ou com tentativas esgotadas), `429`.

### POST /api/v1/public/companies/{companySlug}/auth/resend-verification
**Objetivo:** reenviar o código de confirmação.
**Auth:** não. **Rate limit:** `verification`. No domínio da empresa, use `POST /api/v1/public/tenant/auth/resend-verification`.
**Body:** `{ "email": "novo.aluno@example.com" }`
**Sucesso 200:** resposta genérica, exista ou não a conta. O servidor aplica cooldown de 60 segundos e gira o código anterior.
**Erros:** `429`.

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

### GET /api/v1/banners
Lista os banners **ativos** da empresa com `placement=home` (ordenados por `sort_order`), que alimentam o hero da home logada. Cada item traz o conteúdo do destaque (`eyebrow`, `title`, `title_highlight`, `description`, `quote`, `cta_*`, `secondary_*`) e `image_url` (URL pública da imagem enviada, ou `null`). Banners da loja usam `placement=store` e não aparecem aqui.

### GET /api/v1/storefront
Retorna `company` (`id`, `name`, `slug`, `public_store_url` e `gateways` ativos), `settings` e `banners` ativos da loja para a empresa atual via `X-Company`. É usado pela loja interna do aluno, inclusive para iniciar a compra no checkout existente. Os textos incluem catálogo, benefícios, loja interna e rodapé; os banners são os de `placement=store`.

### GET /api/v1/certificates/{code}/verify
Verifica a autenticidade de um certificado pelo código público (UUID). Retorna 404 se inválido/invalidado.
**Resposta (sem dados pessoais):**
```json
{ "success": true, "data": { "code": "A7K2M9QX", "valid": true, "holder_name": "Aluno", "course": "Curso de PHP", "lessons_count": 10, "issued_at": "2026-09-17T12:00:00+00:00" } }
```

### GET /api/v1/public/certificates/{code}
Payload da **página pública de visualização** do certificado. Não exige `X-Company` nem autenticação: o código público curto resolve o certificado e a empresa; UUIDs antigos continuam aceitos. Retorna 404 para certificado inexistente, revogado ou substituído.
- Inclui dados visuais do template (`certificate`), `modules` congelados, `holder_name`, `course`, `issued_at` e `share_url`.
- Se o certificado foi **substituído automaticamente**, retorna `{ "redirect_url": "..." }` apontando para o certificado válido atual; a página redireciona sozinha. Revogados manualmente continuam retornando 404.
- Não expõe e-mail, telefone, usuário, time ou identificadores internos.
- O endpoint e a página pública respondem com `X-Robots-Tag: noindex, nofollow`.

**Link público:** `${FRONTEND_URL}/v/{codigo}`. O QR code opcional do certificado usa esse endereço. Links antigos em `verificar.html?code={uuid}` continuam funcionando.

---

## Área do aluno (autenticado)

### GET /api/v1/me/courses
Lista apenas os cursos aos quais o aluno tem acesso (turma ativa + produto ativo + matrícula não expirada).

### GET /api/v1/me/courses/{course}
Conteúdo do curso (módulos → tópicos → aulas publicadas) com vídeo, documento e anexos. Retorna `403 unauthorized_course_access` se não houver acesso.
> **Liberação programada:** quando o curso/módulo/aula tem data de liberação (drip) para a turma, o item traz `available_at` (ISO) e `is_locked` (bool). A data efetiva é a **mais tardia** entre curso, módulo e aula. Itens sem agendamento não trazem esses campos.

### GET /api/v1/me/courses/{course}/progress
Query opcional: `team_id`.
```json
{
  "success": true,
  "data": {
    "course_id": 1, "team_id": 1,
    "total_lessons": 10, "completed_lessons": 4,
    "percentage": 40.0, "completed": false, "completed_at": null,
    "required_lessons": 0, "new_lessons_since_completion": 0,
    "completed_lesson_ids": [1,2,3,4]
  }
}
```
> `completed`/`completed_at`/`required_lessons` vêm de `course_completions`, que guarda o **marco mais recente** da conclusão. `completed` fica `true` mesmo que novas aulas sejam adicionadas depois; enquanto elas não forem concluídas, `new_lessons_since_completion` mostra o saldo. Ao concluir todas, `required_lessons` e `completed_at` avançam para o novo total e o indicador volta a `0`. `percentage` segue refletindo o total atual de aulas (ex.: 20/25 = 80%). Os mesmos campos aparecem em `/me/courses` e `/me/home`.

### GET /api/v1/me/courses/{course}/timeline
Histórico de ações do aluno no curso. Query: `team_id`.

### GET /api/v1/me/continue-watching
Lista os cursos **em andamento** com a próxima aula a assistir (1ª aula publicada ainda não concluída, na ordem módulo→tópico→aula).
```json
{
  "success": true,
  "data": [
    { "course_id": 2, "course_name": "Curso de biju", "lesson_id": 7, "lesson_name": "Aula de produção", "module_name": "Macramê", "image": "https://cdn.vdocipher.com/poster/....jpg", "duration": 1250, "resume_from": 42, "percentage": 33.33 }
  ]
}
```
> `duration` (segundos, do vídeo da aula) pode ser `null`. `resume_from` é a posição salva (segundos) para retomar; `image` é o poster do vídeo da aula (usado no card) e, quando o vídeo não tem poster, cai para a capa do curso (`image_url`), podendo ser `null`. Cursos com 0% ou 100% não aparecem. Cursos com **conclusão registrada** também não aparecem, mesmo que ganhem novas aulas depois.

### GET /api/v1/me/courses/{course}/documents/{document}
Baixa (stream) um documento da aula. Exige matrícula no curso (403). 404 se o documento não pertence ao curso ou o arquivo não existe no disco. Se o documento for um **link** (`url`), responde com `302` redirecionando para a URL externa.
> Arquivos ficam privados em `storage/app/private/uploads/{company_id}/{path}`. O download usa o token Bearer (não é link público).

### GET /api/v1/me/courses/{course}/attachments/{attachment}
Baixa (stream) um anexo da aula. Mesmas regras do documento.

### GET /api/v1/me/courses/{course}/lessons/{lesson}/video
Dados de reprodução (embed assinado) do vídeo da aula. Exige matrícula no curso (403); 404 se a aula não tem vídeo.
```json
{ "success": true, "data": { "platform": "vdocipher", "embed_url": "https://player.vdocipher.com/v2/?otp=...&playbackInfo=...", "video_code": "abc123", "duration": 1250, "resume_from": 137 } }
```
> O backend gera o OTP/URL temporária via adapter da plataforma configurada em `video_platforms` (por empresa). O segredo da API nunca é enviado ao cliente. Sem credencial configurada, devolve o link direto + `warning`. `resume_from` é a posição (segundos) salva do aluno para retomar o vídeo.
> **Pré-requisito:** se a aula tem `required_lesson_id` e o aluno ainda não concluiu essa aula no curso/team, responde **422** (`business_rule`) — a aula fica travada.
> **Agendamento:** se a aula ainda não foi liberada pela turma (data futura), responde **422** (`business_rule`).
> O módulo independente **Aulas ao vivo** usa `GET /me/live-classes` e não este endpoint.

### POST /api/v1/me/progress/video-position
Salva a posição de reprodução (retomar de onde parou). Exige matrícula (403); faz upsert por `user_id + lesson_id + course_id + team_id`.
**Body:**
```json
{ "lesson_id": 10, "course_id": 1, "team_id": 1, "position": 137, "duration": 600 }
```
**Resposta:**
```json
{ "success": true, "data": { "lesson_id": 10, "position_seconds": 137, "duration": 600 } }
```

### POST /api/v1/me/progress/complete
**Objetivo:** concluir uma aula. O backend valida acesso e recalcula o progresso.
**Body:**
```json
{ "lesson_id": 10, "course_id": 1, "team_id": 1, "module_id": 2 }
```
Idempotente: repetir a conclusão não duplica o registro em `timelines`.
> **Pré-requisito:** se a aula tem `required_lesson_id` e o aluno ainda não concluiu essa aula no curso/team, responde **422** (`business_rule`).
> **Agendamento:** se a aula ainda não foi liberada pela turma, responde **422** (`business_rule`).
> **Assistir até o final:** se a aula tem `require_full_watch = true` e um vídeo, o aluno só conclui depois que a posição salva em `video-progress` alcança a duração do vídeo (tolerância de 2s); caso contrário responde **422** (`business_rule`). Sem vídeo a flag é ignorada.
**Resposta:**
```json
{ "success": true, "data": { "already_completed": false, "progress": { "percentage": 10.0 } } }
```

### GET /api/v1/me/certificates
Lista os certificados emitidos para o aluno, mais recentes primeiro. Cada item traz `status`: `valid`, `replaced` (substituído por uma nova conclusão) ou `revoked` (revogação manual), além de `share_url` para a página pública.
> Ao concluir aulas adicionadas depois da emissão, um novo certificado é gerado com a grade e a carga atuais, e o anterior passa a `replaced`. O código antigo deixa de verificar.

### POST /api/v1/me/certificates/generate
Gera o certificado somente se todos os critérios de conclusão forem atendidos no backend. Se já existe certificado válido e o aluno concluiu novas aulas, substitui pelo novo snapshot.
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
- `GET /api/v1/comments?lesson_id=10` — apenas de cursos em que o aluno está matriculado; retorna comentários principais com `replies[]` e ignora aulas com `comments_enabled = false`
- `POST /api/v1/comments` — `{ content, lesson_id, module_id, course_id, team_id, image? }`; exige matrícula ativa na turma/curso e `lesson.comments_enabled = true` (403 quando a aba está desativada)
- `POST /api/v1/comments` com `{ parent_id, content }` — cria uma resposta vinculada ao comentário principal. Exige `lesson.student_replies_enabled = true` (403 `"As respostas de alunos estão desativadas para esta aula."`), matrícula ativa e que o pai seja um comentário principal da mesma aula/empresa
- `DELETE /api/v1/comments/{comment}` — exclui um comentário ou resposta **do próprio aluno**. Ao excluir um comentário principal, todas as respostas da thread são removidas em cascata (inclusive respostas de outros autores). Não permite excluir conteúdo de terceiros (403)
- `GET /api/v1/ratings?lesson_id=10` — apenas de cursos em que o aluno está matriculado
- `POST /api/v1/ratings` — `{ rating (0-10), lesson_id, module_id, course_id, team_id, image? }`; exige matrícula ativa; **uma avaliação por aluno/aula** (`409 duplicate_resource` se repetir)
> As listagens retornam apenas `id`, `name`, `username`, `image` e `image_url` do autor (sem e-mail/telefone/endereço). Respostas trazem `parent_id` e `is_admin` (true quando o autor é administrador).

---

## Área administrativa (`role:admin`)

Prefixo: `/api/v1/admin`. Todas exigem Bearer + role `admin` + rate limit `admin`.
Alguns recursos são restritos a **super admin** (ver seção *Super admin* abaixo).

### Cursos (`courses`)
`GET/POST /courses`, `GET/PUT/PATCH/DELETE /courses/{course}`.
Publicação: `POST /courses/{course}/publish`, `POST /courses/{course}/unpublish`.
Módulos do curso: `POST /courses/{course}/modules` (`module_id`, `sort_order`), `DELETE /courses/{course}/modules/{module}`, `PUT /courses/{course}/modules/reorder` (`ids: []`).

### Módulos (`modules`)
CRUD + `PUT /modules/{module}/topics/reorder` (`ids: []`) + `PUT /modules/{module}/lessons/reorder` (`ids: []`, `force?: bool`) para as aulas **sem tópico**.

### Tópicos (`topics`)
CRUD + `PUT /topics/{topic}/lessons/reorder` (`ids: []`, `force?: bool`).

### Aulas (`lessons`)
CRUD + `POST /lessons/{lesson}/publish`, `POST /lessons/{lesson}/unpublish`, `attachment_ids[]` opcional.
> **Vínculo:** `module_id` identifica o módulo da aula (obrigatório; se `topic_id` for enviado e `module_id` omitido, o backend deriva do tópico). `topic_id` é **opcional**: sem ele a aula pertence direto ao módulo. Se ambos vierem, o tópico tem de pertencer ao módulo (422 se não).
> **Tipo de conteúdo:** `content_type` (`video`, `document` ou `null`) define a **mídia principal** da aula e torna o campo correspondente obrigatório (`video`→`video_id`, `document`→`document_id`). Ao definir um tipo, o serviço limpa os campos dos outros tipos. Se `content_type` for omitido, ele é derivado de `video_id`/`document_id`. `null` = aula só com texto/HTML. O recurso retorna `content_type`.
> **Pré-requisito (trava):** `required_lesson_id` (nullable) aponta para outra aula do **mesmo curso** que precisa ser concluída antes. Só são aceitas aulas **anteriores** na ordem do curso (validação 422 `business_rule`). Ao criar, a aula entra no fim do tópico ou, sem tópico, no fim das aulas do módulo. O bloqueio do aluno é validado no backend em `me/courses/{course}/lessons/{lesson}/video` e `me/progress/complete` (422).
> **Assistir até o final:** `require_full_watch` (bool, nullable/padrão `false`). Com `true` e um `video_id` vinculado (`content_type = video`), o aluno só conclui a aula depois de assistir o vídeo até o fim (ver `me/progress/complete`). Em aulas de documento o serviço zera a flag.
> **Comentários:** `comments_enabled` (bool, padrão `true`) define se a aba de comentários aparece para o aluno. Quando `false`, a aba é ocultada, novos comentários retornam **403** e os comentários existentes ficam ocultos (mas preservados); ao reativar, voltam a aparecer.
> **Respostas em comentários:** `student_replies_enabled` (bool, padrão `true`) permite que alunos respondam aos comentários principais. Quando `false`, somente administradores podem responder (via `POST /admin/comments`); respostas de alunos já existentes continuam visíveis e somente leitura.

> Publicar/criar/editar/excluir aulas **não** revoga o certificado atual. Enquanto o aluno não concluir as novas aulas, ele continua válido com a grade antiga; ao concluir todas, o certificado é substituído por um novo com a grade atual (ver `docs/ARCHITECTURE.md`).
> **Reordenação** (`modules/reorder`, `topics/reorder`, `lessons/reorder`, `modules/{module}/lessons/reorder`): se a nova ordem deixar o pré-requisito de alguma aula depois dela, o backend responde **409** `prerequisite_conflict` com `errors.conflicts[]` (`lesson_id`, `lesson_name`, `required_lesson_id`, `required_lesson_name`) e **não** aplica nada. Reenviar com `force: true` aplica a ordem e **remove essas travas**, devolvendo `data.cleared` (ids das aulas destravadas).
> `topics/{topic}/lessons/reorder` e `modules/{module}/lessons/reorder` também **movem** as aulas listadas para o tópico/módulo indicado (só ids do mesmo módulo são aceitos; ids de outros módulos são ignorados). É assim que o builder move uma aula entre tópicos do mesmo módulo ou entre tópico e módulo.

### Turmas (`teams`)
CRUD +
- `GET /teams/{team}/students`
- `POST /teams/{team}/students` (`user_id`, `expired_at?`)
- `DELETE /teams/{team}/students/{user}`
- `GET /teams/{team}/courses`
- `POST /teams/{team}/courses` (`course_id`)
- `DELETE /teams/{team}/courses/{course}`
- `GET /teams/{team}/schedules` — árvore curso → módulo → aula com `available_at` de cada nível
- `PUT /teams/{team}/schedules` — agendamento por item (`schedulable_type`: `course`/`module`/`lesson`, `schedulable_id`, `available_at`; `null` limpa). O item precisa pertencer à turma (senão 422 `business_rule`)

### Usuários (`users`) e tipos (`user-types`)
CRUD. `user_types.code` define o perfil (`admin`, `instructor`, `student`).

### Cupons (`coupons`)
CRUD. Campos: `code`, `type` (`fixed`/`percent`), `value`, `status`, `starts_at`, `expires_at`, `usage_limit`, `per_user_limit`, `min_order_amount`, `max_discount`, `store_product_ids[]`.
A validação/recalculo é sempre no backend.

### Produtos de loja (`store-products`)
CRUD. Campos: `title`, `slug`, `description`, `tags[]`, `price`, `sale`, `status`, `release_at`, `team_ids[]` (turmas que a compra libera), `image` (URL/caminho) e `image_file` (upload JPG/PNG/WEBP até 4 MB). A resposta expõe `image_url`, `course_id` (derivado do primeiro curso ativo das turmas, usado em relatórios) e, no detalhe, `teams[]`. Ao menos uma turma selecionada precisa ter curso ativo.

### Pagamentos (`payments`)
- `GET /payments`, `GET /payments/{payment}`
- `POST /payments` — `{ order_id, gateway_id, method, amount, metadata? }`
- `POST /payments/{payment}/refund` — `{ amount?, reason? }`

### Pedidos (`orders`)
- `GET /orders` — lista os pedidos da empresa (filtros: `status`, `user_id`, `search`, `sort`, `direction`)
- `GET /orders/{order}` — detalhe do pedido (itens, pagamentos, cupom, cliente)
- Filtro adicional em `GET /orders`: `seller_id`.

### Vendedores (`sellers`)
- `GET /sellers` - lista com métricas (filtros: `search`, `status`, `sort`, `direction`)
- `POST /sellers` - cria vendedor (`name`, `email?`, `phone?`, `user_id?`, `code?`, `commission_percent`, `status?`, `notes?`)
- `GET /sellers/{seller}` - detalhe com métricas, vendas por produto e pedidos recentes
- `PUT /sellers/{seller}` - atualiza vendedor
- `DELETE /sellers/{seller}` - exclui apenas vendedor sem histórico
- `POST /sellers/{seller}/rotate-kit` - rotaciona o token do kit de links

### Comissões (`commissions`)
- `GET /commissions` - extrato por item (filtros: `search`, `status`, `seller_id`, `product_id`, `order_id`, `date_from`, `date_to`)
- `GET /commissions/summary` - totais de a pagar, pagas, estornadas e geradas
- `GET /commissions/export` - exporta XLSX com os mesmos filtros
- `POST /commissions/{commission}/pay` - marca uma comissão como paga (`paid_at?`, `payment_reference?`, `payment_notes?`)
- `POST /commissions/bulk-pay` - marca várias comissões como pagas (`ids[]`)

> Regras completas de atribuição, kit de links, congelamento de percentual e estornos em [`SELLERS.md`](SELLERS.md).


### Certificados emitidos (`certificate-users`)
- `GET /certificate-users` — lista os certificados **emitidos** (filtros: `user_id`, `team_id`, `certificate_id`, `course_id`); cada item inclui `modules` com a grade congelada e `status` (`valid`, `replaced` ou `revoked`)
- `POST /certificate-users/{certificateUser}/revoke` — revoga manualmente um certificado emitido (`{ "reason": "..." }`)
- `POST /certificates/{certificate}/transfer-issued` — move os certificados **válidos** do modelo de origem para `target_certificate_id`, que deve ser um modelo do mesmo curso. Preserva código, data de emissão, carga e grade congelada; revogados e substituídos não são alterados. Retorna `{ source_certificate_id, target_certificate_id, transferred, skipped }`.
> Adicionar/editar/excluir aulas **não** revoga o certificado enquanto o aluno não concluir as novas aulas. Ao concluir todas, um novo certificado é emitido com o novo total/grade e o anterior fica no histórico como **Substituído** (`invalidation_reason` fixo), mantendo os snapshots antigos. Só existe **um certificado válido** por `user+curso+time+template`; a **conclusão do aluno vive em `course_completions`** e avança para o marco mais recente — revogar o certificado **não** desfaz a conclusão, e revogação manual não é reemitida automaticamente.
> Criar/atualizar um template dispara emissão retroativa (`issuePendingForCourse`) apenas para alunos concluídos que **nunca** receberam certificado; revogados/replaced nunca são reemitidos por esse caminho.
> Após revogar ou substituir, `GET /certificates/{code}/verify` e `GET /public/certificates/{code}` passam a retornar `404` para o código antigo.
> Comando `php artisan certificates:restore-recalculated [--dry-run]` revalida certificados que foram revogados automaticamente pelo antigo recálculo (motivo `"Novas aulas adicionadas ao curso após a conclusão."`); ignorados os que já tenham certificado válido mais recente e as revogações manuais.
> Comando `php artisan completions:backfill [--dry-run] [--report-manual]` cria `course_completions` a partir de certificados **válidos** e **invalidados automaticamente**; revogações manuais são apenas listadas para revisão.
> Comando `php artisan certificates:backfill-modules [--dry-run]` preenche `certificate_users.modules` nos certificados antigos usando as aulas concluídas da conclusão/timeline; módulos criados depois da conclusão não entram.

### Moderação
- `GET /comments` — moderação; `roots_only=1` retorna comentários principais com `replies[]`, `course`, `module`, `lesson`, `replies_count` e `awaiting_reply`
  - filtros: `course_id`, `lesson_id`, `user_id` e `status` (`awaiting` = sem resposta de administrador; `answered` = com resposta de administrador)
  - quando `roots_only=1` ou `status` é informado, `meta.stats` traz `{ total, awaiting, answered }` no escopo de curso/aula
- `POST /comments` — `{ parent_id, content }`; resposta do administrador a um comentário principal (permitida mesmo com `student_replies_enabled = false`)
- `DELETE /comments/{comment}` — exclui o comentário (respostas são removidas em cascata)
- `DELETE /comments/bulk` — `{ "ids": [1, 2] }`; exclui até 100 comentários/respostas da empresa. Comentários principais removem suas respostas em cascata. Retorna `{ "deleted": 2 }`
- `GET /ratings`, `DELETE /ratings/{rating}` — moderação de avaliações

### Auditoria (`datalogs`)
- `GET /datalogs` — trilha de auditoria (filtros: `module`, `action`, `user_id`, `date_from`, `date_to`)

### Gateways (`gateways`)
CRUD. `credentials`/`settings` são **cifrados em repouso** e **nunca retornados**; a resposta expõe apenas `has_credentials`.

### Vídeos (`videos`)
CRUD + `GET /videos/{video}/playback` (dados seguros de reprodução via adapter da plataforma, sem expor credenciais).
- `POST /videos/import-url` — `{ title, url, folder_id?, status? }`. O provedor baixa o vídeo do link; cria o registro já com `code`/`provider_status` e enfileira o sync.
- `POST /videos/upload-credentials` — `{ title, folder_id?, status? }`. Devolve `{ video, upload }`, onde `upload` são credenciais **temporárias** (`uploadLink`, `policy`, `x-amz-*`) para o navegador enviar o arquivo **direto ao provedor**, sem passar pelo limite de upload do PHP. O upload/import é feito pelo próprio formulário de vídeo (`VideoUploader.vue`), exibido quando o player é `vdocipher`.
- `POST /videos/{video}/sync` — consulta o status no provedor; quando `ready`, preenche `time` (duração) e `image` (poster).
- `POST /videos/sync` — verificação em lote (botão "Atualizar"): atualiza o status dos vídeos da empresa que ainda não estão `ready`; best-effort e retorna `{ checked, ready, pending, failed }`.
> `provider_status` é normalizado em minúsculas (`pre-upload`, `queued`, `processing`, `ready`), pois o provedor varia a caixa (ex.: `PRE-Upload`).
> Adapter real de upload/import para o VdoCipher (usa o mesmo `api_secret` do playback); sem credencial configurada responde `422 business_rule`. `provider_status`: `Pre-Upload` → `Queued` → `ready`. Após import/upload o job `SyncVideoProviderStatusJob` faz o polling em segundo plano.
> Erros do provedor são repassados na `message` (ex.: limite do plano trial do VdoCipher); **401** indica `api_secret` inválido.
> Excluir um vídeo (`DELETE /videos/{video}`) também remove o vídeo no provedor quando a plataforma suporta, liberando a cota.

### Plataformas de vídeo (`video-platforms`)
CRUD. Configura o provedor de vídeo **por empresa** (any-platform): `code` ∈ `vdocipher`, `vimeo`, `fastpix`, `external`. `credentials` (ex.: `api_secret` do VdoCipher) é **cifrado em repouso** e **nunca retornado**; a resposta expõe apenas `has_credentials`.

### Documentos e anexos
`documents`, `attachments` — CRUD. Um documento tem **duas origens**: arquivo enviado (`file`, multipart; grava `path` no disco privado `materials`) ou **link externo** (`url`). Informe `file`, `path` ou `url` (ao menos um). Tipos aceitos no upload: `pdf, doc, docx, ppt, pptx, xls, xlsx, txt, csv, zip` (máx. 20 MB). A aula referencia o documento por `document_id`.

### Banners (`banners`)
CRUD. O campo `placement` separa os banners de `home` (hero da home logada) e `store` (carrossel das lojas). Campos: `name`, `link`, `sort_order`, `status` e o conteúdo do destaque (`eyebrow`, `title`, `title_highlight`, `description`, `quote`, `cta_label`, `cta_link`, `secondary_label`, `secondary_link`).
- **Imagem:** envie como `multipart/form-data` no campo `image` (`jpg/jpeg/png/webp`, máx. 4 MB). O arquivo é salvo no disco `public` em `banners/{company_id}/.` e a resposta expõe `image` (caminho) e `image_url` (URL pública). Ao substituir/excluir o banner, o arquivo antigo é removido.
- Requer `php artisan storage:link` para servir as imagens em `/storage/...`.
- Endpoint público da home: `GET /api/v1/banners` (apenas `placement=home` e ativos).
- A listagem admin aceita `placement` como filtro.

### Loja (`storefront`)
- `GET /api/v1/admin/storefront` - textos atuais da loja.
- `PUT /api/v1/admin/storefront` - salva `catalog` (`eyebrow`, `title`, `description`), `benefits` (até três itens com `title`/`description`), `internal` (`eyebrow`, `title`, `description`) e `footer.note`.
- Restrito ao admin da empresa; o tenant vem do usuário autenticado, nunca do payload.
- A loja pública recebe os mesmos dados em `storefront` no endpoint da empresa por slug.

### Certificados (modelos) (`certificates`)
CRUD dos templates por curso (`name`, `title`, `course_id`, `version`, `time`). A listagem expõe `issued_certificates_count` (todos os emitidos) e `valid_certificates_count` (válidos).
- **Visual:** `body` (texto com variáveis `{aluno}`, `{curso}`, `{data}`, `{carga_horaria}`, `{codigo}`), `signatory_name`, `signatory_title` e os uploads `background` (fundo) e `signature` (assinatura).
- **Uploads:** envie como `multipart/form-data` (`jpg/jpeg/png/webp`, máx. 4 MB). Os arquivos são salvos no disco `public` em `certificates/{company_id}/…` e a resposta expõe `background`/`signature`/`modules_background` (caminho) e `background_url`/`signature_url`/`modules_background_url` (URL pública). Ao substituir/excluir, o arquivo antigo é removido. Para remover fundo do certificado, assinatura ou fundo próprio da grade, envie `remove_background=1`, `remove_signature=1` e/ou `remove_modules_background=1`.
- **Versões:** cada linha é uma versão do certificado do curso (a mais recente é a **atual**). Para criar uma nova versão clonando a atual, envie `copy_from` (id do template de origem): as imagens são copiadas para novos arquivos (não compartilhados), e uploads enviados no mesmo request têm precedência. Envie `remove_background=1`, `remove_signature=1` e/ou `remove_modules_background=1` para não copiar as imagens correspondentes.
- **Bloqueio de edição:** um template que já possui **qualquer** certificado emitido (válido, substituído ou revogado) não pode ser alterado (`422 business_rule`). Crie uma nova versão, ajuste o novo modelo enquanto ele não tem alunos e use a transferência de válidos. A transferência troca apenas o `certificate_id` dos certificados válidos; snapshots e códigos permanecem intactos.
- **Layout visual (`layout`):** objeto JSON por campo (`eyebrow`, `title`, `body`, `modules`, `signature`, `signature_line`, `signatory_name`, `signatory_title`, `code`, `qr`) com `x`/`y` (0–100, % da arte), `width` (5–100, % da arte; usado em `title`/`body`/`modules` com padrão 80 e em `signature_line` com padrão 18), `color` (`#rrggbb` ou `null` para o padrão), `font`, `size` (px na largura de referência de 1000px), `align` (`left|center|right`), `bold` e `italic`. No `multipart/form-data`, envie `layout` como string JSON. Campos ausentes usam o layout padrão. O campo `modules` é a grade do curso na frente do certificado (quando `modules_placement=front`), exibida somente quando o certificado emitido possui módulos. A lista aceita `format` (`numbered`, `bulleted`, `plain` ou `inline`), `columns` (1–4, padrão 2) e `separator` (`bullet`, `comma`, `pipe` ou `dash`, padrão `bullet`; usado apenas em `inline`).
- **QR code:** `qr_enabled` (boolean, padrão `false`) habilita o QR; `qr_placement` (`front`, `modules` ou `both`, padrão `front`) escolhe onde ele aparece. O QR aponta para `${FRONTEND_URL}/v/{codigo}` e é gerado no frontend a partir do `share_url`. A posição/tamanho são configurados em `layout.qr` (frente) e `modules_layout.qr` (página extra/verso).
- **Exibição da grade:** `modules_placement` (`none`, `front`, `extra` ou `back`) define onde a grade aparece. `front` usa o campo `modules` do `layout`; `extra` e `back` geram uma segunda página A4 (a diferença é só o rótulo/uso de duplex); `none` oculta a grade. O padrão é `front`.
- **Página separada da grade:** `modules_title` é o título da página (padrão “Módulos do curso”) e `modules_layout` é um JSON com os campos `heading`, `list` e `qr`, no mesmo formato visual do `layout` (largura padrão 80 no título e 86 na lista). A `list` aceita os mesmos `format`/`columns`/`separator` da grade da frente. `modules_background` é o fundo próprio opcional; sem ele, a página fica branca. No `multipart/form-data`, envie `modules_layout` como string JSON.
- A emissão usa o template **mais recente** do curso; a resposta do aluno (`me/certificates`) já inclui os campos visuais, o `layout`, a configuração da página da grade e `modules` (fotografia da grade no momento da emissão). Como templates com alunos emitidos ficam bloqueados, para atualizar o visual de certificados já válidos crie uma nova versão e transfira os alunos; a transferência preserva código, data, carga e grade, alterando somente o modelo exibido.
- A grade `modules` é um array `[{ "id": 1, "name": "Módulo" }]`. Cada emissão congela a grade: módulos criados depois **não** entram no certificado antigo; eles entram no novo certificado emitido após o aluno concluir as novas aulas. O certificado anterior permanece no histórico como `replaced`. Para certificados antigos, rode `php artisan certificates:backfill-modules [--dry-run]`; o comando usa as aulas concluídas para recuperar a grade histórica.

### Notificações (`notifications`)
`GET /notifications`, `GET /notifications/{notification}`.

---

## Super admin

O super admin é o usuário com a flag `users.is_super_admin = true` (mantém `user_type = admin`, então herda todo o acesso administrativo normal). A flag **não** é mass-assignable: só é concedida por seeder ou pelo bootstrap de empresa. Ela é exposta em leitura (`/auth/me`, resources de usuário).

> **UI do super admin:** para reduzir ruído, o menu lateral do super admin mostra apenas **Sistema > Empresas/Menus** e **Conta** (Meu perfil, Configurações) — a identidade visual por empresa fica dentro de Empresas. As páginas de empresa (`/admin/cursos`, `/admin/turmas`, `/admin/videos`, `/admin/usuarios` etc.) são bloqueadas por rota no frontend e redirecionam para Empresas. Isso é uma limitação de **interface**: no backend o super admin continua sendo admin.

Recursos restritos a super admin (middleware `super.admin` + policies; um admin comum recebe **403**):

- **Menus**: `GET/POST /admin/menus`, `GET/PUT/DELETE /admin/menus/{menu}` — config global de menus da plataforma.
- **Tema (identidade visual, por empresa)**: `GET/PUT /admin/companies/{company}/settings` — cor primária e logos para **tema claro** (`logo_light`) e **tema escuro** (`logo_dark`) de uma empresa específica, editáveis na página Empresas. Se apenas um logo for enviado, ele é usado nos dois temas. `logo`/`logo_url` continuam como aliases legados do logo claro.
- **Empresas**:
  - `GET /admin/companies` — lista/pagina empresas (busca por `name`, filtro `status`).
  - `POST /admin/companies` — cria a empresa e faz o bootstrap: tipos de usuário padrão (`admin`, `instructor`, `student`) + o primeiro admin. Campos: `name`, `domain?`, `status?`, `admin_name`, `admin_username`, `admin_email`, `admin_password`. O `domain` é opcional, único, salvo apenas como host (sem protocolo, `www.` ou caminho) e normalizado em minúsculas.
  - `GET /admin/companies/{company}`, `PUT /admin/companies/{company}` (`name`, `slug?`, `domain?`, `status`).

> Um admin comum continua gerenciando o resto (cursos, módulos, aulas, turmas, usuários, tipos de usuário, vídeos, documentos, banners, relatórios...).

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
| 403 | `forbidden`, `unauthorized_course_access`, `email_not_verified` | sem permissão / sem matrícula / e-mail pendente de confirmação |
| 404 | `resource_not_found`, `course_not_found`, `company_not_found` | inexistente, de outra empresa ou empresa inativa |
| 409 | `duplicate_resource`, `resource_conflict` | registro duplicado (ex.: avaliação repetida) / registro com vínculos |
| 422 | `invalid_coupon`, `certificate_not_available`, `business_rule`, `payment_error`, `invalid_verification_code` | regra de negócio/validação / código de e-mail inválido ou expirado |
| 429 | `too_many_requests` | rate limit |

> Exclusões administrativas retornam **204 No Content**.
> A validação `Rule::exists` é sempre restrita ao `company_id` do usuário autenticado.

## Segurança

- Toda consulta é isolada por `company_id` (global scope + policies).
- Credenciais de gateway cifradas e nunca serializadas.
- Validação sempre no backend; valores de desconto/total recalculados no servidor.
- Conclusão de aula e emissão de certificado validados no backend (nunca por payload do frontend).
- Confirmação de e-mail com código de 6 dígitos, hash no banco, validade de 15 minutos e máximo de 5 tentativas.
- Webhooks: assinatura obrigatória (falha fechada sem segredo configurado), tenant derivado do pagamento e idempotentes.

## Loja publica por empresa

Cada empresa possui um `slug` unico. A loja publica resolve o tenant pela URL, sem confiar em `X-Company`:

- `GET /api/v1/public/companies/{companySlug}`
- `GET /api/v1/public/companies/{companySlug}/store/products`
- `GET /api/v1/public/companies/{companySlug}/store/products/{productSlug}`
- `GET /api/v1/public/companies/{companySlug}/seller-kits/{token}`
- `POST /api/v1/public/companies/{companySlug}/auth/login`
- `POST /api/v1/public/companies/{companySlug}/auth/register`
- `POST /api/v1/public/companies/{companySlug}/auth/verify-email`
- `POST /api/v1/public/companies/{companySlug}/auth/resend-verification`
- `POST /api/v1/public/companies/{companySlug}/auth/forgot-password`
- `POST /api/v1/public/companies/{companySlug}/auth/reset-password`

O cadastro cria sempre um aluno da empresa do slug. O mesmo e-mail pode existir em empresas diferentes; a unicidade e `(company_id, email)` e `(company_id, username)`. Alunos cadastrados pelo site só entram após confirmar o código de 6 dígitos.

`GET /api/v1/public/companies/{companySlug}` inclui `storefront.settings` e `storefront.banners` com os banners ativos de `placement=store`, permitindo que a loja pública renderize o carrossel e os textos configurados pelo admin sem requisições adicionais.

## Loja publica por dominio

Cada empresa pode ter um `domain` unico. O host e a chave do tenant: a loja abre na raiz do dominio, o login fica em `/entrar` e o painel em `/inicio`. O frontend envia o hostname no header **`X-Company-Domain`**; o middleware `company.domain` resolve a empresa ativa.

- `GET /api/v1/public/tenant` - empresa, branding, storefront e banners
- `GET /api/v1/public/tenant/store/products`
- `GET /api/v1/public/tenant/store/products/{productSlug}`
- `GET /api/v1/public/tenant/seller-kits/{token}`
- `POST /api/v1/public/tenant/auth/login`
- `POST /api/v1/public/tenant/auth/register`
- `POST /api/v1/public/tenant/auth/verify-email`
- `POST /api/v1/public/tenant/auth/resend-verification`
- `POST /api/v1/public/tenant/auth/forgot-password`
- `POST /api/v1/public/tenant/auth/reset-password`

Headers:

- `X-Company-Domain`: hostname do tenant (ex.: `ourovelho.com.br`). Aceita `www.`, maiusculas e porta; e normalizado para o host.
- `X-Company`: continua para dev/local, rotas internas e legado.
- Rotas autenticadas ignoram o dominio e usam sempre a empresa do usuario.

Dominios centrais da plataforma (super admin) ficam em `PLATFORM_DOMAINS` (virgula). Eles nunca sao resolvidos como tenant. As rotas antigas `/public/companies/{companySlug}` permanecem como fallback local/legado.

Reset de senha usa `https://{domain}/reset-password` quando a empresa tem dominio; sem dominio, usa `FRONTEND_URL/#/reset-password`. Links publicos de loja, produto, pedido, assinatura e kit de vendedor usam `https://{domain}/...` ou o fallback `FRONTEND_URL/e/{slug}/...`.

### Produto

`store_products` agora possui:

- `billing_type`: `one_time` ou `subscription`;
- `billing_interval`: `month`;
- `access_duration_days`: nulo para acesso permanente, ou quantidade de dias;
- `grace_days`: tolerancia de assinatura.

Todo produto deve ter pelo menos uma turma (`team_ids`). A compra libera todos os cursos ativos das turmas vinculadas. O prazo de acesso conta da confirmacao do pagamento.

### Checkout e pagamentos

- Compra unica: `POST /api/v1/me/orders` e depois `POST /api/v1/me/orders/{order}/pay` com `method=pix` ou `method=credit_card`.
- PIX retorna os dados do QR em `payment.metadata`.
- Cartao usa Checkout Pro e retorna `checkout_url`.
- Assinatura: `POST /api/v1/me/subscriptions` com `store_product_id` e `gateway_id`.
- Consulta: `GET /api/v1/me/subscriptions`; cancelamento: `POST /api/v1/me/subscriptions/{subscription}/cancel`.

### Webhooks por empresa

- `POST /api/v1/webhooks/companies/{companySlug}/payments/{gateway}`
- `POST /api/v1/webhooks/companies/{companySlug}/subscriptions/{gateway}`

O segredo do webhook e lido das credenciais do gateway da empresa (`webhook_secret`), com fallback para a configuracao global.
