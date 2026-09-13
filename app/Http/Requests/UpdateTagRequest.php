<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateTagRequest extends StoreTagRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:50',
                Rule::unique('tags', 'name')
                    ->ignore($this->route('tag')),
            ],
        ];
    }
}
