@extends('layout')
@section('content')
    <a href="{{ route('projects.index') }}">
        ← 案件一覧</a>
    <h1>{{ $project->name }}</h1>
    @if ($project->description)
        <p class="pre muted">{{ $project->description }}</p>
    @endif
    <section class="card">
        <details @if ($errors->has('name') || $errors->has('description')) open @endif>
            <summary>案件情報を編集</summary>
            <form method="post" action="{{ route('projects.update', $project) }}">
                @csrf
                @method('PATCH')
                <label for="project-name">案件名（必須）</label>
                <input id="project-name" name="name" maxlength="100" value="{{ old('name', $project->name) }}" required>
                <label for="project-description">説明</label>
                <textarea id="project-description" name="description" maxlength="2000" rows="3">{{ old('description', $project->description) }}</textarea>
                <button>案件情報を保存</button>
            </form>
        </details>
    </section>
    <section class="card">
        <h2>修正依頼を追加</h2>
        <form method="post" action="{{ route('revisions.store', $project) }}">@csrf<label for="content">修正内容（必須）</label>
            <textarea id="content" name="content" maxlength="2000" rows="3" placeholder="例：トップページの見出しを変更する" required>{{ old('content') }}</textarea><button>修正依頼を追加</button>
        </form>
    </section>
    <section class="card">
        <h2>修正依頼</h2>
        <form class="status-form" method="get" action="{{ route('projects.show', $project) }}">
            <label for="filter-status">対応状況で絞り込み</label>
            <select id="filter-status" name="status">
                <option value="">すべて</option>
                @foreach (\App\Models\RevisionRequest::STATUSES as $value => $label)
                    <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="secondary">絞り込む</button>
        </form>
        @forelse($revisions as $revision)
            <article class="revision">
                <div class="bar"><span
                        class="badge {{ $revision->status }}">{{ \App\Models\RevisionRequest::STATUSES[$revision->status] }}</span><time
                        class="muted">{{ $revision->created_at->format('Y/m/d H:i') }}</time></div>
                <p class="pre">{{ $revision->content }}</p>
                <form class="status-form" method="post" action="{{ route('revisions.update', [$project, $revision]) }}">
                    <input type="hidden" name="filter_status" value="{{ $status }}">
                    @csrf @method('PATCH')<label for="status-{{ $revision->id }}">対応状況</label><select
                        id="status-{{ $revision->id }}" name="status">
                        @foreach (\App\Models\RevisionRequest::STATUSES as $value => $label)
                            <option value="{{ $value }}" @selected($revision->status === $value)>{{ $label }}</option>
                        @endforeach
                    </select><button class="secondary">更新</button>
                </form>
                <details>
                    <summary>対応状況の変更履歴（{{ $revision->statusHistories->count() }}件）</summary>
                    @forelse ($revision->statusHistories as $history)
                        <p>
                            <time>{{ $history->created_at->format('Y/m/d H:i:s') }}</time>
                            {{ $history->user?->name ?? '削除されたユーザー' }}：
                            {{ \App\Models\RevisionRequest::STATUSES[$history->from_status] }} →
                            {{ \App\Models\RevisionRequest::STATUSES[$history->to_status] }}
                        </p>
                    @empty
                        <p class="muted">変更履歴はまだありません。</p>
                    @endforelse
                </details>
        </article>@empty<p class="muted">{{ $status ? 'この対応状況の修正依頼はありません。' : 'まだ修正依頼がありません。' }}</p>
        @endforelse{{ $revisions->links('pagination') }}
    </section>
@endsection
