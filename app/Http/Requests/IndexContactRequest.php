<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:255'],
            'gender' => ['nullable', 'integer', 'in:0,1,2,3'],
            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],
            'date' => ['nullable', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'keyword.string' => '検索キーワードは文字列で入力してください',
            'keyword.max' => '検索キーワードは255文字以内で入力してください',
            'gender.integer' => '性別の値が不正です',
            'gender.in' => '性別の値が不正です',
            'category_id.integer' => '選択されたカテゴリーが存在しません',
            'category_id.exists' => '選択されたカテゴリーが存在しません',
            'date.date' => '日付は正しい日付形式で入力してください',
        ];
    }
}
