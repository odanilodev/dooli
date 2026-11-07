<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * O checkout gravava "credit_card" para qualquer cartão. O gateway guarda o tipo real em
     * metadata.payment_type_id; corrige os pagamentos de débito já existentes.
     */
    public function up(): void
    {
        DB::table('payments')
            ->where('method', 'credit_card')
            ->whereNotNull('metadata')
            ->orderBy('id')
            ->each(function ($payment) {
                $metadata = json_decode((string) $payment->metadata, true);

                if (is_array($metadata) && ($metadata['payment_type_id'] ?? null) === 'debit_card') {
                    DB::table('payments')->where('id', $payment->id)->update(['method' => 'debit_card']);
                }
            });
    }

    public function down(): void
    {
        // Dados corrigidos; não reverte para não reintroduzir o rótulo errado.
    }
};
