<?php

namespace App\Services;

use App\Models\Payment;
use Illuminate\Support\Arr;

class PaymentDetailService
{
    /**
     * Return only safe, normalized payment information extracted from gateway
     * metadata. Raw payloads are never exposed by the API.
     *
     * @return array<string, mixed>
     */
    public function forPayment(Payment $payment): array
    {
        $sources = $this->sources($payment);

        $lastFour = $this->first($sources, [
            'card.last_four_digits',
            'card.last4',
            'card.last_four',
        ]);

        $methodId = $this->first($sources, [
            'payment_method_id',
            'payment_method.id',
        ]);

        $details = [
            'payment_method_id' => $methodId,
            'payment_type_id' => $this->first($sources, ['payment_type_id']),
            'gateway_status' => $this->first($sources, ['status']),
            'status_detail' => $this->first($sources, ['status_detail']),
            'installments' => $this->integer($this->first($sources, [
                'installments',
                'transaction_details.installments',
            ])),
            'installment_amount' => $this->decimal($this->first($sources, [
                'transaction_details.installment_amount',
            ])),
            'card_brand' => $this->first($sources, [
                'card.card_brand',
                'card.brand',
            ]) ?? ($lastFour ? $methodId : null),
            'card_last_four' => $lastFour,
            'authorization_code' => $this->first($sources, ['authorization_code']),
            'receipt_url' => $this->first($sources, [
                'transaction_details.external_resource_url',
                'receipt_url',
            ]),
        ];

        return array_filter($details, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return array<string, string|null>
     */
    public function pix(Payment $payment): array
    {
        if ($payment->method !== 'pix') {
            return [];
        }

        $sources = $this->sources($payment);

        $pix = [
            'qr_code' => $this->first($sources, [
                'point_of_interaction.transaction_data.qr_code',
                'transaction_data.qr_code',
            ]),
            'qr_code_base64' => $this->first($sources, [
                'point_of_interaction.transaction_data.qr_code_base64',
                'transaction_data.qr_code_base64',
            ]),
            'ticket_url' => $this->first($sources, [
                'point_of_interaction.transaction_data.ticket_url',
                'transaction_data.ticket_url',
            ]),
            'expires_at' => $this->first($sources, [
                'date_of_expiration',
                'point_of_interaction.transaction_data.date_of_expiration',
            ]),
        ];

        return array_filter($pix, fn ($value) => $value !== null && $value !== '');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sources(Payment $payment): array
    {
        $sources = [];

        if ($payment->relationLoaded('events')) {
            foreach ($payment->events->sortByDesc('created_at') as $event) {
                if (is_array($event->metadata)) {
                    $sources[] = $event->metadata;
                }
            }
        }

        $metadata = is_array($payment->metadata) ? $payment->metadata : [];

        if (isset($metadata['gateway']) && is_array($metadata['gateway'])) {
            $sources[] = $metadata['gateway'];
        }

        $sources[] = $metadata;

        return $sources;
    }

    /**
     * @param  list<array<string, mixed>>  $sources
     * @param  list<string>  $paths
     */
    private function first(array $sources, array $paths): mixed
    {
        foreach ($paths as $path) {
            foreach ($sources as $source) {
                $value = Arr::get($source, $path);

                if ($value !== null && $value !== '') {
                    return $value;
                }
            }
        }

        return null;
    }

    private function integer(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    private function decimal(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }
}
