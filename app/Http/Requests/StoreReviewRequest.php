<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'user_id' => $this->user()->id,
        ]);
    }

    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'user_id' => [
                Rule::unique('reviews', 'user_id')->where('course_id', $this->route('course')->id),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'rating.required' => '評価を選択してください。',
            'rating.integer' => '評価は整数で指定してください。',
            'rating.between' => '評価は1〜5の範囲で選択してください。',
            'comment.max' => 'コメントは1000文字以内で入力してください。',
            'user_id.unique' => 'このコースには既にレビューを投稿済みです。',
        ];
    }
}
