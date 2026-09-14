<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTagRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required','max:50','unique:tags'],
        ];
    }

    public function messages()
    {
        return [
            'name.required' => 'タグ名を追加してください',
            'name.max' => 'タグ名は50字以内で入力してください',
            'name.unique' => 'そのタグ名は既に使用されています',
        ];
    }
}
