<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Apaga os pagamentos de teste: todos com status "pending" (pendente) ou
     * "refunded" (reembolsado), de todas as empresas, junto com o que depende
     * deles e bloquearia a exclusão (payment_events e refunds).
     *
     * Não toca em pedidos, itens, acessos nem pagamentos pagos ou falhos.
     * order_events e seller_commissions apenas perdem a referência ao pagamento
     * (payment_id vira null, por nullOnDelete). É irreversível: faça backup antes.
     */
    private const STATUSES = ['pending', 'refunded'];

    public function up(): void
    {
        DB::transaction(function () {
            $ids = fn () => DB::table('payments')->whereIn('status', self::STATUSES)->select('id');

            DB::table('payment_events')->whereIn('payment_id', $ids())->delete();
            DB::table('refunds')->whereIn('payment_id', $ids())->delete();
            DB::table('payments')->whereIn('status', self::STATUSES)->delete();
        });
    }

    public function down(): void
    {
        // Irreversível: os registros apagados não são recriados.
    }
};
