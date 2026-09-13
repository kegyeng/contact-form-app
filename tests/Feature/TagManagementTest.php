<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_create_tag(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/admin/tags', ['name' => '新しいタグ'])
            ->assertRedirect('/admin');

        $this->assertDatabaseHas('tags', ['name' => '新しいタグ']);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_tag_creation_rejects_empty_name(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/admin')
            ->post('/admin/tags', ['name' => ''])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors([
                'name' => 'タグ名を入力してください',
            ]);

        $this->assertDatabaseCount('tags', 0);
    }

    public function test_tag_creation_rejects_duplicate_name(): void
    {
        Tag::create(['name' => '質問']);

        $this->actingAs(User::factory()->create())
            ->from('/admin')
            ->post('/admin/tags', ['name' => '質問'])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors([
                'name' => 'そのタグ名は既に使用されています',
            ]);

        $this->assertDatabaseCount('tags', 1);
    }

    public function test_tag_creation_rejects_name_over_fifty_characters(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/admin')
            ->post('/admin/tags', ['name' => str_repeat('あ', 51)])
            ->assertRedirect('/admin')
            ->assertSessionHasErrors([
                'name' => 'タグ名は50文字以内で入力してください',
            ]);

        $this->assertDatabaseCount('tags', 0);
    }

    public function test_tag_edit_page_displays_current_name(): void
    {
        $tag = Tag::create(['name' => '質問']);

        $this->actingAs(User::factory()->create())
            ->get('/admin/tags/'.$tag->id.'/edit')
            ->assertOk()
            ->assertViewIs('admin.tags.edit')
            ->assertViewHas('tag', function ($displayed) use ($tag) {
                return $displayed->id === $tag->id;
            })
            ->assertSee('質問');
    }

    public function test_authenticated_user_can_update_tag(): void
    {
        $tag = Tag::create(['name' => '質問']);

        $this->actingAs(User::factory()->create())
            ->put('/admin/tags/'.$tag->id, ['name' => '商品への質問'])
            ->assertRedirect('/admin');

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => '商品への質問',
        ]);
        $this->assertDatabaseCount('tags', 1);
    }

    public function test_tag_update_accepts_its_own_name(): void
    {
        $tag = Tag::create(['name' => '質問']);

        $this->actingAs(User::factory()->create())
            ->put('/admin/tags/'.$tag->id, ['name' => '質問'])
            ->assertRedirect('/admin')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => '質問',
        ]);
    }

    public function test_tag_update_rejects_another_tags_name(): void
    {
        $tag = Tag::create(['name' => '質問']);
        Tag::create(['name' => '要望']);
        $editUrl = '/admin/tags/'.$tag->id.'/edit';

        $this->actingAs(User::factory()->create())
            ->from($editUrl)
            ->put('/admin/tags/'.$tag->id, ['name' => '要望'])
            ->assertRedirect($editUrl)
            ->assertSessionHasErrors([
                'name' => 'そのタグ名は既に使用されています',
            ]);

        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => '質問',
        ]);
        $this->assertDatabaseCount('tags', 2);
    }

    public function test_authenticated_user_can_delete_tag(): void
    {
        $tag = Tag::create(['name' => '削除用']);
        $remaining = Tag::create(['name' => '残すタグ']);

        $this->actingAs(User::factory()->create())
            ->delete('/admin/tags/'.$tag->id)
            ->assertRedirect('/admin');

        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
        $this->assertDatabaseHas('tags', ['id' => $remaining->id]);
    }

    public function test_guest_cannot_manage_tags(): void
    {
        $tag = Tag::create(['name' => '質問']);

        $this->post('/admin/tags', ['name' => '追加できないタグ'])
            ->assertRedirect('/login');

        $this->get('/admin/tags/'.$tag->id.'/edit')
            ->assertRedirect('/login');

        $this->put('/admin/tags/'.$tag->id, ['name' => '変更できないタグ'])
            ->assertRedirect('/login');

        $this->delete('/admin/tags/'.$tag->id)
            ->assertRedirect('/login');

        $this->assertDatabaseCount('tags', 1);
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => '質問',
        ]);
    }
}
