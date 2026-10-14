@extends('layout')
@section('content')
    <h1>案件一覧</h1>
    <p class="muted">案件ごとに修正依頼と対応状況を確認できます。</p>
    <section class="card">
        <h2>案件を登録</h2>
        <form method="post" action="{{ route('projects.store') }}">@csrf
            <label for="name">案件名（必須）</label><input id="name" name="name" maxlength="100" value="{{ old('name') }}"
                placeholder="例：会社サイトのリニューアル" required>
            <label for="description">説明</label>
            <textarea id="description" name="description" maxlength="2000" rows="3">{{ old('description') }}</textarea><button>案件を登録</button>
        </form>
    </section>
    <section class="card">
        <h2>登録済みの案件</h2>
        <form method="get" action="{{ route('projects.index') }}">
            <label for="project-search">案件名で検索</label>
            <input id="project-search" name="search" maxlength="100" value="{{ $search }}" placeholder="案件名の一部を入力">
            <button>検索</button>
            @if ($search !== '')
                <a href="{{ route('projects.index') }}">検索をクリア</a>
            @endif
        </form>
        @forelse($projects as $project)
            <a class="project" href="{{ route('projects.show', $project) }}"><strong>{{ $project->name }}</strong><span
                class="muted">修正依頼 {{ $project->revision_requests_count }}件 →</span></a>@empty<p class="muted">
                {{ $search !== '' ? '検索条件に一致する案件がありません。' : 'まだ案件がありません。最初の案件を登録しましょう。' }}</p>
        @endforelse{{ $projects->links('pagination') }}
    </section>
@endsection
