@extends('layout')
@section('content')
    <section class="card login">
        <h1>ログイン</h1>
        <p class="muted">案件と修正依頼をまとめて管理します。</p>
        <form method="post" action="{{ url('/login') }}">@csrf
            <label for="email">メールアドレス</label><input id="email" type="email" name="email" value="{{ old('email') }}"
                autocomplete="username" required autofocus>
            <label for="password">パスワード</label><input id="password" type="password" name="password"
                autocomplete="current-password" required>
            <button>ログイン</button>
        </form>
    </section>
@endsection
