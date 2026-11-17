<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Limpeza dos dados de teste:
     *
     *  - Pedidos: apaga todos os que NÃO estão pagos (pending, canceled, refunded),
     *    com seus itens, pagamentos (e payment_events / refunds) e acessos concedidos
     *    por eles. Pedidos pagos são mantidos.
     *  - Pagamentos pendentes ou reembolsados dos pedidos pagos que sobraram também
     *    são apagados; os pagos ficam.
     *  - Certificados emitidos (certificate_users): apaga TODOS, de todas as empresas,
     *    mesmo com aluno vinculado. Os modelos de certificado (certificates) ficam.
     *
     * order_events e seller_commissions dos pedidos apagados saem por cascade; as
     * comissões de pedidos mantidos só perdem a referência ao pagamento apagado.
     * Assinaturas e cupons não são alterados. É irreversível: faça backup antes.
     */
    public function up(): void
    {
        DB::transaction(function () {
            $orderIds = fn () => DB::table('orders')->where('status', '!=', 'paid')->select('id');

            $paymentIds = fn () => DB::table('payments')
                ->whereIn('order_id', $orderIds())
                ->orWhereIn('status', ['pending', 'refunded'])
                ->select('id');

            DB::table('payment_events')->whereIn('payment_id', $paymentIds())->delete();
            DB::table('refunds')->whereIn('payment_id', $paymentIds())->delete();

            DB::table('payments')
                ->whereIn('order_id', $orderIds())
                ->orWhereIn('status', ['pending', 'refunded'])
                ->delete();

            DB::table('access_grants')
                ->where('source_type', 'order')
                ->whereIn('source_id', $orderIds())
                ->delete();

            DB::table('order_items')->whereIn('order_id', $orderIds())->delete();
            DB::table('orders')->where('status', '!=', 'paid')->delete();

            DB::table('certificate_users')->delete();
        });
    }

    public function down(): void
    {
        // Irreversível: os registros apagados não são recriados.
    }
};
