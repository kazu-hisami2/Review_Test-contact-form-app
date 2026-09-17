<?php

namespace Tests\Unit;

use App\Http\Requests\ExportContactRequest;
use App\Http\Requests\IndexContactRequest;
use App\Http\Requests\StoreContactRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ContactTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function お問い合わせ一覧の各フィルタが有効に機能する(): void
    {
        // Arrange: テストデータの作成
        $category = Category::factory()->create();

        $target = Contact::factory()->create([
            'first_name' => '山田',
            'gender' => 1,
            'category_id' => $category->id,
            'created_at' => '2026-03-01 10:00:00',
        ]);

        // 検索件数や検索一覧の妨害データ
        Contact::factory()->create([
            'first_name' => '佐藤',
            'gender' => 2,
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $request = new IndexContactRequest([
            'keyword' => '山田',
            'gender' => 1,
            'category_id' => $category->id,
            'date' => '2026-03-01',
        ]);

        // Act: Contactモデルのスコープメソッドを直接呼び出して実行
        $results = Contact::search($request)->get();

        // Assert: 結果の検証
        $this->assertCount(1, $results);
        $this->assertEquals($target->id, $results->first()->id);
    }

    /** @test */
    public function 検索時にgenderに不正な値が渡された場合バリデーションエラーになる(): void
    {
        // Arrange: 検索フォームリクエストと不正な値の用意
        $request = new IndexContactRequest;
        $invalidData = [
            'gender' => 99, // 'gender'定義外の不正値
        ];

        // Act: invalidDataにrules()メソッドを実行
        $validator = Validator::make($invalidData, $request->rules());

        // Assert: バリデーションが失敗し、gender カラムにエラーがある
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('gender', $validator->errors()->toArray());
    }

    /** @test */
    public function 問い合わせ保存の必須項目とタグ入力を正常に受け付ける(): void
    {
        // Arrange: 必須項目 + tag_ids
        $category = Category::factory()->create();
        $tags = Tag::factory()->count(2)->create();
        $validData = [
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'email' => 'test@example.com',
            'tel' => '09012345678',
            'address' => '東京都渋谷区...',
            'category_id' => $category->id,
            'detail' => 'お問い合わせ内容です。',
            'tag_ids' => $tags->pluck('id')->toArray(),
        ];

        $request = new StoreContactRequest;

        // Act
        $validator = Validator::make($validData, $request->rules());

        // Assert: バリデーションが通過する
        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function 問い合わせ保存で不正な電話番号形式の場合はバリデーションエラーになる(): void
    {
        // Arrange: 桁数が不正な電話番号（12桁）を用意
        $category = Category::factory()->create();
        $tags = Tag::factory()->count(2)->create();
        $invalidData = [
            'first_name' => '太郎',
            'last_name' => '山田',
            'gender' => 1,
            'email' => 'test@example.com',
            'tel' => '090123456789',
            'address' => '東京都渋谷区...',
            'category_id' => $category->id,
            'detail' => 'お問い合わせ内容です。',
            'tag_ids' => $tags->pluck('id')->toArray(),
        ];

        $request = new StoreContactRequest;

        // Act
        $validator = Validator::make($invalidData, $request->rules());

        // Assert: バリデーションが失敗し、tel キーにエラーが存在する
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('tel', $validator->errors()->toArray());
    }

    /** @test */
    public function お問い合わせは特定のカテゴリーに属している(): void
    {
        // Arrange: カテゴリと、それに紐づくお問い合わせを作成
        $category = Category::factory()->create(['content' => 'その他']);
        $contact = Contact::factory()->create([
            'category_id' => $category->id,
        ]);

        // Act: リレーション経由で Category を取得
        $relatedCategory = $contact->category;

        // Assert: 取得したモデルが Category のインスタンスであり、IDと名前が一致する
        $this->assertInstanceOf(Category::class, $relatedCategory);
        $this->assertEquals($category->id, $relatedCategory->id);
        $this->assertEquals('その他', $relatedCategory->content);
    }

    /** @test */
    public function お問い合わせに複数のタグを同期_sync_できる(): void
    {
        // Arrange: お問い合わせと複数のタグを作成
        $contact = Contact::factory()->create();
        $tags = Tag::factory()->count(3)->create();
        $tagIds = $tags->pluck('id')->toArray();

        // Act: tags() リレーションに対して sync を実行
        $contact->tags()->sync($tagIds);

        // Assert: データベース（中間テーブル）に正しく紐付けが保存され、リレーションから3件取得できる
        $this->assertCount(3, $contact->fresh()->tags);

        foreach ($tags as $tag) {
            $this->assertDatabaseHas('contact_tag', [
                'contact_id' => $contact->id,
                'tag_id' => $tag->id,
            ]);
        }
    }

    /** @test */
    public function エクスポートでの検索フィルタが有効に機能する(): void
    {
        // Arrange: テストデータの作成
        $category = Category::factory()->create();

        $target = Contact::factory()->create([
            'first_name' => '山田',
            'gender' => 1,
            'category_id' => $category->id,
            'created_at' => '2026-03-01 10:00:00',
        ]);

        // 検索件数や検索一覧の妨害データ
        Contact::factory()->create([
            'first_name' => '佐藤',
            'gender' => 2,
            'created_at' => '2026-01-01 10:00:00',
        ]);

        $request = new ExportContactRequest([
            'keyword' => '山田',
            'gender' => 1,
            'category_id' => $category->id,
            'date' => '2026-03-01',
        ]);

        // Act: Contactモデルのスコープメソッドを直接呼び出して実行
        $results = Contact::search($request)->get();

        // Assert: 結果の検証
        $this->assertCount(1, $results);
        $this->assertEquals($target->id, $results->first()->id);
    }

    /** @test */
    public function エクスポートでgenderに不正な値が渡された場合バリデーションエラーになる(): void
    {
        // Arrange: 検索フォームリクエストと不正な値の用意
        $request = new ExportContactRequest;
        $invalidData = [
            'gender' => 99, // 'gender'定義外の不正値
        ];

        // Act: invalidDataにrules()メソッドを実行
        $validator = Validator::make($invalidData, $request->rules());

        // Assert: バリデーションが失敗し、gender カラムにエラーがある
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('gender', $validator->errors()->toArray());
    }

    /** @test */
    public function エクスポートでcategory_idに存在しない値が渡された場合バリデーションエラーになる(): void
    {
        // Arrange: 検索フォームリクエストと不正な値の用意
        Category::factory()->create();
        $request = new ExportContactRequest;
        $invalidData = [
            'category_id' => 99,
        ];

        // Act: invalidDataにrules()メソッドを実行
        $validator = Validator::make($invalidData, $request->rules());

        // Assert: バリデーションが失敗し、category_id カラムにエラーがある
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('category_id', $validator->errors()->toArray());
    }
}
