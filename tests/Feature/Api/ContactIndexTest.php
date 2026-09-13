<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactIndexTest extends TestCase
{
    use RefreshDatabase;

    private function createContact(array $attributes = []): Contact
    {
        $category = Category::firstOrCreate([
            'content' => '商品のお届けについて',
        ]);

        return Contact::create(array_merge([
            'category_id' => $category->id,
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'address' => '東京都渋谷区1-2-3',
            'building' => null,
            'detail' => '配送について教えてください。',
        ], $attributes));
    }

    public function test_guest_can_get_list_with_category_and_tags(): void
    {
        $contact = $this->createContact();
        $tag = Tag::create(['name' => '質問']);
        $contact->tags()->attach($tag->id);

        $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $contact->id)
            ->assertJsonPath('data.0.category.id', $contact->category_id)
            ->assertJsonPath('data.0.tags.0.id', $tag->id)
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 1);

        $this->assertGuest();
    }

    public function test_default_page_size_is_twenty(): void
    {
        for ($i = 0; $i < 21; $i++) {
            $this->createContact();
        }

        $first = $this->getJson('/api/v1/contacts')
            ->assertOk()
            ->assertJsonCount(20, 'data')
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 21);

        $second = $this->getJson('/api/v1/contacts?page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.current_page', 2);

        $this->assertEmpty(array_intersect(
            array_column($first->json('data'), 'id'),
            array_column($second->json('data'), 'id')
        ));
    }

    public function test_custom_page_size_and_newest_order(): void
    {
        $newest = $this->createContact();
        $middle = $this->createContact();
        $oldest = $this->createContact();

        foreach ([$newest, $middle, $oldest] as $days => $contact) {
            $contact->created_at = now()->subDays($days);
            $contact->save();
        }

        $first = $this->getJson('/api/v1/contacts?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);

        $this->assertSame(
            [$newest->id, $middle->id],
            array_column($first->json('data'), 'id')
        );

        $this->getJson('/api/v1/contacts?per_page=2&page=2')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $oldest->id)
            ->assertJsonPath('meta.current_page', 2);
    }

    public function test_all_search_filters_can_be_combined(): void
    {
        $category = Category::create([
            'content' => '商品の交換について',
        ]);

        $attributes = [
            'first_name' => '鈴木',
            'last_name' => '花子',
            'gender' => 2,
            'category_id' => $category->id,
        ];

        $target = $this->createContact($attributes);

        $differentName = $this->createContact(array_merge($attributes, [
            'first_name' => '田中',
        ]));

        $differentGender = $this->createContact(array_merge($attributes, [
            'gender' => 1,
        ]));

        $otherCategory = Category::where(
            'content',
            '商品のお届けについて'
        )->firstOrFail();

        $differentCategory = $this->createContact(array_merge($attributes, [
            'category_id' => $otherCategory->id,
        ]));

        foreach ([$target, $differentName, $differentGender, $differentCategory] as $contact) {
            $contact->created_at = '2026-09-12 12:00:00';
            $contact->save();
        }

        $differentDate = $this->createContact($attributes);
        $differentDate->created_at = '2026-09-11 12:00:00';
        $differentDate->save();

        $this->getJson('/api/v1/contacts?'.http_build_query([
            'keyword' => '鈴',
            'gender' => 2,
            'category_id' => $category->id,
            'date' => '2026-09-12',
        ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id)
            ->assertJsonPath('meta.total', 1);
    }

    public function test_keyword_matches_first_name_last_name_and_email(): void
    {
        $target = $this->createContact([
            'first_name' => '鈴木',
            'last_name' => '花子',
            'email' => 'hanako@example.com',
        ]);

        $this->createContact();

        foreach (['鈴', '花', 'hanako@'] as $keyword) {
            $this->getJson('/api/v1/contacts?'.http_build_query([
                'keyword' => $keyword,
            ]))
                ->assertOk()
                ->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', $target->id);
        }
    }

    public function test_no_matches_returns_empty_data(): void
    {
        $this->createContact();

        $this->getJson('/api/v1/contacts?'.http_build_query([
            'keyword' => '存在しない名前',
        ]))
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_api_rejects_zero_and_invalid_gender(): void
    {
        foreach ([0, 9] as $gender) {
            $this->getJson('/api/v1/contacts?gender='.$gender)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('gender')
                ->assertJsonPath(
                    'errors.gender.0',
                    '性別の値が不正です'
                );
        }
    }

    public function test_page_size_accepts_one_and_one_hundred(): void
    {
        foreach ([1, 100] as $perPage) {
            $this->getJson('/api/v1/contacts?per_page='.$perPage)
                ->assertOk()
                ->assertJsonPath('meta.per_page', $perPage);
        }
    }

    public function test_invalid_page_sizes_are_rejected(): void
    {
        foreach (['0', '101', 'abc', '1.5'] as $perPage) {
            $this->getJson('/api/v1/contacts?per_page='.$perPage)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('per_page');
        }
    }

    public function test_invalid_page_numbers_are_rejected(): void
    {
        foreach (['0', '-1', 'abc', '1.5'] as $page) {
            $this->getJson('/api/v1/contacts?page='.$page)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('page');
        }
    }

    public function test_invalid_category_date_and_keyword_are_rejected(): void
    {
        $this->getJson('/api/v1/contacts?'.http_build_query([
            'category_id' => 999999,
            'date' => '2026-02-30',
            'keyword' => str_repeat('あ', 256),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id',
                'date',
                'keyword',
            ]);
    }

    public function test_validation_returns_json_without_accept_header(): void
    {
        $this->get('/api/v1/contacts?gender=9')
            ->assertUnprocessable()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath(
                'errors.gender.0',
                '性別の値が不正です'
            );
    }
}
