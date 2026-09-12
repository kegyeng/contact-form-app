<?php

namespace App\Http\Requests;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $uniqueRule = Rule::unique('tags', 'name');
        $tag = $this->route('tag');

        if ($tag instanceof Tag) {
            $uniqueRule->ignore($tag);
        }

        return [
            'name' => ['bail', 'required', 'string', 'max:50', $uniqueRule],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'タグ名を入力してください',
            'name.string' => 'タグ名は文字列で入力してください',
            'name.max' => 'タグ名は50文字以内で入力してください',
            'name.unique' => 'そのタグ名は既に使用されています',
        ];
    }
}
