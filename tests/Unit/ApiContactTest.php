<?php

namespace Tests\Unit;

use App\Http\Requests\Api\V1\IndexContactRequest;
use App\Http\Requests\Api\V1\StoreContactRequest;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class ApiContactTest extends TestCase
{
    use RefreshDatabase;

    /**
     * お問い合わせの正常系ベースデータ
     */
    private function validStoreData(array $overrides = []): array
    {
        $category = Category::factory()->create();
        $tags = Tag::factory()->count(2)->create();

        return array_merge([
            'category_id' => $category->id,
            'first_name' => '山田',
            'last_name' => '太郎',
            'gender' => 1,
            'email' => 'yamada@example.com',
            'tel' => '09012345678',
            'address' => '東京都千代田区1-1-1',
            'building' => 'テストビル101',
            'detail' => 'お問い合わせの詳細テキストです。',
            'tag_ids' => $tags->pluck('id')->toArray(),
        ], $overrides);
    }

    /** @test */
    public function 検索時有効なパラメータの場合はバリデーションを通過する(): void
    {
        $category = Category::factory()->create();
        $request = new IndexContactRequest;

        $validData = [
            'keyword' => 'テスト',
            'gender' => 1,
            'category_id' => $category->id,
            'date' => '2026-01-01',
            'per_page' => 15,
            'page' => 1,
        ];

        $validator = Validator::make($validData, $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * @test
     *
     * @dataProvider invalidIndexContactDataProvider
     */
    public function 検索時不正なパラメータの場合はバリデーションエラーになる(string $field, mixed $value): void
    {
        $category = Category::factory()->create();
        $request = new IndexContactRequest;

        $data = [
            'keyword' => 'テスト',
            'gender' => 1,
            'category_id' => $category->id,
            'date' => '2026-01-01',
            'per_page' => 15,
            $field => $value,
        ];

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey($field, $validator->errors()->toArray());
    }

    public static function invalidIndexContactDataProvider(): array
    {
        return [
            '性別に範囲外の値' => ['gender', 99],
            '日付形式が不正' => ['date', 'invalid-date'],
            '存在しないカテゴリID' => ['category_id', 99],
            'per_pageが数値以外' => ['per_page', 'あ'],
            'per_pageが範囲外の値' => ['per_page', '101'],
            'pageが数値以外' => ['page', 'あ'],
        ];
    }

    /** @test */
    public function 作成時全ての必須項目とタグ_i_dが正しい場合はバリデーションを通過する(): void
    {
        $request = new StoreContactRequest;
        $data = $this->validStoreData();

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->passes());
    }

    /**
     * @test
     *
     * @dataProvider invalidStoreContactDataProvider
     */
    public function 作成時不正な入力値の場合はバリデーションエラーになる(string $field, mixed $value): void
    {
        $request = new StoreContactRequest;
        $data = $this->validStoreData([$field => $value]);

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());

        $errorKeys = array_keys($validator->errors()->toArray());
        $hasError = collect($errorKeys)->contains(
            fn ($key) => $key === $field || str_starts_with($key, $field.'.')
        );

        $this->assertTrue($hasError, "フィールド [{$field}] に対するバリデーションエラーが発生していません。");
    }

    public static function invalidStoreContactDataProvider(): array
    {
        return [
            'genderの値が範囲外' => ['gender', 99],
            'category_idが存在しない' => ['category_id', 99],
            'tag_idsに存在しないID' => ['tag_ids', [99]],
            'detailが121文字（超過）' => ['detail', str_repeat('あ', 121)],
            'emailがメール形式でない' => ['email', 'invalid-email'],
            'telが桁数不正（12桁）' => ['tel', '090123456789'],
        ];
    }
}
