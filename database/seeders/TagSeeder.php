<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

class TagSeeder extends Seeder
{
    public function run(): void
    {
        $tags = [
            '質問',
            '要望',
            '不具合報告',
            'ご意見',
            'その他',
        ];

        foreach ($tags as $name) {
            Tag::firstOrCreate([
                'name' => $name,
            ]);
        }
    }
}
