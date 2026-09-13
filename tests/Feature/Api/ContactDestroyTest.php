<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactDestroyTest extends TestCase
{
    use RefreshDatabase;

    private function createContact(Category $category): Contact
    {
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

    public function test_guest_can_delete_contact_without_affecting_other_data(): void
    {
        $category = Category::create([
            'content' => '商品のお届けについて',
        ]);

        $contact = $this->createContact($category);
        $remaining = $this->createContact($category);

        $sharedTag = Tag::create(['name' => '質問']);
        $otherTag = Tag::create(['name' => '要望']);

        $contact->tags()->attach([$sharedTag->id, $otherTag->id]);
        $remaining->tags()->attach($sharedTag->id);

        $this->deleteJson('/api/v1/contacts/'.$contact->id)
            ->assertNoContent();

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);

        $this->assertDatabaseMissing('contact_tag', [
            'contact_id' => $contact->id,
        ]);

        $this->assertDatabaseHas('contacts', [
            'id' => $remaining->id,
        ]);

        $this->assertDatabaseHas('contact_tag', [
            'contact_id' => $remaining->id,
            'tag_id' => $sharedTag->id,
        ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);

        $this->assertDatabaseHas('tags', ['id' => $sharedTag->id]);
        $this->assertDatabaseHas('tags', ['id' => $otherTag->id]);

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseCount('contact_tag', 1);
        $this->assertGuest();
    }

    public function test_contact_without_tags_can_be_deleted(): void
    {
        $category = Category::create([
            'content' => '商品のお届けについて',
        ]);
        $contact = $this->createContact($category);

        $this->deleteJson('/api/v1/contacts/'.$contact->id)
            ->assertNoContent();

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_tag', 0);
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
        ]);
    }

    public function test_nonexistent_contact_returns_json_not_found(): void
    {
        $category = Category::create([
            'content' => '商品のお届けについて',
        ]);
        $remaining = $this->createContact($category);

        $this->deleteJson('/api/v1/contacts/999999')
            ->assertNotFound()
            ->assertExactJson([
                'error' => 'お問い合わせが見つかりませんでした。',
            ]);

        $this->assertDatabaseCount('contacts', 1);
        $this->assertDatabaseHas('contacts', [
            'id' => $remaining->id,
        ]);
    }

    public function test_deleting_same_contact_twice_returns_not_found(): void
    {
        $category = Category::create([
            'content' => '商品のお届けについて',
        ]);
        $contact = $this->createContact($category);
        $url = '/api/v1/contacts/'.$contact->id;

        $this->deleteJson($url)->assertNoContent();

        $this->deleteJson($url)
            ->assertNotFound()
            ->assertExactJson([
                'error' => 'お問い合わせが見つかりませんでした。',
            ]);

        $this->assertDatabaseCount('contacts', 0);
    }
}
