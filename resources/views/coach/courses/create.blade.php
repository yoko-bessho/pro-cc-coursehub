@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">コース作成</h1>
        <p class="mt-1 text-sm text-gray-500">新しいコースの基本情報を入力してください</p>
    </div>

    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <form method="POST" action="{{ route('coach.courses.store') }}" enctype="multipart/form-data" x-data="{ submitting: false, imagePreview: null }" @submit="submitting = true">
            @csrf

            <div class="mb-5">
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">タイトル</label>
                <input type="text" name="title" id="title" value="{{ old('title') }}" required
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2.5 border">
            </div>

            <div class="mb-5">
                <label for="category_id" class="block text-sm font-medium text-gray-700 mb-1">カテゴリ</label>
                <select name="category_id" id="category_id" required class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2.5 border">
                    <option value="">選択してください</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" {{ old('category_id') == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="mb-5">
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">概要</label>
                <textarea name="description" id="description" rows="4" required
                    class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2.5 border">{{ old('description') }}</textarea>
            </div>

            <div class="mb-5">
                <label for="image" class="block text-sm font-medium text-gray-700 mb-1">サムネイル画像</label>
                <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/jpg,image/gif"
                    @change="imagePreview = $event.target.files.length ? URL.createObjectURL($event.target.files[0]) : null"
                    class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg shadow-sm cursor-pointer
                        focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500
                        file:mr-4 file:py-2.5 file:px-4 file:border-0 file:rounded-l-lg
                        file:text-sm file:font-medium file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100">
                <p class="mt-1 text-xs text-gray-500">JPEG, PNG, GIF形式、2MBまで</p>
                @error('image')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror

                <template x-if="imagePreview">
                    <img :src="imagePreview" alt="プレビュー" class="mt-3 w-full max-w-xs object-contain rounded-lg border border-gray-200">
                </template>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 mb-5">
                <div>
                    <label for="difficulty" class="block text-sm font-medium text-gray-700 mb-1">難易度</label>
                    <select name="difficulty" id="difficulty" required class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2.5 border">
                        <option value="beginner" {{ old('difficulty') === 'beginner' ? 'selected' : '' }}>初級</option>
                        <option value="intermediate" {{ old('difficulty') === 'intermediate' ? 'selected' : '' }}>中級</option>
                        <option value="advanced" {{ old('difficulty') === 'advanced' ? 'selected' : '' }}>上級</option>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">ステータス</label>
                    <select name="status" id="status" required class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2.5 border">
                        <option value="draft" {{ old('status') === 'draft' ? 'selected' : '' }}>下書き</option>
                        <option value="published" {{ old('status') === 'published' ? 'selected' : '' }}>公開</option>
                    </select>
                </div>
            </div>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">タグ</label>
                <div class="flex flex-wrap gap-3">
                    @foreach($tags as $tag)
                        <label class="flex items-center cursor-pointer">
                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                                {{ in_array($tag->id, old('tags', [])) ? 'checked' : '' }}
                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ml-1.5 text-sm text-gray-700">{{ $tag->name }}</span>
                        </label>
                    @endforeach
                </div>

                <div class="mt-3">
                    <label for="new_tags" class="block text-sm font-medium text-gray-700 mb-1">新しいタグを追加</label>
                    <input type="text" name="new_tags" id="new_tags" value="{{ old('new_tags') }}"
                        placeholder="カンマ区切りで入力（例: Laravel, PHP）"
                        class="w-full rounded-lg border-gray-300 shadow-sm focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 px-3 py-2.5 border">
                </div>
            </div>

            <button type="submit" :disabled="submitting"
                :class="submitting ? 'opacity-50 cursor-not-allowed' : ''"
                class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg px-4 py-2.5 shadow-sm transition-all duration-150">
                <span x-show="!submitting">作成する</span>
                <span x-show="submitting" class="flex items-center">
                    <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    作成中...
                </span>
            </button>
        </form>
    </div>
</div>
@endsection
