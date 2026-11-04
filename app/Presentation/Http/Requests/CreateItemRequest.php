<?php

namespace App\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CreateItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:30',
            'type' => 'required|string|in:part,supply',
            'measure_unit' => 'required|string|in:un,kg,g,l,ml,m,cm,cx,pair,pc',
            'minimum_quantity' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'unit_price' => 'nullable|numeric|min:0',
        ];
    }
}
