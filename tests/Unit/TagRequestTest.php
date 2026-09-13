<?php

namespace Tests\Unit;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TagRequestTest extends TestCase
{
    use RefreshDatabase;

    private function storeValidator(array $input)
    {
        $request = new StoreTagRequest;

        return Validator::make(
            $input,
            $request->rules(),
            $request->messages()
        );
    }

    private function updateValidator(Tag $tag, array $input)
    {
        $request = UpdateTagRequest::create(
            '/admin/tags/'.$tag->id,
            'PUT'
        );

        // 編集対象のタグをルートのパラメーターに設定する
        $route = new Route('PUT', 'admin/tags/{tag}', function () {});
        $route->bind($request);
        $route->setParameter('tag', $tag);

        $request->setRouteResolver(fn () => $route);

        return Validator::make(
            $input,
            $request->rules(),
            $request->messages()
        );
    }

    public function test_store_accepts_valid_name(): void
    {
        $this->assertTrue(
            $this->storeValidator(['name' => '質問'])->passes()
        );
    }

    public function test_store_rejects_missing_or_empty_name(): void
    {
        foreach ([[], ['name' => ''], ['name' => null]] as $input) {
            $this->assertSame(
                'タグ名を入力してください',
                $this->storeValidator($input)->errors()->first('name')
            );
        }
    }

    public function test_store_rejects_non_string_name(): void
    {
        $this->assertSame(
            'タグ名は文字列で入力してください',
            $this->storeValidator([
                'name' => ['質問'],
            ])->errors()->first('name')
        );
    }

    public function test_store_accepts_50_characters_but_rejects_51(): void
    {
        $this->assertTrue($this->storeValidator([
            'name' => str_repeat('あ', 50),
        ])->passes());

        $this->assertSame(
            'タグ名は50文字以内で入力してください',
            $this->storeValidator([
                'name' => str_repeat('あ', 51),
            ])->errors()->first('name')
        );
    }

    public function test_store_rejects_duplicate_name(): void
    {
        Tag::create(['name' => '質問']);

        $this->assertSame(
            'そのタグ名は既に使用されています',
            $this->storeValidator([
                'name' => '質問',
            ])->errors()->first('name')
        );
    }

    public function test_update_accepts_new_name(): void
    {
        $tag = Tag::create(['name' => '質問']);

        $this->assertTrue($this->updateValidator($tag, [
            'name' => '商品への質問',
        ])->passes());
    }

    public function test_update_accepts_its_own_name(): void
    {
        $tag = Tag::create(['name' => '質問']);

        $this->assertTrue($this->updateValidator($tag, [
            'name' => '質問',
        ])->passes());
    }

    public function test_update_rejects_another_tags_name(): void
    {
        $tag = Tag::create(['name' => '質問']);
        Tag::create(['name' => '要望']);

        $this->assertSame(
            'そのタグ名は既に使用されています',
            $this->updateValidator($tag, [
                'name' => '要望',
            ])->errors()->first('name')
        );
    }

    public function test_update_rejects_missing_or_empty_name(): void
    {
        $tag = Tag::create(['name' => '質問']);

        foreach ([[], ['name' => ''], ['name' => null]] as $input) {
            $this->assertSame(
                'タグ名を入力してください',
                $this->updateValidator($tag, $input)
                    ->errors()->first('name')
            );
        }
    }

    public function test_update_rejects_non_string_name(): void
    {
        $tag = Tag::create(['name' => '質問']);

        $this->assertSame(
            'タグ名は文字列で入力してください',
            $this->updateValidator($tag, [
                'name' => ['要望'],
            ])->errors()->first('name')
        );
    }

    public function test_update_accepts_50_characters_but_rejects_51(): void
    {
        $tag = Tag::create(['name' => '質問']);

        $this->assertTrue($this->updateValidator($tag, [
            'name' => str_repeat('あ', 50),
        ])->passes());

        $this->assertSame(
            'タグ名は50文字以内で入力してください',
            $this->updateValidator($tag, [
                'name' => str_repeat('あ', 51),
            ])->errors()->first('name')
        );
    }
}
