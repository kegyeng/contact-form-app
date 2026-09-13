<?php

namespace Tests\Unit;

use App\Http\Requests\StoreContactRequest;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreContactRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validInput(): array
    {
        $category = Category::create([
            'content' => '商品のお届けについて',
        ]);

        $tag = Tag::create(['name' => '質問']);

        return [
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'address' => '東京都渋谷区千駄ヶ谷1-2-3',
            'building' => null,
            'category_id' => $category->id,
            'detail' => '配送について教えてください。',
            'tag_ids' => [$tag->id],
        ];
    }

    private function validator(array $input)
    {
        $request = new StoreContactRequest;

        return Validator::make(
            $input,
            $request->rules(),
            $request->messages(),
            $request->attributes()
        );
    }

    public function test_valid_input_is_accepted(): void
    {
        $validator = $this->validator($this->validInput());

        $this->assertTrue(
            $validator->passes(),
            $validator->errors()->toJson()
        );
    }

    public function test_required_fields_are_rejected_when_missing(): void
    {
        $validator = $this->validator([]);
        $errors = $validator->errors();

        $expected = [
            'first_name' => '姓を入力してください',
            'last_name' => '名を入力してください',
            'gender' => '性別を選択してください',
            'email' => 'メールアドレスを入力してください',
            'tel' => '電話番号を入力してください',
            'address' => '住所を入力してください',
            'category_id' => 'お問い合わせの種類を選択してください',
            'detail' => 'お問い合わせ内容を入力してください',
        ];

        foreach ($expected as $field => $message) {
            $this->assertSame($message, $errors->first($field));
        }
    }

    public function test_phone_accepts_ten_and_eleven_digits(): void
    {
        $input = $this->validInput();

        foreach (['0312345678', '09012345678'] as $tel) {
            $input['tel'] = $tel;

            $this->assertTrue(
                $this->validator($input)->passes(),
                '有効な電話番号が拒否されました: '.$tel
            );
        }
    }

    public function test_phone_rejects_invalid_formats(): void
    {
        $input = $this->validInput();

        foreach ([
            '123456789',
            '123456789012',
            '090-1234-5678',
            'abcdefghijk',
            '０９０１２３４５６７８',
        ] as $tel) {
            $input['tel'] = $tel;

            $this->assertSame(
                '電話番号はハイフンなしの10〜11桁で入力してください',
                $this->validator($input)->errors()->first('tel'),
                '電話番号: '.$tel
            );
        }
    }

    public function test_invalid_email_is_rejected(): void
    {
        $input = $this->validInput();
        $input['email'] = 'invalid-email';

        $this->assertSame(
            'メールアドレスはメール形式で入力してください',
            $this->validator($input)->errors()->first('email')
        );
    }

    public function test_gender_accepts_only_one_two_or_three(): void
    {
        $input = $this->validInput();

        foreach ([1, 2, 3] as $gender) {
            $input['gender'] = $gender;

            $this->assertTrue($this->validator($input)->passes());
        }

        foreach ([0, 9, 'abc', 1.5] as $gender) {
            $input['gender'] = $gender;

            $this->assertSame(
                '性別の値が不正です',
                $this->validator($input)->errors()->first('gender')
            );
        }
    }

    public function test_detail_accepts_120_characters_but_rejects_121(): void
    {
        $input = $this->validInput();
        $input['detail'] = str_repeat('あ', 120);

        $this->assertTrue($this->validator($input)->passes());

        $input['detail'] = str_repeat('あ', 121);

        $this->assertSame(
            'お問い合わせ内容は120文字以内で入力してください',
            $this->validator($input)->errors()->first('detail')
        );
    }

    public function test_name_address_and_building_length_limits(): void
    {
        $input = $this->validInput();

        foreach (['first_name', 'last_name', 'address', 'building'] as $field) {
            $candidate = $input;
            $candidate[$field] = str_repeat('あ', 255);

            $this->assertTrue(
                $this->validator($candidate)->passes(),
                $field.'の255文字が拒否されました'
            );

            $candidate[$field] = str_repeat('あ', 256);

            $this->assertTrue(
                $this->validator($candidate)->errors()->has($field),
                $field.'の256文字が拒否されませんでした'
            );
        }
    }

    public function test_nonexistent_category_is_rejected(): void
    {
        $input = $this->validInput();
        $input['category_id'] = 999999;

        $this->assertSame(
            '選択されたカテゴリーが存在しません',
            $this->validator($input)->errors()->first('category_id')
        );
    }

    public function test_building_and_tags_can_be_omitted(): void
    {
        $input = $this->validInput();
        unset($input['building'], $input['tag_ids']);

        $this->assertTrue($this->validator($input)->passes());
    }

    public function test_tags_must_be_an_array(): void
    {
        $input = $this->validInput();
        $input['tag_ids'] = '質問';

        $this->assertSame(
            'タグは配列で指定してください',
            $this->validator($input)->errors()->first('tag_ids')
        );
    }

    public function test_nonexistent_or_non_integer_tag_is_rejected(): void
    {
        $input = $this->validInput();

        foreach ([999999, 'abc'] as $tagId) {
            $input['tag_ids'] = [$tagId];

            $this->assertSame(
                '選択されたタグが存在しません',
                $this->validator($input)->errors()->first('tag_ids.0')
            );
        }
    }
}
