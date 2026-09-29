# Relatório de Arquitetura

## Visão geral

API REST versionada (`/api/v1`) em **Laravel 13 + Sanctum**, com arquitetura em camadas:

```
Route → Middleware → FormRequest → Controller → Service → Repository → Model → DB
                                   Controller ← Service ← Resource → JSON
```

- **Controllers** finos: validam via `FormRequest`, autorizam via Policy e delegam ao Service.
- **Services**: regras de negócio e orquestração (transações, idempotência, eventos).
- **Repositories**: persistência e consultas específicas (com filtros/paginação padronizados).
- **DTOs**: transporte tipado em operações relevantes.
- **Resources**: formato explícito de resposta (nunca `SELECT *`).
- **Policies**: autorização por empresa + perfil.

> Hierarquias de **conteúdo**, **acesso** (turmas) e **comercial** (produtos) estão detalhadas em [`DOMAIN.md`](DOMAIN.md).

## Decisões

| Tema | Decisão |
| ---- | ------- |
| Auth | Sanctum (Bearer token), login por `email` **ou** `username` |
| Multi-tenancy | `CompanyContext` (singleton) + trait `BelongsToCompany` (global scope + auto-fill de `company_id`) |
| Tenant público/webhook | Header `X-Company` (id) com middleware `company.required` |
| Perfis | `user_types.code` (`admin`/`instructor`/`student`) via middleware `role:` e Policies |
| Modelo de aulas | Tabela `lessons` → model **`Lesson`** (rota `/lessons`); `Class` é reservado no PHP |
| Certificados | `certificates` = template por curso; `certificate_users` = emissão por user+team com snapshot `lessons_count` |
| Pagamentos | `PaymentGatewayContract` + `GatewayManager` (Strategy/Factory); adapter real MercadoPago + Manual |
| Vídeo | `VideoPlatformContract` + `VideoPlatformManager` resolvido por `videos.player`; adapters reais: Panda (playback) e VdoCipher (playback OTP + upload/import via API); stubs para os demais |
| Webhooks | Verificação de assinatura, idempotência por `external_id`, processamento via Job |
| Progresso | `timelines` + `actions.code` (idempotente por unique de negócio no Service) |
| Regra de curso alterado | `CourseCompletionService` acionado pelo evento `CourseContentChanged` |
| Respostas | Envelope `success/data/meta/errors` + exceções centralizadas em `bootstrap/app.php` |
| Erros | `ApiException` e filhas; handler mapeia validação/auth/404/429 |

## Nomenclatura

O padrão do projeto é **rota = tabela**: tabela plural snake_case, model singular PascalCase, rota kebab plural, FK `{singular}_id`.

| Antes | Depois |
| ----- | ------ |
| `products` (curso) | `courses` |
| `classes` | `lessons` |
| `comment_classes` | `comments` |
| `rating_classes` | `ratings` |
| `attachment_classes` | `lesson_attachments` |
| `product_modules` | `course_modules` |
| `team_products` | `team_courses` |
| `banner`/`menu`/`datalog`/`timeline` | `banners`/`menus`/`datalogs`/`timelines` |
| colunas `product_id`/`class_id` | `course_id`/`lesson_id` |

`store_products` permanece como o produto vendável da loja.

## Inconsistências encontradas e soluções

1. **`users` sem `email_verified_at`/`remember_token`** e factory incompatível → model/factory ajustados.
2. **`email`/`username` sem unique** → migration aditiva `add_unique_index_to_users_email_username`.
3. **`notifications` sem destinatário/leitura** → tabela aditiva `notification_users` (`read_at`).
4. **`actions` sem código estável** → coluna aditiva `actions.code` + seeder.
5. **`timelines` sem idempotência** → checagem no `ProgressService` + índices.
6. **`coupons` sem limite por usuário / valor mínimo / produtos** → colunas aditivas + `coupon_store_products`.
7. **`user_types` sem identificador de perfil** → coluna aditiva `code`.
8. **`payment_events` sem id externo** → coluna aditiva `external_id` + unique para idempotência.
9. **`certificate_users` sem identificador público nem invalidação** → `code` (UUID) + `invalidated_at`/`invalidation_reason`.
10. **`gateways.credentials` em coluna `json`** → coluna `text` + cast `encrypted:array` + `#[Hidden]`.
11. **Tabelas só com `created_at`** → `const UPDATED_AT = null` nos models.
12. **Pivots com `company_id` NOT NULL** → todos os `attach/sync` preenchem `company_id`.
13. **Índices compostos ausentes** → migration aditiva `add_performance_indexes`.

## Fluxos principais

### Acesso do aluno
`team_users` (ativo, não expirado) + `team_courses` (ativo) + curso/aulas ativos. Centralizado em `StudentCourseService::assertAccess`.

### Compra → acesso
`Order` → `Payment` (gateway) → confirmação (webhook/manual) → `EnrollmentService::grantAccessForOrder` → cria `team_courses` + `team_users` a partir de `store_product_teams`.

### Conclusão → certificado
`ProgressService::completeLesson` grava `timelines` (idempotente) e chama `CertificateService::issueIfEligible`, que valida total × concluídas antes de emitir.

### Alteração de curso (seção 27)
Ao criar/publicar aula, `LessonService` dispara `CourseContentChanged` → `HandleCourseContentChange` → `CourseCompletionService::recalculateCourse` invalida certificados cujo `lessons_count` < total de aulas e notifica os alunos afetados.

## Estrutura

```
app/
├── Concerns/            BelongsToCompany
├── DTO/
├── Enums/
├── Events/ Listeners/ Jobs/
├── Exceptions/
├── Http/{Controllers/Api/V1, Middleware, Requests, Resources}
├── Models/              (38 models)
├── Policies/
├── Providers/           bindings + rate limiters
├── Repositories/{Contracts}
├── Services/{Payments,Video}
└── Support/             ApiResponse, CompanyContext
routes/api.php + routes/api/v1.php
docs/ + postman/
```
