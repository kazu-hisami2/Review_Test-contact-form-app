<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 管理者一覧画面でお問い合わせが7件ごとにページネーションされる(): void
    {
        // Arrange: ログイン用管理者と 10 件のお問い合わせを用意
        $admin = User::factory()->create();
        Contact::factory()->count(10)->create();

        // Act: 管理者としてログインして /admin にGETリクエスト
        $response = $this->actingAs($admin)->get('/admin');

        // Assert
        $response->assertOk();
        $response->assertViewHas('contacts');

        // View に渡された $contacts の件数を検証
        $contacts = $response->viewData('contacts');
        $this->assertEquals(7, $contacts->count(), '1ページ目の表示件数が7件であること');
        $this->assertEquals(10, $contacts->total(), '全体のデータ件数が10件であること');
    }

    /** @test */
    public function キーワード・性別・カテゴリ・日付フィルタで検索結果が絞り込まれる(): void
    {
        // Arrange: ターゲットデータと検索から除外されるデータの作成
        $admin = User::factory()->create();
        $category1 = Category::factory()->create(['content' => '商品について']);
        $category2 = Category::factory()->create(['content' => 'その他']);

        $target = Contact::factory()->create([
            'first_name' => '山田',
            'gender' => 1,
            'category_id' => $category1->id,
            'created_at' => '2026-03-01 10:00:00',
        ]);

        $other = Contact::factory()->create([
            'first_name' => '佐藤',
            'gender' => 2,
            'category_id' => $category2->id,
            'created_at' => '2026-03-02 10:00:00',
        ]);

        // Act: 検索クエリを含めたGETリクエストを送る
        $response = $this->actingAs($admin)
            ->get("/admin?keyword=山田&gender=1&category_id={$category1->id}&date=2026-03-01");

        // Assert: 対象データのみが画面に存在し、除外データは含まれていないこと
        $response->assertOk();
        $response->assertSee('山田');
        $response->assertDontSee('佐藤');
    }

    /** @test */
    public function 指定したお問い合わせの詳細ページがカテゴリ情報付きで表示される(): void
    {
        // Arrange: 管理者、カテゴリ、お問い合わせデータの作成
        $admin = User::factory()->create();
        $category = Category::factory()->create(['content' => '商品に関するお問い合わせ']);

        $contact = Contact::factory()->create([
            'first_name' => '山田',
            'last_name' => '太郎',
            'email' => 'test@example.com',
            'category_id' => $category->id,
            'detail' => '詳細画面のテストメッセージです。',
        ]);

        // Act: 管理者としてログインし、詳細画面へアクセス
        $response = $this->actingAs($admin)
            ->get("/admin/contacts/{$contact->id}");

        // Assert: 200 OK かつ admin.show ビューが返る
        $response->assertOk();
        $response->assertViewIs('admin.show');

        // ビューにお問い合わせデータ（$contact）が渡されていることや、画面表示の検証
        $response->assertViewHas('contact');
        $response->assertSee('山田 太郎');
        $response->assertSee('test@example.com');
        $response->assertSee('商品に関するお問い合わせ');
    }

    /** @test */
    public function 指定したお問い合わせが正常に削除されホームにリダイレクトされる(): void
    {
        // Arrange: 管理者、カテゴリ、お問い合わせデータの作成
        $admin = User::factory()->create();

        $contact = Contact::factory()->create();

        // Act: 管理者としてログインし、お問い合わせを削除
        $response = $this->actingAs($admin)
            ->delete("/admin/contacts/{$contact->id}");

        // Assert: /admin にリダイレクトされ、対象のレコードが消えている
        $response->assertRedirect('/admin');
        $this->assertDatabaseMissing('contacts', [
            'id' => $contact->id,
        ]);
    }
}
