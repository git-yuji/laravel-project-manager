@extends('layout')
@section('content')
    <div class="page-intro">
        <p class="eyebrow">PROJECTS</p>
        <h1>案件一覧</h1>
        <p class="muted">案件ごとに修正依頼と対応状況を確認できます。</p>
    </div>
    <details class="card create-panel" @if ($errors->has('name') || $errors->has('description')) open @endif>
        <summary>新しい案件を登録</summary>
        <form method="post" action="{{ route('projects.store') }}">@csrf
            <label for="name">案件名（必須）</label><input id="name" name="name" maxlength="100"
                value="{{ is_string(old('name')) ? old('name') : '' }}" placeholder="例：会社サイトのリニューアル" required>
            <label for="description">説明</label>
            <textarea id="description" name="description" maxlength="2000" rows="3">{{ is_string(old('description')) ? old('description') : '' }}</textarea><button>案件を登録</button>
        </form>
    </details>
    <section class="card">
        <div class="list-heading">
            <h2>登録済みの案件</h2>
            <span class="count">{{ $projects->total() }} 件{{ $search !== '' ? 'の検索結果' : '' }}</span>
        </div>
        <form class="search-form" method="get" action="{{ route('projects.index') }}">
            <div class="search-field">
                <label for="project-search">案件名で検索</label>
                <input id="project-search" name="search" maxlength="100" value="{{ $search }}"
                    placeholder="案件名の一部を入力">
            </div>
            <button>検索</button>
            @if ($search !== '')
                <a href="{{ route('projects.index') }}">検索をクリア</a>
            @endif
        </form>
        @forelse($projects as $project)
            <a class="project" href="{{ route('projects.show', $project) }}"><strong>{{ $project->name }}</strong><span
                class="muted">修正依頼 {{ $project->revision_requests_count }}件 →</span></a>@empty<p class="empty-state">
                {{ $search !== '' ? '検索条件に一致する案件がありません。' : 'まだ案件がありません。最初の案件を登録しましょう。' }}</p>
        @endforelse{{ $projects->links('pagination') }}
    </section>
@endsection
