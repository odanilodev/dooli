<?php

namespace App\Http\Requests\Admin;

use App\Enums\GeneralStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => ['nullable', 'file', 'mimes:pdf,doc,docx,ppt,pptx,xls,xlsx,txt,csv,zip', 'max:20480'],
            'path' => ['nullable', 'string', 'max:255', 'required_without_all:file,url'],
            'url' => ['nullable', 'url', 'max:2048', 'required_without_all:file,path'],
            'image' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
        ];
    }
}
