<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function 認証済みユーザーは管理ボードにアクセスできる(): void
    {
        // Arrange: 管理者ユーザーの準備
        $admin = User::factory()->create();

        // Act: 管理者としてホームにアクセス
        $response = $this->actingAs($admin)
            ->get('/admin');

        $response->assertOk();
        $response->assertViewIs('admin.index');
    }

    /** @test */
    public function 未認証認証ユーザーはログイン画面にリダイレクトされる(): void
    {
        $this->get('/admin/')
            ->assertRedirect('/login');
    }
}
