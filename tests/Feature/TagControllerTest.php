<?php

namespace Tests\Feature;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 認証済みユーザーがタグ編集画面を表示できる(): void
    {
        // Arrnge: 管理者とタグの準備
        $admin = User::factory()->create();
        $tag = Tag::factory()->create();

        // Act: 管理者としてタグ編集画面にアクセス
        $response = $this->actingAs($admin)
            ->get("/admin/tags/{$tag->id}/edit");

        // Assert: ビューが返され、設定したtagがbladeに渡されているかの検証
        $response->assertOk();
        $response->assertViewIs('admin.tags.edit');
        $response->assertViewHas('tag');
    }

    /** @test */
    public function 認証済みユーザーがタグを作成できホームへリダイレクトされる(): void
    {
        // Arrange: 管理者と作成用のタグ配列の準備
        $admin = User::factory()->create();
        $tagData = ['name' => '新規タグ'];

        // Act: 管理者としてタグを新規作成
        $response = $this->actingAs($admin)
            ->post('/admin/tags', $tagData);

        // Arrange: 作成後にホームにリダイレクトされ、DBに新規タグが作成されたことを確認
        $response->assertRedirect('/admin');
        $this->assertDatabaseHas('tags', ['name' => '新規タグ']);
    }

    /** @test */
    public function 認証済みユーザーがタグを更新できホームへリダイレクトされる(): void
    {
        // Arrange: 管理者とタグの作成
        $admin = User::factory()->create();
        $tag = Tag::factory()->create(['name' => '変更前']);

        // Act: 管理者としてタグを更新
        $response = $this->actingAs($admin)
            ->put("/admin/tags/{$tag->id}", ['name' => '変更後']);

        // Arrange: 作成後にホームにリダイレクトされ、DBでタグが更新されたことを確認
        $response->assertRedirect('/admin');
        $this->assertDatabaseHas('tags', [
            'id' => $tag->id,
            'name' => '変更後',
        ]);
    }

    /** @test */
    public function 認証済みユーザーがタグを削除できホームへリダイレクトされる(): void
    {
        // Arrange: 管理者とタグの作成
        $admin = User::factory()->create();
        $tag = Tag::factory()->create();

        // Act: 管理者としてタグを削除
        $response = $this->actingAs($admin)
            ->delete("/admin/tags/{$tag->id}");

        // Arrange: 作成後にホームにリダイレクトされ、DBからタグが削除されたことを確認
        $response->assertRedirect('/admin');
        $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
    }

    /** @test */
    public function 未認証ユーザーのタグ操作は拒否され_loginへリダイレクトされる(): void
    {
        $tag = Tag::factory()->create();

        // 編集画面表示
        $this->get("/admin/tags/{$tag->id}/edit")
            ->assertRedirect('/login');

        // 作成
        $this->post('/admin/tags', ['name' => '作成'])
            ->assertRedirect('/login');

        // 更新
        $this->put("/admin/tags/{$tag->id}", ['name' => '更新'])
            ->assertRedirect('/login');

        // 削除
        $this->delete("/admin/tags/{$tag->id}")
            ->assertRedirect('/login');
    }
}
