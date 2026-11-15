<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => 'required|uuid',
            'name' => 'sometimes|string|max:255',
            'code' => 'sometimes|string|max:30',
            'type' => 'sometimes|string|in:part,supply',
            'measure_unit' => 'sometimes|string|in:un,kg,g,l,ml,m,cm,cx,pair,pc',
            'minimum_quantity' => 'sometimes|numeric|min:0',
            'description' => 'nullable|string',
            'unit_price' => 'nullable|numeric|min:0',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['id' => $this->route('id')]);
    }
}
