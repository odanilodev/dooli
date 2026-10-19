<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\ScopedExists;
use Illuminate\Foundation\Http\FormRequest;

class TransferIssuedCertificateRequest extends FormRequest
{
    use ScopedExists;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'target_certificate_id' => ['required', 'integer', $this->companyExists('certificates')],
        ];
    }
}
