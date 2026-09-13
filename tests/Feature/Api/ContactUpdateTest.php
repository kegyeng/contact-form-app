<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function createContact(): Contact
    {
        $category = Category::create([
            'content' => '商品のお届けについて',
        ]);

        $contact = Contact::create([
            'category_id' => $category->id,
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'address' => '東京都渋谷区1-2-3',
            'building' => 'さくらビル101',
            'detail' => '更新前のお問い合わせです。',
        ]);

        $tag = Tag::create(['name' => '質問']);
        $contact->tags()->attach($tag->id);

        return $contact;
    }

    private function updateInput(Contact $contact): array
    {
        return [
            'category_id' => $contact->category_id,
            'first_name' => '鈴木',
            'last_name' => '花子',
            'gender' => 2,
            'email' => 'suzuki@example.com',
            'tel' => '0312345678',
            'address' => '大阪府大阪市1-2-3',
            'building' => null,
            'detail' => '更新後のお問い合わせです。',
            'tag_ids' => [],
        ];
    }

    private function assertContactUnchanged(
        Contact $contact,
        array $before,
        array $tagIds
    ): void {
        $fresh = $contact->fresh();

        $this->assertSame($before, $fresh->getAttributes());
        $this->assertEqualsCanonicalizing(
            $tagIds,
            $fresh->tags->modelKeys()
        );
    }

    public function test_guest_can_update_contact_and_replace_tags(): void
    {
        $contact = $this->createContact();
        $oldTag = $contact->tags->first();

        $category = Category::create([
            'content' => '商品の交換について',
        ]);
        $newTag = Tag::create(['name' => '要望']);

        $input = $this->updateInput($contact);
        $input['category_id'] = $category->id;
        $input['tag_ids'] = [$newTag->id];

        $this->putJson('/api/v1/contacts/'.$contact->id, $input)
            ->assertOk()
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.first_name', '鈴木')
            ->assertJsonPath('data.last_name', '花子')
            ->assertJsonPath('data.category.id', $category->id)
            ->assertJsonPath('data.category.content', '商品の交換について')
            ->assertJsonCount(1, 'data.tags')
            ->assertJsonPath('data.tags.0.id', $newTag->id);

        $expected = $input;
        unset($expected['tag_ids']);

        $this->assertDatabaseHas('contacts', array_merge(
            ['id' => $contact->id],
            $expected
        ));
        $this->assertDatabaseCount('contacts', 1);

        $this->assertDatabaseMissing('contact_tag', [
            'contact_id' => $contact->id,
            'tag_id' => $oldTag->id,
        ]);
        $this->assertDatabaseHas('contact_tag', [
            'contact_id' => $contact->id,
            'tag_id' => $newTag->id,
        ]);
        $this->assertDatabaseHas('tags', ['id' => $oldTag->id]);
        $this->assertGuest();
    }

    public function test_empty_tag_array_removes_associations(): void
    {
        $contact = $this->createContact();

        $this->putJson(
            '/api/v1/contacts/'.$contact->id,
            $this->updateInput($contact)
        )
            ->assertOk()
            ->assertJsonPath('data.tags', []);

        $this->assertDatabaseMissing('contact_tag', [
            'contact_id' => $contact->id,
        ]);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_omitted_tags_remove_associations(): void
    {
        $contact = $this->createContact();
        $input = $this->updateInput($contact);
        unset($input['tag_ids']);

        $this->putJson('/api/v1/contacts/'.$contact->id, $input)
            ->assertOk()
            ->assertJsonPath('data.tags', []);

        $this->assertDatabaseMissing('contact_tag', [
            'contact_id' => $contact->id,
        ]);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_invalid_phone_does_not_change_contact_or_tags(): void
    {
        $contact = $this->createContact();
        $before = $contact->fresh()->getAttributes();
        $tagIds = $contact->tags->modelKeys();

        $input = $this->updateInput($contact);
        $input['tel'] = '090-1234-5678';

        $this->putJson('/api/v1/contacts/'.$contact->id, $input)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tel');

        $this->assertContactUnchanged($contact, $before, $tagIds);
    }

    public function test_nonexistent_tag_does_not_change_contact_or_tags(): void
    {
        $contact = $this->createContact();
        $before = $contact->fresh()->getAttributes();
        $tagIds = $contact->tags->modelKeys();

        $input = $this->updateInput($contact);
        $input['tag_ids'] = [$tagIds[0], 999999];

        $this->putJson('/api/v1/contacts/'.$contact->id, $input)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tag_ids.1')
            ->assertJsonFragment([
                'tag_ids.1' => ['選択されたタグが存在しません'],
            ]);

        $this->assertContactUnchanged($contact, $before, $tagIds);
    }

    public function test_missing_required_fields_do_not_change_contact(): void
    {
        $contact = $this->createContact();
        $before = $contact->fresh()->getAttributes();
        $tagIds = $contact->tags->modelKeys();

        $this->putJson('/api/v1/contacts/'.$contact->id, [])
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
            ]);

        $this->assertContactUnchanged($contact, $before, $tagIds);
    }

    public function test_nonexistent_contact_returns_json_not_found(): void
    {
        $contact = $this->createContact();

        $this->putJson(
            '/api/v1/contacts/999999',
            $this->updateInput($contact)
        )
            ->assertNotFound()
            ->assertExactJson([
                'error' => 'お問い合わせが見つかりませんでした。',
            ]);

        $this->assertDatabaseCount('contacts', 1);
    }
}
