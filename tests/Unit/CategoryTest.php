<?php

namespace Tests\Unit;

use App\Models\Category;
use App\Models\Contact;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function １つのカテゴリーから複数のお問い合わせを取得できる(): void
    {
        // Arrange: 1つのカテゴリと、それに属する3つのお問い合わせを作成
        $category = Category::factory()->create();
        $contacts = Contact::factory()->count(3)->create([
            'category_id' => $category->id,
        ]);

        // Act: Category モデルのリレーション経由でお問い合わせを取得
        $relatedContacts = $category->contacts;

        // Assert: 3件取得でき、型やIDが一致している
        $this->assertCount(3, $relatedContacts);
        $this->assertInstanceOf(Contact::class, $relatedContacts->first());
        $this->assertTrue($relatedContacts->contains($contacts->first()));
    }
}
