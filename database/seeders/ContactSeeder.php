<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    public function run(): void
    {
        $faker = Faker::create('ja_JP');

        $categoryIds = Category::pluck('id')->all();
        $tagIds = Tag::pluck('id')->all();

        for ($i = 0; $i < 20; $i++) {
            $contact = Contact::create([
                'category_id' => $faker->randomElement($categoryIds),
                'first_name' => $faker->lastName(),  // 姓
                'last_name' => $faker->firstName(),  // 名
                'gender' => $faker->numberBetween(1, 3),
                'email' => $faker->safeEmail(),
                'tel' => $faker->numerify('090########'),
                'address' => $faker->address(),
                'building' => $faker->optional()->randomElement([
                    'さくらマンション101号室',
                    'ひかりハイツ202号室',
                    'みどりビル303号室',
                ]),
                'detail' => $faker->randomElement([
                    '商品の到着予定日を教えてください。',
                    '商品の交換方法について確認したいです。',
                    '届いた商品に不具合があるため相談したいです。',
                    'ショップの営業時間を教えてください。',
                    '商品の品ぞろえを増やしていただきたいです。',
                ]),
            ]);

            $selectedTagIds = $faker->randomElements(
                $tagIds,
                $faker->numberBetween(1, 3)
            );

            $contact->tags()->attach($selectedTagIds);
        }
    }
}
