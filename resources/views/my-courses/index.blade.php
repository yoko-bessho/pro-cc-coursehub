@extends('layouts.app')

@section('content')
<div>
    <div class="mb-8">
        <h1 class="text-2xl font-bold text-gray-900">マイコース</h1>
        <p class="mt-1 text-sm text-gray-500">受講中のコースと進捗を確認できます</p>
    </div>

    @if($enrollments->isEmpty())
        <div class="flex flex-col items-center justify-center py-16">
            <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.26 10.147a60.436 60.436 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.57 50.57 0 00-2.658-.813A59.905 59.905 0 0112 3.493a59.902 59.902 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.697 50.697 0 0112 13.489a50.702 50.702 0 017.74-3.342"/></svg>
            <p class="text-gray-500 mb-4">まだコースに登録していません。</p>
            <a href="{{ route('courses.index') }}" class="bg-indigo-600 hover:bg-indigo-700 text-white font-medium rounded-lg px-4 py-2.5 shadow-sm transition-all duration-150">コース一覧を見る</a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($enrollments as $enrollment)
                @php
                    $course = $enrollment->course;
                    $progressRate = $progressRates[$course->id] ?? 0;
                @endphp
                <a href="{{ route('courses.show', $course) }}" class="block bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow duration-200 p-6">
                    <div class="flex items-center justify-between mb-3">
                        <div class="flex-1 min-w-0">
                            <h2 class="text-lg font-semibold text-gray-900 truncate">{{ $course->title }}</h2>
                            <div class="flex flex-wrap items-center gap-3 mt-1 text-sm text-gray-500">
                                <span>by {{ $course->user->name ?? '不明' }}</span>
                                <span>受講開始: {{ $enrollment->enrolled_at->format('Y/m/d') }}</span>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium
                                    {{ $enrollment->status === 'active' ? 'bg-blue-50 text-blue-700' : 'bg-green-50 text-green-700' }}">
                                    {{ $enrollment->status === 'active' ? '受講中' : '完了' }}
                                </span>
                            </div>
                        </div>
                        <div class="text-right ml-4 flex-shrink-0">
                            <p class="text-3xl font-bold text-indigo-600">{{ $progressRate }}%</p>
                            <p class="text-xs text-gray-500 mt-0.5">進捗率</p>
                        </div>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-2">
                        <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500" style="width: {{ $progressRate }}%"></div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endsection
