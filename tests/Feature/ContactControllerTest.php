<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function お問い合わせフォーム入力ページが正常に表示されカテゴリとタグが表示される(): void
    {
        // Arrange: 画面表示確認用のカテゴリとタグ（複数）を作成
        $categories = Category::factory()->count(2)->create();
        $tags = Tag::factory()->count(2)->create();

        // Act: 入力ページ（/）に GET リクエスト
        $response = $this->get('/');

        // Assert
        $response->assertStatus(200);
        $response->assertViewHas('categories');
        $response->assertViewHas('tags');

        // レンダリングされた画面内に最初のタグ名が含まれているか確認
        $response->assertSee($categories->first()->content);
        $response->assertSee($tags->first()->name);
    }

    /** @test */
    public function サンクスページが正常に表示される(): void
    {
        // Act: サンクスページに GET リクエスト
        $response = $this->get('/thanks');

        // Assert: 200 OK で正常表示されること
        $response->assertStatus(200);
    }

    /** @test */
    public function 入力内容が正しい場合確認画面が表示され入力内容とカテゴリ名が表示される(): void
    {
        // Arrange: カテゴリとタグの作成
        $category = Category::factory()->create(['content' => 'その他']);
        $tag = Tag::factory()->create(['name' => 'ご要望']);

        $formData = [
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'address' => '東京都渋谷区1-1-1',
            'category_id' => $category->id,
            'detail' => 'テストのお問い合わせ内容です。',
            'tag_ids' => [$tag->id],
        ];

        // Act: POST リクエストで確認画面を表示
        $response = $this->post('/contacts/confirm', $formData);

        // Assert
        $response->assertOk();
        $response->assertViewIs('contact.confirm');
        $response->assertSee('山田 太郎');
        $response->assertSee('yamada@example.com');
        $response->assertSee('その他');
        $response->assertSee('ご要望');
    }

    /** @test */
    public function バリデーションエラー時は確認画面に進まずエラーとともにリダイレクトされる(): void
    {
        // Arrange: 必須項目が欠落した不正なデータ
        $invalidFormData = [
            'first_name' => '',
            'email' => 'invalid-email',
        ];

        // Act: POST リクエストを送信
        $response = $this->post('/contacts/confirm', $invalidFormData);

        // Assert: 直前ページへのリダイレクト,対象フィールドのエラーが含まれていることの確認
        $response->assertRedirect();
        $response->assertSessionHasErrors(['first_name', 'email', 'last_name', 'category_id']);
    }

    /** @test */
    public function お問い合わせ内容とタグが正常にデータベースへ保存されサンクスページへリダイレクトされる(): void
    {
        // Arrange: カテゴリとタグの作成
        $category = Category::factory()->create();
        $tag = Tag::factory()->create();

        $formData = [
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'address' => '東京都渋谷区1-1-1',
            'category_id' => $category->id,
            'detail' => 'テストのお問い合わせ内容です。',
            'tag_ids' => [$tag->id],
        ];

        // Act: POST リクエストで保存処理を実行
        $response = $this->post('/contacts', $formData);

        // Assert
        // 1. サンクスページ（/thanks）へリダイレクトされること
        $response->assertRedirect('/thanks');

        // 2. contacts テーブルにデータが保存されていること
        $this->assertDatabaseHas('contacts', [
            'first_name' => '山田',
            'last_name' => '太郎',
            'email' => 'yamada@example.com',
            'category_id' => $category->id,
        ]);

        // 3. 中間テーブル（contact_tag）に正しく紐付けが保存されていること
        $this->assertDatabaseHas('contact_tag', [
            'tag_id' => $tag->id,
        ]);
    }

    /** @test */
    public function 保存時にバリデーションエラーの場合はデータベースに保存されずリダイレクトされる(): void
    {
        // Arrange: 不正なデータ
        $invalidFormData = [
            'first_name' => '',
            'email' => 'invalid-email',
        ];

        // Act: POST リクエストで保存を実行
        $response = $this->post('/contacts', $invalidFormData);

        // Assert: 前のページへリダイレクト,セッションエラー,レコードが追加されていないことの確認
        $response->assertRedirect();
        $response->assertSessionHasErrors(['first_name', 'email']);
        $this->assertDatabaseCount('contacts', 0);
    }

    /** @test */
    public function ログイン済み管理者がフィルタ条件付きで_cs_vをダウンロードできる(): void
    {
        // Arrange: 管理者ユーザーとテストデータの作成
        $admin = User::factory()->create();
        $category = Category::factory()->create();

        $target = Contact::factory()->create([
            'first_name' => '山田',
            'category_id' => $category->id,
        ]);

        $other = Contact::factory()->create([
            'first_name' => '佐藤',
            'category_id' => $category->id,
        ]);

        // Act: 管理者としてログインし、クエリ付きでアクセス
        $response = $this->actingAs($admin)
            ->get('/contacts/export?keyword=山田');

        // Assert
        $response->assertOk();

        $csvContent = $response->streamedContent();
        $this->assertStringContainsString('山田', $csvContent);
        $this->assertStringNotContainsString('佐藤', $csvContent);
    }

    /** @test */
    public function 検索条件無指定時は新着順で_cs_vが出力される(): void
    {
        // Arrange: 管理者データと新旧問い合わせを用意
        $admin = User::factory()->create();

        $oldContact = Contact::factory()->create([
            'email' => 'old@example.com',
            'created_at' => '2025-01-01 00:00:00',
        ]);

        $newContact = Contact::factory()->create([
            'email' => 'new@example.com',
            'created_at' => '2026-01-01 00:00:00',
        ]);

        // Act: 条件無指定でエクスポート
        $response = $this->actingAs($admin)
            ->get('/contacts/export');

        // Assert
        $response->assertOk();

        $csvContent = $response->streamedContent();

        // CSV文字列内でそれぞれのメールアドレスが登場する位置（インデックス）を取得
        $posNew = strpos($csvContent, 'new@example.com');
        $posOld = strpos($csvContent, 'old@example.com');

        // どちらも存在し、新しいデータの方が前の位置にあることを判定
        $this->assertNotFalse($posNew);
        $this->assertNotFalse($posOld);
        $this->assertLessThan($posOld, $posNew, '新着データが古いデータよりも先に出力されていること');
    }
}
