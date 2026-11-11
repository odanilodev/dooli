<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

/**
 * Versão da vitrine (visitante e aluno) do produto. Não expõe as turmas internas
 * (nomes, cursos aninhados, company_id) e lista em included_courses só o que o
 * comprador realmente recebe.
 */
class PublicStoreProductResource extends StoreProductResource
{
    protected bool $activeOnly = true;

    public function toArray(Request $request): array
    {
        $data = parent::toArray($request);

        unset($data['teams']);

        return $data;
    }
}
