<?php

namespace App\Support;

use App\Models\Seller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Regra única de quem enxerga quais vendas (pedidos, pagamentos e assinaturas).
 *
 * Admins e super admins veem todas as vendas da empresa. Qualquer outro usuário
 * vê somente as vendas atribuídas ao vendedor vinculado a ele (sellers.user_id);
 * sem esse vínculo, não vê venda nenhuma.
 */
class SalesVisibility
{
    /** Módulos do datalog que descrevem vendas e por isso são exclusivos de quem vê todas. */
    public const AUDIT_MODULES = ['order', 'payment', 'subscription', 'seller'];

    public static function seesAll(User $user): bool
    {
        return $user->isAdmin() || $user->isSuperAdmin();
    }

    /** Se o usuário pode ver uma venda atribuída ao vendedor informado. */
    public static function allows(User $user, int|string|null $sellerId): bool
    {
        if (self::seesAll($user)) {
            return true;
        }

        $own = self::sellerIdFor($user);

        return $own !== null && $sellerId !== null && (int) $sellerId === $own;
    }

    /**
     * Restringe uma consulta cuja tabela tem a coluna do vendedor.
     */
    public static function apply(Builder $query, User $user, string $column = 'seller_id'): Builder
    {
        if (self::seesAll($user)) {
            return $query;
        }

        $sellerId = self::sellerIdFor($user);

        // where(coluna, null) viraria "is null" e mostraria as vendas sem vendedor.
        if ($sellerId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where($query->qualifyColumn($column), $sellerId);
    }

    /**
     * Restringe uma consulta que chega à venda por um relacionamento
     * (ex.: pagamento -> "order", estorno -> "payment.order").
     */
    public static function applyThrough(Builder $query, User $user, string $relation): Builder
    {
        if (self::seesAll($user)) {
            return $query;
        }

        return $query->whereHas($relation, fn (Builder $sale) => self::apply($sale, $user));
    }

    private static function sellerIdFor(User $user): ?int
    {
        $id = Seller::query()
            ->where('company_id', $user->company_id)
            ->where('user_id', $user->id)
            ->value('id');

        return $id === null ? null : (int) $id;
    }
}
