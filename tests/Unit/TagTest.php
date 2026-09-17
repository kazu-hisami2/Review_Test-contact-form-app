<?php

namespace Tests\Unit;

use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function タグの名前が50字以内かつ一意なものは新規作成できる(): void
    {
        // Arrange: フォームリクエストとテストデータの作成
        $request = new StoreTagRequest;

        Tag::factory()->create([
            'name' => str_repeat('あ', 50),
        ]);

        $validData = ['name' => str_repeat('い', 50)];

        // Act: validDataにrules()メソッドを実行
        $validator = Validator::make($validData, $request->rules());

        // Assert:
        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function タグの名前が空欄のときはバリデーションエラーになる(): void
    {
        // Arrange: フォームリクエストとテストデータの作成
        $request = new StoreTagRequest;
        $invalidData = [
            'name' => ' ',
        ];

        // Act: validDataにrules()メソッドを実行
        $validator = Validator::make($invalidData, $request->rules());

        // Assert:
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /** @test */
    public function タグの名前が51字のときはバリデーションエラーになる(): void
    {
        // Arrange: フォームリクエストとテストデータの作成
        $request = new StoreTagRequest;
        $invalidData = [
            'name' => str_repeat('あ', 51),
        ];

        // Act: validDataにrules()メソッドを実行
        $validator = Validator::make($invalidData, $request->rules());

        // Assert:
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /** @test */
    public function タグの名前が重複するときもバリデーションエラーになる(): void
    {
        // Arrange: フォームリクエストとテストデータの作成
        $request = new StoreTagRequest;

        Tag::factory()->create([
            'name' => str_repeat('あ', 50),
        ]);

        $invalidData = [
            'name' => str_repeat('あ', 50),
        ];

        // Act: validDataにrules()メソッドを実行
        $validator = Validator::make($invalidData, $request->rules());

        // Assert:
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /** @test */
    public function 自身のタグ名は名前変更なしでも更新ができる(): void
    {
        // Arrange: フォームリクエストとテストデータの作成
        $request = new UpdateTagRequest;

        $tag = Tag::factory()->create(['name' => '更新タグ']);

        $request->setRouteResolver(fn () => new class($tag)
        {
            public function __construct(private $tag) {}

            public function parameter($name)
            {
                return $this->tag;
            }
        });

        $validData = [
            'name' => '更新タグ',
        ];

        // Act: validDataにrules()メソッドを実行
        $validator = Validator::make($validData, $request->rules());

        // Assert: 自身のタグなのでバリデーションエラーにならない
        $this->assertTrue($validator->passes());
    }

    /** @test */
    public function タグ更新時に他の既存タグ名と重複する場合はバリデーションエラーになる(): void
    {
        // Arrange: 2つの異なるタグを作成
        $request = new UpdateTagRequest;
        Tag::factory()->create(['name' => 'タグA']);
        $tag = Tag::factory()->create(['name' => 'タグB']);

        $request->setRouteResolver(fn () => new class($tag)
        {
            public function __construct(private $tag) {}

            public function parameter($name)
            {
                return $this->tag;
            }
        });

        $invalidData = ['name' => 'タグA'];

        // Act
        $validator = Validator::make($invalidData, $request->rules());

        // Assert: 重複エラーになる
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('name', $validator->errors()->toArray());
    }

    /** @test */
    public function １つのタグに複数のお問い合わせが紐づく(): void
    {
        // Arrange: 1つのタグと、それに属する3つのお問い合わせを作成
        $tag = Tag::factory()->create();
        $contacts = Contact::factory()->count(3)->create();
        $tag->contacts()->attach($contacts->pluck('id'));

        // Act: Tag モデルのリレーション経由でお問い合わせを取得
        $relatedContacts = $tag->contacts;

        // Assert: 3件取得でき、型やIDが一致している
        $this->assertCount(3, $relatedContacts);
        $this->assertInstanceOf(Contact::class, $relatedContacts->first());
        $this->assertTrue($relatedContacts->contains($contacts->first()));
    }
}
