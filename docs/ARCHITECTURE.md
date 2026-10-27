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
| Certificados | `certificates` = template por curso (layout + `qr_enabled`/`qr_placement` + `modules_placement`/`modules_layout`/fundo próprio da grade); `certificate_users` = emissão por user+team com snapshots `lessons_count` e `modules` (unique parcial via `active_flag`: 1 válido por user+team+template; versões anteriores ficam no histórico como `replaced`). Template com certificado emitido é imutável; válidos podem ser transferidos para outra versão do mesmo curso. A página pública usa o UUID, sem tenant/autenticação |
| Pagamentos | `PaymentGatewayContract` + `GatewayManager` (Strategy/Factory); adapter real MercadoPago + Manual |
| Vídeo | `VideoPlatformContract` + `VideoPlatformManager` resolvido por `videos.player`; adapter real: VdoCipher (playback OTP + upload/import via API); stubs para os demais |
| Webhooks | Verificação de assinatura, idempotência por `external_id`, processamento via Job |
| Progresso | `timelines` + `actions.code` (unique de banco `user+course+lesson+action+team` garante idempotência) |
| Conclusão | `course_completions` = **marco mais recente** da conquista (user+team+course, `required_lessons`, `lesson_ids`, `completed_at`), independente do certificado |
| Curso alterado vs. conclusão | Novas aulas mantêm o certificado atual válido; ao concluir todas, o marco avança e o certificado é substituído por um novo com a grade atual |
| Loja | Banners usam `placement` (`home`/`store`); textos da vitrine ficam em `company_settings` na chave JSON `storefront` e são expostos por `/admin/storefront`, `/storefront` e pelo payload público da empresa |
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
`teams` (ativo) + `team_users` (ativo, não expirado) + `team_courses` (ativo) + curso/aulas ativos. Centralizado em `StudentCourseService::assertAccess`. Turma inativa bloqueia o acesso (reativar restaura).

### Compra → acesso
`Order` → `Payment` (gateway) → confirmação (webhook/manual) → `EnrollmentService::grantAccessForOrder` → cria `team_courses` + `team_users` a partir de `store_product_teams` (turmas inativas são ignoradas).

### Conclusão → certificado
`ProgressService::completeLesson` grava `timelines` (unique de banco) + snapshot e, quando o aluno atinge o total atual de aulas, registra ou avança a **conclusão** em `course_completions`. Só então chama `CertificateService::issueIfEligible` (fora da transação). Se ainda não existe certificado, ele é emitido com o snapshot da conclusão. Se já existe e o curso ganhou aulas, o certificado atual permanece válido até o aluno concluir todas; nesse momento um novo certificado é emitido com o total e a grade atuais, o antigo é invalidado como **Substituído** e ambos os snapshots ficam no histórico. Módulos/novas aulas só entram no novo documento depois de concluídos.

O template decide a apresentação da grade (`modules_placement`: `none`, `front`, `extra` ou `back`). Em `front`, a grade usa `layout.modules`; em `extra`/`back`, o documento tem uma segunda página A4 desenhada por `modules_layout` (título + lista) e fundo próprio opcional (`modules_background`); sem esse fundo, a página fica branca. A lista aceita formato (numerada, com marcadores, sem marcador ou em linha), 1–4 colunas e separador configurável no modo em linha. A configuração é do template, não do certificado emitido; o snapshot de módulos continua sendo congelado por emissão.

### Versões do template
Editar um template com qualquer certificado emitido retorna `422 business_rule`; o fluxo correto é criar uma nova versão, ajustar o modelo enquanto ele ainda não tem alunos e transferir os certificados válidos. `CertificateService::transferIssued` atualiza apenas `certificate_users.certificate_id` dos válidos, preservando código, data, `lessons_count` e `modules`; revogados/substituídos permanecem no modelo antigo. Alunos que já tenham válido no destino são ignorados. A operação é transacional e auditada.

### Alteração de curso vs. conclusão
Adicionar ou publicar aulas **não** revoga o certificado atual enquanto o aluno não as concluir; ele continua válido com a grade antiga. Quando o aluno conclui todas as aulas atuais, `course_completions` avança para o novo total e `CertificateService::issueIfEligible` substitui o certificado: o antigo recebe `invalidated_at`/motivo **Substituído automaticamente por uma nova conclusão do curso** e o novo passa a ser o válido. Excluir aulas não reemite nem invalida por si só. O status `completed` vem de `course_completions` (não do certificado), então uma revogação manual do documento mantém o curso como concluído e não é reemitida automaticamente. `course_completions` guarda o marco mais recente (`required_lessons`, `lesson_ids`); `certificate_users.modules` guarda a grade de cada emissão. Comandos: `completions:backfill` (recupera conclusões de certificados válidos e invalidados automaticamente), `certificates:backfill-modules` (preenche a grade de certificados antigos usando as aulas concluídas) e `certificates:restore-recalculated` (revalida certificados invalidados pelo recálculo antigo).

### Página pública do certificado
`GET /api/v1/public/certificates/{code}` não usa autenticação nem `X-Company`: o código público curto localiza a emissão e o tenant, e UUIDs antigos continuam aceitos. Certificados substituídos automaticamente retornam `redirect_url` para o válido atual; a página pública redireciona. O `CertificatePublicResource` expõe somente apresentação e nome do titular. O frontend tem uma entrada Vite separada (`verificar.html`) com `noindex` no HTML, sem layout autenticado. `CertificateShare::url()` monta `${FRONTEND_URL}/v/{codigo}`; esse valor vira o `share_url` do aluno e, quando `certificates.qr_enabled` está ativo, o QR impresso no certificado. `qr_placement` define frente, página da grade ou ambas; os campos `qr` de `layout` e `modules_layout` guardam posição/tamanho, e o QR é gerado no cliente com `qrcode`. Em desenvolvimento/preview o Vite reescreve `/v/*` para `verificar.html`; em produção o host precisa da regra equivalente.

## Estrutura

```
app/
├── Concerns/            BelongsToCompany
├── DTO/
├── Enums/
├── Jobs/
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

## Tenancy por dominio

- **Dominio** (`companies.domain`, opcional e unico) e a chave principal do tenant. `Company::normalizeDomain()` remove protocolo, `www.`, porta, caminho e normaliza para minusculo.
- **Resolucao publica:** o frontend envia `X-Company-Domain`; `ResolveCompanyByDomain` (alias `company.domain`) define `CompanyContext` e `public_company`. `X-Forwarded-Host`/`Host` sao fallback. Dominios em `PLATFORM_DOMAINS` nunca viram tenant.
- **Rotas:** `/api/v1/public/tenant/*` para loja/login/cadastro/reset por dominio. `/api/v1/public/companies/{slug}/*` continua como fallback local/legado. Rotas autenticadas ignoram o dominio e usam a empresa do usuario.
- **URLs:** `Company::frontendUrl()`/`publicStoreUrl()`/`publicOrderUrl()`/`publicSubscriptionsUrl()` preferem `https://{domain}`. Sem dominio, usam `FRONTEND_URL/#/e/{slug}/...`.
- **Frontend:** `createWebHistory` (sem hash), loja publica em `/`, login em `/entrar`, painel em `/inicio`; `useTenant()` mapeia rotas para o modo dominio ou legado por slug. `PLATFORM_DOMAINS`/`VITE_PLATFORM_DOMAINS` separam o painel central. O servidor precisa de fallback SPA para todos os hosts.
- **Links externos:** reset de senha, retorno do MercadoPago, assinaturas e certificados usam o dominio da empresa quando configurado.
- **Seguranca:** dominio identifica o tenant, mas nao autoriza; autorizacao continua por `company_id` do usuario/token.
