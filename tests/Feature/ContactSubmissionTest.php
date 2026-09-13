<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private function validInput(): array
    {
        $category = Category::create([
            'content' => '商品のお届けについて',
        ]);

        $tag = Tag::create([
            'name' => '質問',
        ]);

        return [
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => '1',
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'address' => '東京都渋谷区千駄ヶ谷1-2-3',
            'building' => 'テストビル101',
            'category_id' => $category->id,
            'detail' => '商品の到着予定日を教えてください。',
            'tag_ids' => [$tag->id],
        ];
    }

    public function test_input_page_displays_categories_and_tags(): void
    {
        $this->validInput();

        $this->get('/')
            ->assertOk()
            ->assertViewIs('contact.index')
            ->assertViewHasAll(['categories', 'tags'])
            ->assertSee('商品のお届けについて')
            ->assertSee('質問');
    }

    public function test_thanks_page_is_displayed(): void
    {
        $this->get('/thanks')
            ->assertOk()
            ->assertViewIs('contact.thanks')
            ->assertSee('お問い合わせありがとうございました');
    }

    public function test_valid_input_displays_confirmation_without_saving(): void
    {
        $input = $this->validInput();

        $this->post('/contacts/confirm', $input)
            ->assertOk()
            ->assertViewIs('contact.confirm')
            ->assertSee('山田')
            ->assertSee('太郎')
            ->assertSee('yamada@example.com')
            ->assertSee('商品のお届けについて')
            ->assertSee('質問');

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_tag', 0);
    }

    public function test_confirmation_rejects_missing_required_fields(): void
    {
        $this->from('/')
            ->post('/contacts/confirm', [])
            ->assertRedirect('/')
            ->assertSessionHasErrors([
                'first_name',
                'last_name',
                'gender',
                'email',
                'tel',
                'address',
                'category_id',
                'detail',
            ]);

        $this->assertDatabaseCount('contacts', 0);
    }

    public function test_submission_saves_contact_and_tag(): void
    {
        $input = $this->validInput();

        $this->post('/contacts', $input)
            ->assertRedirect('/thanks')
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('contacts', 1);

        $this->assertDatabaseHas('contacts', [
            'first_name' => '山田',
            'last_name' => '太郎',
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'category_id' => $input['category_id'],
            'detail' => $input['detail'],
        ]);

        $contact = Contact::firstOrFail();

        $this->assertDatabaseCount('contact_tag', 1);

        $this->assertDatabaseHas('contact_tag', [
            'contact_id' => $contact->id,
            'tag_id' => $input['tag_ids'][0],
        ]);
    }

    public function test_submission_rejects_invalid_phone_without_saving(): void
    {
        $input = $this->validInput();
        $input['tel'] = '090-1234-5678';

        $this->from('/')
            ->post('/contacts', $input)
            ->assertRedirect('/')
            ->assertSessionHasErrors([
                'tel' => '電話番号はハイフンなしの10〜11桁で入力してください',
            ]);

        $this->assertDatabaseCount('contacts', 0);
        $this->assertDatabaseCount('contact_tag', 0);
    }
}
