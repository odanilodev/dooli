<?php

namespace App\Http\Requests\Admin\Concerns;

trait ValidatesCertificateLayout
{
    /**
     * When the certificate is saved as multipart/form-data, the layout arrives
     * as a JSON string and must be decoded before validation.
     */
    protected function prepareLayoutForValidation(): void
    {
        $layout = $this->input('layout');

        if (! is_string($layout)) {
            return;
        }

        $decoded = json_decode($layout, true);

        $this->merge(['layout' => is_array($decoded) ? $decoded : null]);
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function layoutRules(): array
    {
        return [
            'layout' => ['nullable', 'array'],
            'layout.*' => ['array'],
            'layout.*.x' => ['numeric', 'between:0,100'],
            'layout.*.y' => ['numeric', 'between:0,100'],
            'layout.*.width' => ['nullable', 'numeric', 'between:5,100'],
            'layout.*.color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'layout.*.font' => ['nullable', 'string', 'max:80'],
            'layout.*.size' => ['nullable', 'numeric', 'between:4,160'],
            'layout.*.align' => ['nullable', 'in:left,center,right'],
            'layout.*.bold' => ['nullable', 'boolean'],
            'layout.*.italic' => ['nullable', 'boolean'],
        ];
    }
}
