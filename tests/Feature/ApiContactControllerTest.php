<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiContactControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function お問い合わせ一覧が正しい_jso_n構造とページネーション形式で返る(): void
    {
        // Arrange: 15件のテストデータを生成
        Contact::factory()->count(15)->create();

        // Act: 1ページあたり10件取得
        $response = $this->getJson('/api/v1/contacts?per_page=10');

        // Assert: お問い合わせ一覧取得とページネーションの検証
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'first_name',
                        'last_name',
                        'gender',
                        'email',
                        'detail',
                        'category',
                        'created_at',
                    ],
                ],
                'links' => ['first', 'last', 'prev', 'next'],
                'meta' => ['current_page', 'last_page', 'per_page', 'total'],
            ])
            ->assertJsonCount(10, 'data');
    }

    /** @test */
    public function 検索条件で一覧を絞り込める(): void
    {
        $categoryA = Category::factory()->create();
        $categoryB = Category::factory()->create();

        // 検索対象のデータ
        $target = Contact::factory()->create([
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'category_id' => $categoryA->id,
        ]);

        // 対象外のデータ
        Contact::factory()->create([
            'first_name' => '佐藤',
            'last_name' => '花子',
            'gender' => 2,
            'category_id' => $categoryB->id,
        ]);

        $queryParams = http_build_query([
            'keyword' => '山田',
            'gender' => 1,
            'category_id' => $categoryA->id,
        ]);

        $response = $this->getJson("/api/v1/contacts?{$queryParams}");

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    /** @test */
    public function 不正な検索パラメータの場合は422エラーとエラーメッセージが返る(): void
    {
        // 性別に不正な値（99）と不正な日付形式を指定
        $response = $this->getJson('/api/v1/contacts?gender=99&date=invalid-date');

        $response->assertStatus(422)->assertJsonValidationErrors(['gender', 'date']);
    }

    /** @test */
    public function お問い合わせ詳細が正しい_jso_n構造で返る(): void
    {
        // Arrange: テストデータの作成
        $contact = Contact::factory()->create([
            'first_name' => '山田',
            'last_name' => '太郎',
        ]);

        // Act: 詳細APIへGETリクエスト
        $response = $this->getJson("/api/v1/contacts/{$contact->id}");

        // Assert: 200 OK と JSON 構造・内容の検証
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'gender',
                    'email',
                    'tel',
                    'address',
                    'building',
                    'detail',
                    'category',
                    'tags',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.first_name', '山田');
    }

    /** @test */
    public function 存在しない_i_dの場合は404エラーが返る(): void
    {
        // Arrange: テストデータの作成
        $contact = Contact::factory()->create([
            'first_name' => '山田',
            'last_name' => '太郎',
        ]);

        // Act: 存在しないIDでリクエスト
        $response = $this->getJson('/api/v1/contacts/99');

        // Assert: 404 Not Found の検証
        $response->assertStatus(404);
    }

    /** @test */
    public function 作成時有効なデータでお問い合わせが作成され201と保存データが返る(): void
    {
        $category = Category::factory()->create();
        $tags = Tag::factory()->count(2)->create();

        $postData = [
            'category_id' => $category->id,
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'address' => '東京都千代田区1-1-1',
            'building' => 'テストビル101',
            'detail' => '新規お問い合わせのテストです。',
            'tag_ids' => $tags->pluck('id')->toArray(),
        ];

        $response = $this->postJson('/api/v1/contacts', $postData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'category',
                    'tags',
                    'created_at',
                ],
            ])
            ->assertJsonPath('data.email', 'yamada@example.com');

        $this->assertDatabaseHas('contacts', [
            'first_name' => '山田',
            'last_name' => '太郎',
            'email' => 'yamada@example.com',
            'category_id' => $category->id,
        ]);

        foreach ($tags as $tag) {
            $this->assertDatabaseHas('contact_tag', [
                'tag_id' => $tag->id,
            ]);
        }
    }

    /** @test */
    public function 作成時バリデーションエラーの場合はお問い合わせが作成されず422が返る(): void
    {
        $response = $this->postJson('/api/v1/contacts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'category_id',
                'first_name',
                'last_name',
                'gender',
                'email',
                'tel',
                'address',
                'detail',
            ]);

        $this->assertDatabaseCount('contacts', 0);
    }

    /** @test */
    public function 更新時有効なデータでお問い合わせが更新され200と更新後のデータが返る(): void
    {
        $contact = Contact::factory()->create([
            'first_name' => '山田',
            'detail' => '更新前の詳細テキストです。',
        ]);
        $newCategory = Category::factory()->create();
        $newTags = Tag::factory()->count(2)->create();

        $updateData = [
            'category_id' => $newCategory->id,
            'first_name' => '佐藤',
            'last_name' => '次郎',
            'gender' => 2,
            'email' => 'sato@example.com',
            'tel' => '08098765432',
            'address' => '大阪府大阪市1-1-1',
            'building' => '更新ビル202',
            'detail' => 'お問い合わせ内容を更新しました。',
            'tag_ids' => $newTags->pluck('id')->toArray(),
        ];

        $response = $this->putJson("/api/v1/contacts/{$contact->id}", $updateData);

        $response->assertStatus(200)
            ->assertJsonPath('data.id', $contact->id)
            ->assertJsonPath('data.first_name', '佐藤')
            ->assertJsonPath('data.email', 'sato@example.com');

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'first_name' => '佐藤',
            'email' => 'sato@example.com',
        ]);
    }

    /** @test */
    public function 更新時存在しない_i_dの場合は404エラーが返る(): void
    {
        $category = Category::factory()->create();
        $updateData = [
            'category_id' => $category->id,
            'first_name' => '佐藤',
            'last_name' => '次郎',
            'gender' => 1,
            'email' => 'sato@example.com',
            'tel' => '09012345678',
            'address' => '東京都千代田区1-1-1',
            'detail' => '詳細テキスト情報です。',
        ];

        $response = $this->putJson('/api/v1/contacts/99', $updateData);

        $response->assertStatus(404);
    }

    /** @test */
    public function 更新時バリデーションエラーの場合は更新されず422が返る(): void
    {
        $contact = Contact::factory()->create([
            'email' => 'original@example.com',
        ]);

        $response = $this->putJson("/api/v1/contacts/{$contact->id}", [
            'email' => 'invalid-email',
            'detail' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'detail']);

        $this->assertDatabaseHas('contacts', [
            'id' => $contact->id,
            'email' => 'original@example.com',
        ]);
    }

    /** @test */
    public function 削除時有効な_i_dを指定した場合はレコードが削除され204が返る(): void
    {
        $contact = Contact::factory()->create();

        $response = $this->deleteJson("/api/v1/contacts/{$contact->id}");

        $response->assertStatus(204);

        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }

    /** @test */
    public function 削除時存在しない_i_dの場合は404エラーが返る(): void
    {
        $response = $this->deleteJson('/api/v1/contacts/99');

        $response->assertStatus(404);
    }
}
