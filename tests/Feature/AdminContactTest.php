<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminContactTest extends TestCase
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

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')
            ->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_admin_page(): void
    {
        $contact = $this->createContact();

        $this->actingAs(User::factory()->create())
            ->get('/admin')
            ->assertOk()
            ->assertViewIs('admin.index')
            ->assertViewHas('contacts', function ($contacts) use ($contact) {
                return $contacts->total() === 1
                    && $contacts->first()->id === $contact->id;
            });
    }

    public function test_all_search_filters_can_be_used_together(): void
    {
        $category = Category::create([
            'content' => '商品の交換について',
        ]);

        $criteria = [
            'first_name' => '鈴木',
            'last_name' => '花子',
            'gender' => 2,
            'category_id' => $category->id,
        ];

        $matching = $this->createContact($criteria);
        $matching->created_at = '2026-09-12 12:00:00';
        $matching->save();

        // 条件を1つだけ変えたデータは、検索結果から除かれる
        $variations = [
            ['first_name' => '田中'],
            ['gender' => 1],
            [
                'category_id' => Category::where(
                    'content',
                    '商品のお届けについて'
                )->firstOrFail()->id,
            ],
        ];

        foreach ($variations as $variation) {
            $contact = $this->createContact(
                array_merge($criteria, $variation)
            );
            $contact->created_at = '2026-09-12 12:00:00';
            $contact->save();
        }

        $differentDate = $this->createContact($criteria);
        $differentDate->created_at = '2026-09-11 12:00:00';
        $differentDate->save();

        $query = http_build_query([
            'keyword' => '鈴',
            'gender' => '2',
            'category_id' => $category->id,
            'date' => '2026-09-12',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin?'.$query)
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) use ($matching) {
                return $contacts->total() === 1
                    && $contacts->first()->id === $matching->id;
            });
    }

    public function test_email_can_be_searched_by_partial_match(): void
    {
        $matching = $this->createContact([
            'email' => 'target@example.com',
        ]);

        $this->createContact([
            'email' => 'another@example.com',
        ]);

        $this->actingAs(User::factory()->create())
            ->get('/admin?keyword=target')
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) use ($matching) {
                return $contacts->total() === 1
                    && $contacts->first()->id === $matching->id;
            });
    }

    public function test_contacts_are_paginated_seven_per_page(): void
    {
        for ($index = 0; $index < 8; $index++) {
            $this->createContact();
        }

        $this->actingAs(User::factory()->create());

        $this->get('/admin')
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->perPage() === 7
                    && $contacts->count() === 7
                    && $contacts->total() === 8;
            });

        $this->get('/admin?page=2')
            ->assertOk()
            ->assertViewHas('contacts', function ($contacts) {
                return $contacts->currentPage() === 2
                    && $contacts->count() === 1;
            });
    }

    public function test_invalid_gender_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/admin')
            ->get('/admin?gender=9')
            ->assertRedirect('/admin')
            ->assertSessionHasErrors([
                'gender' => '性別の値が不正です',
            ]);
    }

    public function test_nonexistent_category_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/admin')
            ->get('/admin?category_id=999999')
            ->assertRedirect('/admin')
            ->assertSessionHasErrors([
                'category_id' => '選択されたカテゴリーが存在しません',
            ]);
    }

    public function test_contact_detail_displays_category_and_tags(): void
    {
        $contact = $this->createContact();

        $tag = Tag::create(['name' => '質問']);
        $contact->tags()->attach($tag->id);

        $this->actingAs(User::factory()->create())
            ->get('/admin/contacts/'.$contact->id)
            ->assertOk()
            ->assertViewIs('admin.show')
            ->assertViewHas('contact', function ($displayed) use ($contact) {
                return $displayed->id === $contact->id;
            })
            ->assertSee('山田')
            ->assertSee('太郎')
            ->assertSee('商品のお届けについて')
            ->assertSee('質問')
            ->assertSee('配送について教えてください。');
    }

    public function test_contact_deletion_removes_associations_but_keeps_tag(): void
    {
        $contact = $this->createContact();
        $remaining = $this->createContact([
            'email' => 'remaining@example.com',
        ]);

        $tag = Tag::create(['name' => '質問']);

        $contact->tags()->attach($tag->id);
        $remaining->tags()->attach($tag->id);

        $this->actingAs(User::factory()->create())
            ->delete('/admin/contacts/'.$contact->id)
            ->assertRedirect('/admin');

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);

        $this->assertDatabaseMissing('contact_tag', [
            'contact_id' => $contact->id,
        ]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $remaining->id,
        ]);

        $this->assertDatabaseHas('contact_tag', [
            'contact_id' => $remaining->id,
            'tag_id' => $tag->id,
        ]);
    }

    public function test_guest_cannot_view_or_delete_contact(): void
    {
        $contact = $this->createContact();

        $this->get('/admin/contacts/'.$contact->id)
            ->assertRedirect('/login');

        $this->delete('/admin/contacts/'.$contact->id)
            ->assertRedirect('/login');

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
        ]);
    }

    public function test_nonexistent_contact_returns_not_found(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/contacts/999999')
            ->assertNotFound();

        $this->delete('/admin/contacts/999999')
            ->assertNotFound();
    }
}
