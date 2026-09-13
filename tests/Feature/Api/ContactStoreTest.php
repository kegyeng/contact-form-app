<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactStoreTest extends TestCase
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

    public function test_guest_can_create_contact_with_category_and_tags(): void
    {
        $input = $this->validInput();
        $secondTag = Tag::create(['name' => '要望']);
        $input['tag_ids'][] = $secondTag->id;

        $response = $this->postJson('/api/v1/contacts', $input);

        $response->assertCreated()
            ->assertJsonPath('data.first_name', '山田')
            ->assertJsonPath('data.last_name', '太郎')
            ->assertJsonPath('data.gender', 1)
            ->assertJsonPath('data.email', $input['email'])
            ->assertJsonPath('data.tel', $input['tel'])
            ->assertJsonPath('data.category.id', $input['category_id'])
            ->assertJsonPath('data.category.content', '商品のお届けについて')
            ->assertJsonCount(2, 'data.tags');

        $contact = Contact::findOrFail($response->json('data.id'));

        $expected = $input;
        unset($expected['tag_ids']);

        $this->assertDatabaseHas('contacts', array_merge(
            ['id' => $contact->id],
            $expected
        ));

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('contact_tag', 2);

        $this->assertEqualsCanonicalizing(
            $input['tag_ids'],
            array_column($response->json('data.tags'), 'id')
        );

        foreach ($input['tag_ids'] as $tagId) {
            $this->assertDatabaseHas('contact_tag', [
                'contact_id' => $contact->id,
                'tag_id' => $tagId,
            ]);
        }

        $this->assertGuest();
    }

    public function test_contact_can_be_created_without_optional_fields(): void
    {
        $input = $this->validInput();
        unset($input['building'], $input['tag_ids']);

        $this->postJson('/api/v1/contacts', $input)
            ->assertCreated()
            ->assertJsonPath('data.building', null)
            ->assertJsonPath('data.tags', []);

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('contact_tag', 0);
    }

    public function test_missing_required_fields_return_json_errors_without_saving(): void
    {
        $this->postJson('/api/v1/contacts', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'first_name',
                'last_name',
                'gender',
                'email',
                'tel',
                'address',
                'category_id',
                'detail',
            ])
            ->assertJsonPath(
                'errors.first_name.0',
                '姓を入力してください'
            );

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_tag', 0);
    }

    public function test_invalid_phone_is_rejected_without_saving(): void
    {
        $input = $this->validInput();
        $input['tel'] = '090-1234-5678';

        $this->postJson('/api/v1/contacts', $input)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tel')
            ->assertJsonPath(
                'errors.tel.0',
                '電話番号はハイフンなしの10〜11桁で入力してください'
            );

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_tag', 0);
    }

    public function test_nonexistent_category_is_rejected_without_saving(): void
    {
        $input = $this->validInput();
        $input['category_id'] = 999999;

        $this->postJson('/api/v1/contacts', $input)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('category_id')
            ->assertJsonPath(
                'errors.category_id.0',
                '選択されたカテゴリーが存在しません'
            );

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_tag', 0);
    }

    public function test_nonexistent_tag_is_rejected_without_partial_saving(): void
    {
        $input = $this->validInput();
        $input['tag_ids'][] = 999999;

        $this->postJson('/api/v1/contacts', $input)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids.1')
            ->assertJsonFragment([
                'tag_ids.1' => ['選択されたタグが存在しません'],
            ]);

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_tag', 0);
    }

    public function test_validation_returns_json_even_without_accept_header(): void
    {
        $this->post('/api/v1/contacts', [])
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonValidationErrors('first_name');

        $this->assertDatabaseCount('contacts', 0);
    }
}
