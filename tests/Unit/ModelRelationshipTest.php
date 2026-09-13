<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModelRelationshipTest extends TestCase
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
            'address' => '東京都渋谷区千駄ヶ谷1-2-3',
            'building' => null,
            'detail' => '配送について教えてください。',
        ]);
    }

    public function test_category_returns_only_its_contacts(): void
    {
        $category = Category::create(['content' => '商品のお届けについて']);
        $otherCategory = Category::create(['content' => '商品の交換について']);

        $first = $this->createContact($category);
        $second = $this->createContact($category);
        $this->createContact($otherCategory);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $category->contacts->modelKeys()
        );
    }

    public function test_contact_returns_its_category(): void
    {
        $category = Category::create(['content' => '商品のお届けについて']);
        Category::create(['content' => '商品の交換について']);

        $contact = $this->createContact($category);

        $this->assertTrue($contact->category->is($category));
        $this->assertSame(
            '商品のお届けについて',
            $contact->category->content
        );
    }

    public function test_contact_returns_only_attached_tags(): void
    {
        $category = Category::create(['content' => '商品のお届けについて']);
        $contact = $this->createContact($category);

        $question = Tag::create(['name' => '質問']);
        $request = Tag::create(['name' => '要望']);
        Tag::create(['name' => 'その他']);

        $contact->tags()->attach([$question->id, $request->id]);

        $this->assertEqualsCanonicalizing(
            [$question->id, $request->id],
            $contact->tags->modelKeys()
        );

        foreach ($contact->tags as $tag) {
            $this->assertNotNull($tag->pivot->created_at);
            $this->assertNotNull($tag->pivot->updated_at);
        }
    }

    public function test_tag_returns_only_related_contacts(): void
    {
        $category = Category::create(['content' => '商品のお届けについて']);

        $first = $this->createContact($category);
        $second = $this->createContact($category);
        $this->createContact($category);

        $tag = Tag::create(['name' => '質問']);

        $first->tags()->attach($tag->id);
        $second->tags()->attach($tag->id);

        $this->assertEqualsCanonicalizing(
            [$first->id, $second->id],
            $tag->contacts->modelKeys()
        );
    }

    public function test_sync_replaces_tags_without_affecting_other_contacts(): void
    {
        $category = Category::create(['content' => '商品のお届けについて']);

        $contact = $this->createContact($category);
        $otherContact = $this->createContact($category);

        $question = Tag::create(['name' => '質問']);
        $request = Tag::create(['name' => '要望']);
        $report = Tag::create(['name' => '不具合報告']);

        $contact->tags()->attach([$question->id, $request->id]);
        $otherContact->tags()->attach($question->id);

        // 「質問・要望」から「要望・不具合報告」に付け替える
        $contact->tags()->sync([$request->id, $report->id]);

        $this->assertEqualsCanonicalizing(
            [$request->id, $report->id],
            $contact->fresh()->tags->modelKeys()
        );

        $this->assertDatabaseMissing('contact_tag', [
            'contact_id' => $contact->id,
            'tag_id' => $question->id,
        ]);

        $this->assertSame(
            [$question->id],
            $otherContact->fresh()->tags->modelKeys()
        );

        // 関連付けを外しても、タグ本体は残る
        $this->assertDatabaseCount('tags', 3);
    }

    public function test_sync_empty_array_removes_all_tag_associations(): void
    {
        $category = Category::create(['content' => '商品のお届けについて']);
        $contact = $this->createContact($category);
        $tag = Tag::create(['name' => '質問']);

        $contact->tags()->attach($tag->id);
        $contact->tags()->sync([]);

        $this->assertCount(0, $contact->fresh()->tags);

        $this->assertDatabaseMissing('contact_tag', [
            'contact_id' => $contact->id,
        ]);

        $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
        $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    }
}
