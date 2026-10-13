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
            'image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'status' => ['sometimes', Rule::in(GeneralStatus::values())],
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => 'O arquivo é muito grande. O tamanho máximo é de 20 MB.',
            'file.uploaded' => 'Não foi possível enviar o arquivo. Verifique se ele não ultrapassa 20 MB e tente novamente.',
            'file.file' => 'Não foi possível enviar o arquivo. Verifique se ele não ultrapassa 20 MB e tente novamente.',
            'file.mimes' => 'Formato de arquivo não permitido. Envie PDF, Word, PowerPoint, Excel, TXT, CSV ou ZIP.',
            'image_file.max' => 'A imagem é muito grande. O tamanho máximo é de 4 MB.',
        ];
    }
}
