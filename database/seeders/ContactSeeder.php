<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Database\Seeder;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = Category::all();

        // カテゴリーidはクロージャでランダムにidを設定
        $contacts = Contact::factory()->count(20)->create([
            'category_id' => fn () => $categories->random()->id,
        ]);

        $tags = Tag::all();

        foreach ($contacts as $contact) {
            $tagCount = rand(1, 3); // タグの件数を1～3件
            $randomTags = $tags->random($tagCount); //　$tags配列から重複なくランダムにタグを指定
            $contact->tags()->attach($randomTags);
        }
    }
}
