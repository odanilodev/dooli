# Vendedores, links e comissões

## Conceitos

- **Usuário** é uma identidade de acesso (`users`), com login, perfil e permissões.
- **Vendedor** é um parceiro comercial (`sellers`), com código, percentual e histórico de comissões.
- O vendedor **não tem login** nesta versão. O vínculo opcional `sellers.user_id` apenas identifica a pessoa; não concede acesso ao painel.
- Cada vendedor pertence a uma empresa e possui um código único dentro dela.

## Kit de links

O vendedor recebe do admin um link secreto:

```text
https://loja.exemplo.com/vendedor/{share_token}
```

Sem domínio próprio:

```text
https://app.exemplo.com/e/{companySlug}/vendedor/{share_token}
```

O kit é público, somente leitura e mostra:

- nome e código do vendedor;
- link geral da loja com `?ref`;
- produtos ativos e já liberados;
- botão para copiar e compartilhar cada link.

O kit não mostra comissões, vendas, clientes ou dados de outros vendedores. O `share_token` é aleatório e pode ser rotacionado no admin; o link anterior deixa de funcionar. Vendedor inativo tem o kit indisponível.

## Links de produto

Com domínio próprio:

```text
https://loja.exemplo.com/produto/curso-php?ref=joao123
```

Sem domínio próprio:

```text
https://app.exemplo.com/e/empresa-x/loja/curso-php?ref=joao123
```

## Atribuição

1. O frontend captura `?ref` em qualquer página pública e guarda por **30 dias** no `localStorage`, por empresa.
2. O código também é enviado no cadastro, no pedido e na assinatura.
3. O backend valida se o vendedor existe, está ativo e pertence à empresa.
4. O último código válido vence. Se não houver código explícito válido, usa a atribuição ainda válida do usuário.
5. A atribuição é gravada em `users.referred_seller_id` e `users.referral_expires_at`.
6. Pedidos e assinaturas guardam `seller_id`, `referral_code` e `commission_percent` congelados.

Código inválido, inativo ou de outra empresa é ignorado. A atribuição anônima depende do navegador; se o usuário trocar de dispositivo antes de se cadastrar, o código pode ser perdido.

## Comissão

- É gerada somente quando o pagamento é confirmado.
- É uma linha por item do pedido (`seller_commissions`), permitindo relatório por produto.
- A base é o valor do item **após desconto**, rateado proporcionalmente entre os itens.
- O percentual é congelado no pedido/assinatura. Alterar a configuração do vendedor não muda vendas antigas nem assinaturas em andamento.
- Assinaturas geram comissão em todas as renovações, usando o percentual congelado na assinatura.
- A confirmação é idempotente: webhook duplicado não gera comissão duplicada.
- Estorno parcial reduz `refunded_amount` proporcionalmente.
- Estorno integral marca a comissão como `reversed`.
- Se o estorno ocorrer após a comissão já ter sido paga, o admin deve descontar o valor no próximo repasse; não há transferência bancária automática.

Status:

| Status | Significado |
| --- | --- |
| `approved` | A pagar |
| `paid` | Paga manualmente |
| `reversed` | Estornada |

## Endpoints administrativos

Prefixo `/api/v1/admin`, somente admin da empresa:

- `GET /sellers` — lista com métricas (filtros: `search`, `status`)
- `POST /sellers` — cria vendedor
- `GET /sellers/{seller}` — detalhe com métricas, produtos e pedidos recentes
- `PUT /sellers/{seller}` — atualiza vendedor
- `DELETE /sellers/{seller}` — exclui somente sem histórico
- `POST /sellers/{seller}/rotate-kit` — gera novo token do kit
- `GET /commissions` — extrato (filtros: `search`, `status`, `seller_id`, `product_id`, `order_id`, `date_from`, `date_to`)
- `GET /commissions/summary` — totais
- `GET /commissions/export` — XLSX
- `POST /commissions/{commission}/pay` — marca uma comissão como paga
- `POST /commissions/bulk-pay` — marca várias comissões como pagas

Campos de criação/edição do vendedor:

```json
{
  "name": "João Vendedor",
  "email": "joao@example.com",
  "phone": "(11) 99999-9999",
  "user_id": null,
  "code": "joao123",
  "commission_percent": 10,
  "status": "active",
  "notes": "Parceiro interno"
}
```

`code` é opcional; quando vazio, é gerado a partir do nome. O código é normalizado para minúsculas e aceita letras, números, `_` e `-`.

## Endpoints públicos

- `GET /api/v1/public/companies/{companySlug}/seller-kits/{token}`
- `GET /api/v1/public/tenant/seller-kits/{token}`

Ambos usam `throttle:public` e retornam 404 para kit inexistente, inativo ou de outra empresa.

## Tabelas

- `sellers`: vendedor, código, token do kit, percentual, status e vínculo opcional com usuário.
- `seller_commissions`: extrato financeiro por item, com snapshot de base, percentual, valor, estorno e pagamento.
- `users.referred_seller_id` / `users.referral_expires_at`: última atribuição válida.
- `orders.seller_id` / `orders.referral_code` / `orders.commission_percent`: atribuição da venda.
- `subscriptions.seller_id` / `subscriptions.referral_code` / `subscriptions.commission_percent`: atribuição recorrente.

## Limitações atuais

- Sem portal/login do vendedor.
- Sem rastreamento de cliques no servidor; a captura anônima é via `localStorage`.
- Sem backfill de pedidos históricos.
- Sem taxas de gateway na base de comissão.
- Pagamento ao vendedor é manual.
