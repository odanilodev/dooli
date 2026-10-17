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

### GET /api/v1/banners
Lista os banners **ativos** da empresa (ordenados por `sort_order`), que alimentam o hero da home. Cada item traz o conteúdo do destaque (`eyebrow`, `title`, `title_highlight`, `description`, `quote`, `cta_*`, `secondary_*`) e `image_url` (URL pública da imagem enviada, ou `null`).

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
> `completed`/`completed_at`/`required_lessons` vêm de `course_completions` (**histórico e persistente**): `completed` fica `true` mesmo que novas aulas sejam adicionadas depois. `percentage` segue refletindo o total atual de aulas (ex.: 20/25 = 80%) e `new_lessons_since_completion` indica quantas aulas novas existem desde a conclusão. Os mesmos campos aparecem em `/me/courses` e `/me/home`.

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
> `duration` (segundos, do vídeo da aula) pode ser `null`. `resume_from` é a posição salva (segundos) para retomar; `image` é o poster do vídeo da aula (usado no card) e, quando o vídeo não tem poster, cai para a capa do curso (`image_url`), podendo ser `null`. Cursos com 0% ou 100% não aparecem. Cursos com **conclusão histórica** (certificado válido) também não aparecem, mesmo que ganhem novas aulas depois.

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
> **Aulas ao vivo** não usam este endpoint: quando a aula tem `webinar_link`, o frontend embute a URL (iframe). Cada aula em `GET /me/courses/{course}` traz `webinar_link` e `webinar_starts_at` (ISO, nullable): no futuro → card "EM BREVE"; passado/nulo → player ao vivo com selo "AO VIVO".

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
> **Tipo de conteúdo:** `content_type` (`video`, `document`, `live` ou `null`) define a **mídia principal** da aula e torna o campo correspondente obrigatório (`video`→`video_id`, `document`→`document_id`, `live`→`webinar_link`). Ao definir um tipo, o serviço limpa os campos dos outros tipos. Se `content_type` for omitido, ele é derivado de `video_id`/`document_id`/`webinar_link`. `null` = aula só com texto/HTML. O recurso retorna `content_type`.
> **Pré-requisito (trava):** `required_lesson_id` (nullable) aponta para outra aula do **mesmo curso** que precisa ser concluída antes. Só são aceitas aulas **anteriores** na ordem do curso (validação 422 `business_rule`). Ao criar, a aula entra no fim do tópico ou, sem tópico, no fim das aulas do módulo. O bloqueio do aluno é validado no backend em `me/courses/{course}/lessons/{lesson}/video` e `me/progress/complete` (422).
> **Assistir até o final:** `require_full_watch` (bool, nullable/padrão `false`). Com `true` e um `video_id` vinculado (`content_type = video`), o aluno só conclui a aula depois de assistir o vídeo até o fim (ver `me/progress/complete`). Em aulas de documento/ao vivo o serviço zera a flag.
> **Aula ao vivo:** `webinar_link` (URL de embed, ex.: `https://player.vdocipher.com/live-v2?liveId=...`) + `webinar_starts_at` (data/hora agendada, nullable). A aula com `webinar_link` é renderizada como live no player do aluno (iframe), sem passar pelo fluxo de OTP/`videos`. Antes de `webinar_starts_at` o frontend mostra um card "EM BREVE"; depois (ou sem data) mostra o iframe com selo "AO VIVO".
> Publicar/criar/editar/excluir aulas **não** revoga certificados já emitidos. As novas aulas contam apenas para alunos que ainda não receberam o certificado (ver `docs/ARCHITECTURE.md`).
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
CRUD. Campos: `course_id`, `title`, `slug`, `price`, `sale`, `status`, `release_at`, `team_ids[]` (turmas que a compra libera).

### Pagamentos (`payments`)
- `GET /payments`, `GET /payments/{payment}`
- `POST /payments` — `{ order_id, gateway_id, method, amount, metadata? }`
- `POST /payments/{payment}/refund` — `{ amount?, reason? }`

### Pedidos (`orders`)
- `GET /orders` — lista os pedidos da empresa (filtros: `status`, `user_id`, `search`, `sort`, `direction`)
- `GET /orders/{order}` — detalhe do pedido (itens, pagamentos, cupom, cliente)

### Certificados emitidos (`certificate-users`)
- `GET /certificate-users` — lista os certificados **emitidos** (filtros: `user_id`, `team_id`, `certificate_id`, `course_id`)
- `POST /certificate-users/{certificateUser}/revoke` — invalida um certificado emitido (`{ "reason": "..." }`)
> Um certificado emitido é **histórico e permanente**: adicionar/editar/excluir aulas **não** o revoga. A emissão é única por `user+curso+time` (sem reemissão automática) e a **conclusão do aluno vive em `course_completions`** — revogar o certificado **não** desfaz a conclusão.
> Criar/atualizar um template dispara emissão retroativa (`issuePendingForCourse`) apenas para alunos concluídos que **nunca** receberam certificado; revogados (manual ou automático) nunca são reemitidos.
> Após revogar, `GET /certificates/{code}/verify` passa a retornar `404`.
> Comando `php artisan certificates:restore-recalculated [--dry-run]` revalida certificados que foram revogados automaticamente pelo antigo recálculo (motivo `"Novas aulas adicionadas ao curso após a conclusão."`); ignorados os que já tenham certificado válido mais recente e as revogações manuais.
> Comando `php artisan completions:backfill [--dry-run] [--report-manual]` cria `course_completions` a partir de certificados **válidos** e **invalidados automaticamente**; revogações manuais são apenas listadas para revisão.

### Moderação
- `GET /comments`, `DELETE /comments/{comment}` — moderação de comentários
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
CRUD. Configura o provedor de vídeo **por empresa** (any-platform): `code` ∈ `panda`, `vdocipher`, `vimeo`, `fastpix`, `external`. `credentials` (ex.: `api_secret` do VdoCipher, `api_key` do Panda) é **cifrado em repouso** e **nunca retornado**; a resposta expõe apenas `has_credentials`.

### Documentos e anexos
`documents`, `attachments` — CRUD. Um documento tem **duas origens**: arquivo enviado (`file`, multipart; grava `path` no disco privado `materials`) ou **link externo** (`url`). Informe `file`, `path` ou `url` (ao menos um). Tipos aceitos no upload: `pdf, doc, docx, ppt, pptx, xls, xlsx, txt, csv, zip` (máx. 20 MB). A aula referencia o documento por `document_id`.

### Banners (`banners`)
CRUD. Controlam o **hero da home**: `name`, `link`, `sort_order`, `status` e o conteúdo do destaque (`eyebrow`, `title`, `title_highlight`, `description`, `quote`, `cta_label`, `cta_link`, `secondary_label`, `secondary_link`).
- **Imagem:** envie como `multipart/form-data` no campo `image` (`jpg/jpeg/png/webp`, máx. 4 MB). O arquivo é salvo no disco `public` em `banners/{company_id}/…` e a resposta expõe `image` (caminho) e `image_url` (URL pública). Ao substituir/excluir o banner, o arquivo antigo é removido.
- Requer `php artisan storage:link` para servir as imagens em `/storage/...`.
- Endpoint público correspondente: `GET /api/v1/banners` (apenas ativos).

### Certificados (modelos) (`certificates`)
CRUD dos templates por curso (`name`, `title`, `course_id`, `grade`, `version`, `time`).
- **Visual:** `body` (texto com variáveis `{aluno}`, `{curso}`, `{data}`, `{carga_horaria}`, `{codigo}`), `signatory_name`, `signatory_title` e os uploads `background` (fundo) e `signature` (assinatura).
- **Uploads:** envie como `multipart/form-data` (`jpg/jpeg/png/webp`, máx. 4 MB). Os arquivos são salvos no disco `public` em `certificates/{company_id}/…` e a resposta expõe `background`/`signature` (caminho) e `background_url`/`signature_url` (URL pública). Ao substituir/excluir, o arquivo antigo é removido.
- **Versões:** cada linha é uma versão do certificado do curso (a mais recente é a **atual**). Para criar uma nova versão clonando a atual, envie `copy_from` (id do template de origem): as imagens são copiadas para novos arquivos (não compartilhados), e uploads enviados no mesmo request têm precedência.
- A emissão usa o template **mais recente** do curso; a resposta do aluno (`me/certificates`) já inclui os campos visuais.

### Notificações (`notifications`)
`GET /notifications`, `GET /notifications/{notification}`.

---

## Super admin

O super admin é o usuário com a flag `users.is_super_admin = true` (mantém `user_type = admin`, então herda todo o acesso administrativo normal). A flag **não** é mass-assignable: só é concedida por seeder ou pelo bootstrap de empresa. Ela é exposta em leitura (`/auth/me`, resources de usuário).

> **UI do super admin:** para reduzir ruído, o menu lateral do super admin mostra apenas **Empresas**, **Menus** e **Conta** (Meu perfil, Configurações) — a identidade visual por empresa fica dentro de Empresas. As páginas de empresa (`/admin/cursos`, `/admin/turmas`, `/admin/videos`, `/admin/usuarios` etc.) são bloqueadas por rota no frontend e redirecionam para Empresas. Isso é uma limitação de **interface**: no backend o super admin continua sendo admin.

Recursos restritos a super admin (middleware `super.admin` + policies; um admin comum recebe **403**):

- **Menus**: `GET/POST /admin/menus`, `GET/PUT/DELETE /admin/menus/{menu}` — config global de menus da plataforma.
- **Tema (identidade visual, por empresa)**: `GET/PUT /admin/companies/{company}/settings` — cor primária e logo **de uma empresa específica** (editáveis na página Empresas). Cada empresa tem sua própria cor/logo.
- **Empresas**:
  - `GET /admin/companies` — lista/pagina empresas (busca por `name`, filtro `status`).
  - `POST /admin/companies` — cria a empresa e faz o bootstrap: tipos de usuário padrão (`admin`, `instructor`, `student`) + o primeiro admin. Campos: `name`, `status?`, `admin_name`, `admin_username`, `admin_email`, `admin_password`.
  - `GET /admin/companies/{company}`, `PUT /admin/companies/{company}` (`name`, `status`).

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
