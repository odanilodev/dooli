# Relatório de Performance

## Estratégias aplicadas

### Consultas
- **Eager loading controlado** em todas as listagens e detalhes (`with([...])`) para evitar N+1.
- **Paginação obrigatória** em listagens, com `per_page` limitado a **100** (`ApiController::perPage`).
- **Global scope por empresa** garante filtros seletivos; listagens sempre filtradas.
- **Constantes/Enums** evitam strings mágicas e permitem índices consistentes.

### Índices
Migrations originais já indexam `status` e chaves estrangeiras (InnoDB). A migration aditiva `add_performance_indexes` adiciona compostos para consultas quentes:
- `timelines(user_id, course_id, lesson_id, action_id)` e `timelines(company_id, team_id, lesson_id)`
- `team_users(company_id, user_id, status)`
- `orders(company_id, user_id, status)`
- `payments(company_id, order_id, status)`
- `payment_events(payment_id, event)`
- `notifications(company_id, team_id, status)`
- `certificate_users(company_id, user_id)`
- `lessons(topic_id, status, sort_order)`
- `lessons(module_id, status, sort_order)`
- `store_products(company_id, status, release_at)`

### Processamento assíncrono
- Webhooks processados por `ProcessPaymentWebhookJob` (fila), tirando o processamento pesado do ciclo HTTP.
- `SendNotificationJob` preparado para envio de e-mail/WhatsApp fora do request.
- Worker dedicado (`queue` no `docker-compose`).

### Cache
- Adapter de vídeo (Panda) usa cache de 30 min por vídeo para evitar chamadas repetidas à API externa.

## Revisão de consultas

- **N+1**: mitigado por `with()`; relações acessadas nos Resources são sempre carregadas (`whenLoaded`).
- **SELECT \***: mitigado no nível de resposta (Resources explícitos). Para volumes maiores, recomenda-se adicionar `select()` específico nos Repositories de listagem.
- **Consultas em loop**: `CourseCompletionService` percorre certificados afetados em uma transação única; `EnrollmentService` itera itens do pedido dentro de transação.
- **Agregações**: contagem de aulas concluídas usa `pluck('lesson_id')->unique()` no Repository.

## Recomendações futuras

1. Trocar `CACHE_STORE` para `redis` e cachear catálogo público (`store/products`) e permissões.
2. Ativar `Model::preventLazyLoading()` em ambiente de desenvolvimento para detectar N+1.
3. Adicionar índices de texto completo (`FULLTEXT`) para `search` em grandes volumes.
4. Usar `select()` explícito nos Repositories de listagem para reduzir payload do banco.
5. Considerar cache de progresso por (user, course, team) invalidado ao concluir aula.
6. Monitorar queries lentas (ex.: Telescope/Pulse) em produção.
