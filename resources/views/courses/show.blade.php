@extends('layouts.app')

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- コース情報ヘッダー --}}
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="flex-1">
                <h1 class="text-2xl font-bold text-gray-900 mb-3">{{ $course->title }}</h1>
                <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500 mb-4">
                    <span class="flex items-center gap-1">
                        <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                        {{ $course->user->name }}
                    </span>
                    <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                        {{ $course->difficulty === 'beginner' ? 'bg-green-50 text-green-700' : ($course->difficulty === 'intermediate' ? 'bg-amber-50 text-amber-700' : 'bg-red-50 text-red-700') }}">
                        {{ $course->difficulty === 'beginner' ? '初級' : ($course->difficulty === 'intermediate' ? '中級' : '上級') }}
                    </span>
                    @if($course->category)
                        <span class="inline-flex items-center rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-700">{{ $course->category->name }}</span>
                    @endif
                </div>
                @if($course->tags->isNotEmpty())
                    <div class="flex flex-wrap gap-2 mb-4">
                        @foreach($course->tags as $tag)
                            <span class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700">{{ $tag->name }}</span>
                        @endforeach
                    </div>
                @endif
            </div>

            @if(auth()->user()->isStudent())
                @if($enrollment)
                    <span class="inline-flex items-center rounded-full bg-green-50 px-3 py-1.5 text-sm font-medium text-green-700">
                        <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        受講中
                    </span>
                @else
                    <form method="POST" action="{{ route('courses.enroll', $course) }}">
                        @csrf
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg px-5 py-2.5 shadow-sm transition-all duration-150">受講登録</button>
                    </form>
                @endif
            @endif
        </div>

        <p class="text-gray-700 leading-relaxed">{{ $course->description }}</p>
    </div>

    {{-- チャプター一覧 --}}
    <div class="space-y-4">
        @foreach($course->chapters->sortBy('order') as $chapter)
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
                <h2 class="text-lg font-semibold text-gray-900 mb-3">
                    <span class="text-indigo-600 mr-1">{{ $chapter->order }}.</span> {{ $chapter->title }}
                </h2>
                <ul class="space-y-1">
                    @foreach($chapter->lessons->where('is_published', true)->sortBy('order') as $lesson)
                        <li class="flex items-center justify-between py-2.5 px-3 hover:bg-gray-50 rounded-lg transition-colors">
                            <a href="{{ route('courses.lessons.show', [$course, $lesson]) }}" class="text-indigo-600 hover:text-indigo-700 font-medium text-sm">
                                {{ $lesson->order }}. {{ $lesson->title }}
                            </a>
                            @if($lesson->quiz)
                                <a href="{{ route('courses.quizzes.show', [$course, $lesson->quiz]) }}" class="inline-flex items-center rounded-full bg-indigo-50 px-2.5 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100 transition-colors">小テスト</a>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    @if($canViewReviews)
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 mt-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">レビュー（{{ $reviews->count() }}件）</h2>

            @forelse($reviews as $review)
                <div class="border-b border-gray-100 py-4 last:border-b-0">
                    <div class="flex items-center gap-2 mb-1">
                        <span class="text-yellow-500 text-sm tracking-tight">
                            @for($i = 1; $i <= 5; $i++)
                                {{ $i <= $review->rating ? '★' : '☆' }}
                            @endfor
                        </span>
                        <span class="text-sm text-gray-500">{{ $review->user->name }}</span>
                    </div>
                    @if($review->comment)
                        <p class="text-gray-700 text-sm">{{ $review->comment }}</p>
                    @endif
                </div>
            @empty
                <p class="text-gray-500 text-sm">まだレビューがありません。</p>
            @endforelse

            @if($canReview)
                <form method="POST" action="{{ route('courses.reviews.store', $course) }}"
                      class="mt-6 pt-6 border-t border-gray-200"
                      x-data="{ submitting: false }" @submit="submitting = true">
                    @csrf
                    <h3 class="text-md font-semibold text-gray-900 mb-3">レビューを投稿</h3>
                    <div class="mb-4">
                        <label for="rating" class="block text-sm font-medium text-gray-700 mb-1">評価</label>
                        <select name="rating" id="rating" class="w-32 rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">
                            @for($i = 5; $i >= 1; $i--)
                                <option value="{{ $i }}" {{ old('rating') == $i ? 'selected' : '' }}>{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                    <div class="mb-4">
                        <label for="comment" class="block text-sm font-medium text-gray-700 mb-1">コメント（任意）</label>
                        <textarea name="comment" id="comment" rows="3"
                                  class="w-full rounded-lg border-gray-300 text-sm focus:border-indigo-500 focus:ring-indigo-500">{{ old('comment') }}</textarea>
                    </div>
                    <button type="submit" :disabled="submitting"
                            :class="submitting ? 'opacity-50 cursor-not-allowed' : ''"
                            class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg px-5 py-2.5 shadow-sm transition-all duration-150">
                        <span x-show="!submitting">投稿する</span>
                        <span x-show="submitting">投稿中...</span>
                    </button>
                </form>
            @endif
        </div>
    @endif
</div>
@endsection
