<?php

namespace Tests\Unit;

use App\Http\Requests\IndexContactRequest;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class IndexContactRequestTest extends TestCase
{
    use RefreshDatabase;

    private function validator(array $input)
    {
        $request = new IndexContactRequest;

        return Validator::make(
            $input,
            $request->rules(),
            $request->messages()
        );
    }

    public function test_search_conditions_can_be_omitted(): void
    {
        $this->assertTrue($this->validator([])->passes());
    }

    public function test_search_conditions_can_be_null(): void
    {
        $this->assertTrue($this->validator([
            'keyword' => null,
            'gender' => null,
            'category_id' => null,
            'date' => null,
        ])->passes());
    }

    public function test_valid_combined_conditions_are_accepted(): void
    {
        $category = Category::create([
            'content' => '商品のお届けについて',
        ]);

        $validator = $this->validator([
            'keyword' => '山田',
            'gender' => '1',
            'category_id' => (string) $category->id,
            'date' => '2026-09-12',
        ]);

        $this->assertTrue(
            $validator->passes(),
            $validator->errors()->toJson()
        );
    }

    public function test_keyword_accepts_255_characters_but_rejects_256(): void
    {
        $this->assertTrue($this->validator([
            'keyword' => str_repeat('あ', 255),
        ])->passes());

        $this->assertSame(
            '検索キーワードは255文字以内で入力してください',
            $this->validator([
                'keyword' => str_repeat('あ', 256),
            ])->errors()->first('keyword')
        );
    }

    public function test_keyword_must_be_a_string(): void
    {
        $this->assertSame(
            '検索キーワードは文字列で入力してください',
            $this->validator([
                'keyword' => ['山田'],
            ])->errors()->first('keyword')
        );
    }

    public function test_gender_accepts_all_option_and_valid_genders(): void
    {
        foreach (['0', '1', '2', '3'] as $gender) {
            $this->assertTrue(
                $this->validator(['gender' => $gender])->passes(),
                '性別の値が拒否されました: '.$gender
            );
        }
    }

    public function test_gender_rejects_invalid_values(): void
    {
        foreach (['9', '-1', 'abc', '1.5'] as $gender) {
            $this->assertSame(
                '性別の値が不正です',
                $this->validator([
                    'gender' => $gender,
                ])->errors()->first('gender')
            );
        }
    }

    public function test_category_rejects_nonexistent_or_non_integer_values(): void
    {
        foreach ([999999, 'abc', '1.5'] as $categoryId) {
            $this->assertSame(
                '選択されたカテゴリーが存在しません',
                $this->validator([
                    'category_id' => $categoryId,
                ])->errors()->first('category_id')
            );
        }
    }

    public function test_date_accepts_valid_leap_day(): void
    {
        $this->assertTrue($this->validator([
            'date' => '2024-02-29',
        ])->passes());
    }

    public function test_date_rejects_invalid_dates(): void
    {
        foreach (['not-a-date', '2026-02-30', '2026-13-01'] as $date) {
            $this->assertSame(
                '日付は正しい日付形式で入力してください',
                $this->validator([
                    'date' => $date,
                ])->errors()->first('date')
            );
        }
    }
}
