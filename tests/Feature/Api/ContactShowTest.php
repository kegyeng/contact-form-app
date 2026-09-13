<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactShowTest extends TestCase
{
    use RefreshDatabase;

    private function createContact(): Contact
    {
        $category = Category::create([
            'content' => '商品のお届けについて',
        ]);

        return Contact::create([
            'category_id' => $category->id,
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'address' => '東京都渋谷区1-2-3',
            'building' => null,
            'detail' => '配送について教えてください。',
        ]);
    }

    public function test_guest_can_get_contact_with_category_and_tags(): void
    {
        $contact = $this->createContact();

        $question = Tag::create(['name' => '質問']);
        $request = Tag::create(['name' => '要望']);
        Tag::create(['name' => '関連しないタグ']);

        $contact->tags()->attach([$question->id, $request->id]);

        $response = $this->getJson('/api/v1/contacts/'.$contact->id);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'category' => ['id', 'content'],
                    'first_name',
                    'last_name',
                    'gender',
                    'email',
                    'tel',
                    'address',
                    'building',
                    'detail',
                    'tags' => [
                        '*' => ['id', 'name'],
                    ],
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.category.id', $contact->category_id)
            ->assertJsonPath(
                'data.category.content',
                '商品のお届けについて'
            )
            ->assertJsonPath('data.first_name', '山田')
            ->assertJsonPath('data.last_name', '太郎')
            ->assertJsonPath('data.gender', 1)
            ->assertJsonPath('data.email', 'yamada@example.com')
            ->assertJsonPath('data.tel', '09012345678')
            ->assertJsonPath('data.address', '東京都渋谷区1-2-3')
            ->assertJsonPath('data.building', null)
            ->assertJsonPath(
                'data.detail',
                '配送について教えてください。'
            )
            ->assertJsonCount(2, 'data.tags');

        $this->assertEqualsCanonicalizing(
            [$question->id, $request->id],
            array_column($response->json('data.tags'), 'id')
        );

        $this->assertEqualsCanonicalizing(
            ['質問', '要望'],
            array_column($response->json('data.tags'), 'name')
        );

        $this->assertGuest();
    }

    public function test_contact_without_tags_returns_empty_array(): void
    {
        $contact = $this->createContact();

        $this->getJson('/api/v1/contacts/'.$contact->id)
            ->assertOk()
            ->assertJsonPath('data.tags', []);
    }

    public function test_nonexistent_contact_returns_json_not_found(): void
    {
        $this->getJson('/api/v1/contacts/999999')
            ->assertNotFound()
            ->assertExactJson([
                'error' => 'お問い合わせが見つかりませんでした。',
            ]);
    }

    public function test_not_found_returns_json_without_accept_header(): void
    {
        $this->get('/api/v1/contacts/999999')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'error' => 'お問い合わせが見つかりませんでした。',
            ]);
    }
}
