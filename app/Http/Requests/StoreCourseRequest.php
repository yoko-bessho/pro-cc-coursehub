<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCourseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => [
                'required', 'string', 'max:255',
                Rule::unique('courses', 'title')->where('user_id', auth()->id()),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'description' => ['required', 'string'],
            'difficulty' => ['required', 'in:beginner,intermediate,advanced'],
            'status' => ['required', 'in:draft,published'],
            'image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['exists:tags,id'],
            'new_tags' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'title.required' => 'タイトルは必須です。',
            'title.string' => 'タイトルは文字列で入力してください。',
            'title.max' => 'タイトルは255文字以内で入力してください。',
            'title.unique' => '同じタイトルのコースが既に存在します。',
            'category_id.required' => 'カテゴリを選択してください。',
            'category_id.exists' => '選択されたカテゴリが存在しません。',
            'description.required' => '概要は必須です。',
            'description.string' => '概要は文字列で入力してください。',
            'difficulty.required' => '難易度を選択してください。',
            'difficulty.in' => '難易度の値が不正です。',
            'status.required' => 'ステータスを選択してください。',
            'status.in' => 'ステータスの値が不正です。',
            'image.image' => '画像ファイルを選択してください。',
            'image.mimes' => '画像はjpeg, png, jpg, gif形式のみアップロードできます。',
            'image.max' => '画像サイズは2MB以内にしてください。',
            'tags.array' => 'タグの形式が不正です。',
            'tags.*.exists' => '選択されたタグが存在しません。',
            'new_tags.string' => '新規タグは文字列で入力してください。',
        ];
    }
}
