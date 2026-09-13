<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\IndexContactRequest as WebIndexContactRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class IndexContactRequest extends WebIndexContactRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'gender' => ['nullable', 'integer', 'in:1,2,3'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
    }

    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'page.integer' => 'ページ番号は整数で指定してください',
            'page.min' => 'ページ番号は1以上で指定してください',
            'per_page.integer' => '表示件数は整数で指定してください',
            'per_page.min' => '表示件数は1以上で指定してください',
            'per_page.max' => '表示件数は100以下で指定してください',
        ]);
    }

    protected function failedValidation(Validator $validator)
    {
        // ブラウザから直接開いた場合も、エラーをJSONで返す
        throw new HttpResponseException(response()->json([
            'message' => '入力内容に誤りがあります。',
            'errors' => $validator->errors(),
        ], 422));
    }
}
