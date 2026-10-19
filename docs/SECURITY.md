# Relatório de Segurança

## Controles implementados

### Autenticação e sessão
- Sanctum com tokens por dispositivo; logout revoga o token atual; `logout-all` revoga todos.
- Tokens recebem abilities (`admin` para admin/instrutor, `student` para alunos); expiração opcional via `SANCTUM_EXPIRATION`.
- Bloqueio de usuário/empresa inativos no login retorna a **mesma** resposta genérica de credenciais inválidas (anti-enumeração), com auditoria do bloqueio.
- Rate limit restritivo no login (`5/min` por login+IP) contra brute force.

### Autorização
- Policies por recurso + middleware `role:admin` nas rotas administrativas.
- Rotas do aluno validam posse/acesso no Service (`assertAccess`), não no frontend.
- Pedidos: aluno só acessa os próprios (`OrderPolicy`).
- Comentários/avaliações exigem matrícula ativa e a aula precisa pertencer ao curso; listagens mostram apenas produtos em que o aluno está matriculado.
- Notificações só podem ser marcadas como lidas pelo destinatário.
- Exclusões administrativas retornam `204`; erros de integridade viram `409` (nunca `500`).

### Multi-tenancy (anti-IDOR / cross-company)
- Global scope por `company_id` em todos os models de tenant.
- O middleware de tenant roda **antes** do route-model binding (`bootstrap/app.php`), então modelos vinculados implicitamente já vêm filtrados por empresa (cross-tenant → `404`, sem vazar existência).
- Policies rejeitam registros de outra empresa (`sameCompany`).
- Regras `Rule::exists` são restritas ao `company_id` do usuário (trait `ScopedExists`).
- Rotas públicas/webhooks exigem `X-Company`; sem header → `400`; empresa inexistente/inativa → `404`.
- Exceção: a página pública do certificado (`/public/certificates/{uuid}`) resolve a empresa pelo UUID e não aceita header de tenant; o código é não sequencial e só retorna certificados válidos.
- O tenant do webhook é derivado do **pagamento resolvido**, não do header enviado.

### Mass assignment
- Todos os models usam `#[Fillable]` explícito; nenhum `$guarded = []`.

### Exposição de dados
- `User` oculta `password`/`remember_token`.
- Listagens de comentários/avaliações usam `UserSummaryResource` (sem e-mail/telefone/endereço).
- Verificação pública de certificado usa `CertificateVerifyResource` (sem dados de contato); a página pública usa `CertificatePublicResource` (sem contato, usuário, time ou IDs internos).
- A página pública do certificado (`/v/{codigo}`) é servida com `meta robots=noindex,nofollow,noarchive,nosnippet` e a API responde `X-Robots-Tag`.
- O código público do certificado é aleatório e único, e a consulta pública usa `throttle:public`.
- `Gateway` oculta `credentials`/`settings`; resposta expõe apenas `has_credentials`.
- `PaymentEventResource` não expõe `metadata` (pode conter payload sensível do gateway).
- Vídeo: reprodução via adapter; credenciais de plataforma nunca são retornadas.

### Webhooks
- Verificação de assinatura por adapter (`verifyWebhook`) — **falha fechada**: sem segredo configurado, todo webhook é rejeitado (`401`).
- MercadoPago valida HMAC + janela de 5 minutos (anti-replay).
- Idempotência por `external_id` (unique por pagamento; `NULL` quando ausente) — evita liberar acesso duas vezes.
- Processamento assíncrono e auditável (`payment_events` + `datalogs`).

### Regras críticas no backend
- Desconto/total de pedido recalculados no servidor (nunca confia no frontend).
- Conclusão de aula e emissão de certificado validadas no backend.
- Liberação de acesso só após confirmação de pagamento.

### Auditoria
- `DatalogService` registra login/logout, CRUDs sensíveis, pagamentos, estornos, emissão/invalidação de certificados e webhooks — **sem** gravar senhas, tokens ou credenciais.

## Riscos residuais e recomendações

1. **HTTPS obrigatório em produção** — tokens trafegam no header `Authorization`.
2. **Definir `MERCADOPAGO_WEBHOOK_SECRET`** e `MANUAL_WEBHOOK_SECRET` em produção; sem segredo, todo webhook é rejeitado (falha fechada).
3. **`X-Company` é público por natureza** (id enumerável). Recomenda-se, no futuro, usar um `companies.slug`/chave pública para reduzir enumeração — mudança aditiva já prevista.
4. **CORS** deve ser restringido aos domínios do frontend em produção.
5. **Rotação de credenciais de gateway** e uso de cofre de segredos (ex.: variáveis de ambiente/secret manager) em vez de apenas cifra em banco.
6. **Upload de arquivos** (`documents`/`attachments`/`banners`) hoje armazena caminhos; ao habilitar upload real, validar tipo/tamanho e servir por URL assinada.
7. **`APP_DEBUG=false`** em produção para não vazar stack traces.

## Revisão realizada

- IDOR: coberto por scope + policies + `assertAccess`; notificações validadas por destinatário.
- Broken access control: rotas admin protegidas por `role:admin`; aluno isolado; comentários/avaliações exigem matrícula.
- Mass assignment: `#[Fillable]`.
- SQL injection: uso exclusivo de Query Builder/Eloquent com bindings.
- Enumeração de contas: forgot-password sempre `200`; login inativo com resposta genérica.
- Rate limit: por tipo de endpoint (auth/public/api/admin/webhooks).
- Vazamento de credenciais/PII: cifra + ocultação + resources reduzidos.
- Erros de integridade (unique/FK): tratados como `409` em vez de `500`.
- Idempotência de webhook: garantida por unique + `NULL` quando não há `external_id`.
