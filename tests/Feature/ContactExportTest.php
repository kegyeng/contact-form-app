<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactExportTest extends TestCase
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
            'address' => '東京都渋谷区千駄ヶ谷1-2-3',
            'building' => null,
            'detail' => '配送について教えてください。',
        ], $attributes));
    }

    private function parseCsv(string $content): array
    {
        // 先頭のBOMを除いて、CSVを行と列に分解する
        $handle = fopen('php://temp', 'r+');
        fwrite($handle, substr($content, 3));
        rewind($handle);

        $rows = [];

        while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function test_guest_cannot_export_contacts(): void
    {
        $this->get('/contacts/export')
            ->assertRedirect('/login');
    }

    public function test_export_has_bom_and_correct_japanese_columns(): void
    {
        $contact = $this->createContact();

        $response = $this->actingAs(User::factory()->create())
            ->get('/contacts/export');

        $response->assertOk();
        $response->assertDownload();
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $response->streamedContent();

        $this->assertSame("\xEF\xBB\xBF", substr($content, 0, 3));

        $rows = $this->parseCsv($content);

        $this->assertCount(2, $rows);
        $this->assertSame([
            'ID',
            '氏名',
            '性別',
            'メール',
            '電話',
            '住所',
            '建物',
            'カテゴリ',
            '内容',
            '作成日時',
        ], $rows[0]);

        $this->assertSame([
            (string) $contact->id,
            '山田 太郎',
            '男性',
            'yamada@example.com',
            '09012345678',
            '東京都渋谷区千駄ヶ谷1-2-3',
            '',
            '商品のお届けについて',
            '配送について教えてください。',
            $contact->created_at->format('Y-m-d H:i:s'),
        ], $rows[1]);
    }

    public function test_export_includes_all_contacts_in_newest_order(): void
    {
        $expectedIds = [];

        for ($i = 0; $i < 8; $i++) {
            $contact = $this->createContact([
                'email' => "contact{$i}@example.com",
            ]);

            // ID順と日時順を逆にして、日時で並ぶことを確認する
            $contact->created_at = now()->subDays($i);
            $contact->save();

            $expectedIds[] = (string) $contact->id;
        }

        $response = $this->actingAs(User::factory()->create())
            ->get('/contacts/export');

        $response->assertOk();

        $rows = $this->parseCsv($response->streamedContent());

        // 見出し1行＋データ8行。画面の7件制限を受けない
        $this->assertCount(9, $rows);
        $this->assertSame(
            $expectedIds,
            array_column(array_slice($rows, 1), 0)
        );
    }

    public function test_export_applies_all_search_filters(): void
    {
        $category = Category::create(['content' => '商品の交換について']);

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

        $response = $this->actingAs(User::factory()->create())
            ->get('/contacts/export?'.http_build_query([
                'keyword' => '鈴',
                'gender' => '2',
                'category_id' => $category->id,
                'date' => '2026-09-12',
            ]));

        $response->assertOk();

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertCount(2, $rows);
        $this->assertSame((string) $target->id, $rows[1][0]);
    }

    public function test_export_rejects_invalid_gender(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/contacts/export?gender=9')
            ->assertRedirect('/admin')
            ->assertSessionHasErrors('gender');
    }

    public function test_export_rejects_nonexistent_category(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/contacts/export?category_id=999999')
            ->assertRedirect('/admin')
            ->assertSessionHasErrors('category_id');
    }

    public function test_export_without_matches_contains_only_header(): void
    {
        $this->createContact();

        $response = $this->actingAs(User::factory()->create())
            ->get('/contacts/export?keyword=存在しない名前');

        $response->assertOk();

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertCount(1, $rows);
        $this->assertCount(10, $rows[0]);
        $this->assertSame('ID', $rows[0][0]);
    }

    public function test_export_preserves_commas_quotes_and_line_breaks(): void
    {
        $detail = "商品A,商品Bの\"配送\"\nについて確認したいです。";

        $this->createContact(['detail' => $detail]);

        $response = $this->actingAs(User::factory()->create())
            ->get('/contacts/export');

        $response->assertOk();

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertCount(2, $rows);
        $this->assertCount(10, $rows[1]);
        $this->assertSame($detail, $rows[1][8]);
    }

    public function test_export_escapes_formula_like_input(): void
    {
        $this->createContact(['detail' => '=1+1']);

        $response = $this->actingAs(User::factory()->create())
            ->get('/contacts/export');

        $response->assertOk();

        $rows = $this->parseCsv($response->streamedContent());

        $this->assertSame("'=1+1", $rows[1][8]);
    }
}
