<?php

namespace App\Http\Requests\Admin\Concerns;

trait ValidatesCertificateLayout
{
    /**
     * When the certificate is saved as multipart/form-data, the layouts arrive
     * as JSON strings and must be decoded before validation.
     */
    protected function prepareLayoutForValidation(): void
    {
        foreach (['layout', 'modules_layout'] as $key) {
            $layout = $this->input($key);

            if (! is_string($layout)) {
                continue;
            }

            $decoded = json_decode($layout, true);

            $this->merge([$key => is_array($decoded) ? $decoded : null]);
        }
    }

    /**
     * @return array<string, array<int, string>>
     */
    protected function layoutRules(string $key = 'layout'): array
    {
        return [
            $key => ['nullable', 'array'],
            "{$key}.*" => ['array'],
            "{$key}.*.x" => ['numeric', 'between:0,100'],
            "{$key}.*.y" => ['numeric', 'between:0,100'],
            "{$key}.*.width" => ['nullable', 'numeric', 'between:5,100'],
            "{$key}.*.color" => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            "{$key}.*.font" => ['nullable', 'string', 'max:80'],
            "{$key}.*.size" => ['nullable', 'numeric', 'between:4,160'],
            "{$key}.*.align" => ['nullable', 'in:left,center,right'],
            "{$key}.*.bold" => ['nullable', 'boolean'],
            "{$key}.*.italic" => ['nullable', 'boolean'],
            "{$key}.*.format" => ['nullable', 'in:numbered,bulleted,plain,inline'],
            "{$key}.*.columns" => ['nullable', 'integer', 'between:1,4'],
            "{$key}.*.separator" => ['nullable', 'in:bullet,comma,pipe,dash'],
        ];
    }
}
