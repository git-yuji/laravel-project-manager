<!DOCTYPE html>
<html lang="ja">

    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>案件・修正依頼管理</title>
        <link rel="stylesheet" href="{{ asset('app.css') }}">
    </head>

    <body>
        <header>
            <div class="container bar"><a href="{{ route('projects.index') }}">案件・修正依頼管理</a>@auth<form method="post"
                        action="{{ route('logout') }}">@csrf<span>{{ auth()->user()->name }}</span> <button
                        class="secondary">ログアウト</button></form>@endauth
            </div>
        </header>
        <main class="container">
            @if (session('success'))
                <p class="notice" role="status">{{ session('success') }}</p>
            @endif
            @if ($errors->any())
                <div class="errors" role="alert">
                    <p>入力内容を確認してください。</p>
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @yield('content')
        </main>
    </body>

</html>
