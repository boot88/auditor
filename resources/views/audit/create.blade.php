<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Аудит сайта</title>
  <style>
    body{font-family:system-ui,Segoe UI,Arial;max-width:900px;margin:40px auto;padding:0 16px}
    .card{border:1px solid #e5e7eb;border-radius:14px;padding:18px}
    input{width:100%;padding:12px;border-radius:10px;border:1px solid #cbd5e1}
    button{padding:12px 16px;border-radius:10px;border:0;background:#111827;color:#fff;cursor:pointer}
    .row{display:flex;gap:12px;align-items:center}
    .muted{color:#64748b}
    .err{color:#b91c1c}
  </style>
</head>
<body>
  <h1>Онлайн-аудит сайта</h1>
  <p class="muted">Вставьте адрес сайта — получите рекомендации по SEO, контенту, UX, безопасности и маркетингу.</p>

  <div class="card">
    <form method="POST" action="{{ route('audit.store') }}">
      @csrf
      <label>URL сайта</label>
      <div class="row" style="margin-top:8px">
        <input name="url" placeholder="https://example.com" value="{{ old('url') }}" required>
        <button type="submit">Проверить</button>
      </div>
      @error('url')
        <p class="err">{{ $message }}</p>
      @enderror
      <p class="muted" style="margin-top:10px;font-size:14px">
        Мы анализируем только публично доступные страницы. Сайты с капчей/жёсткой защитой могут не загрузиться.
      </p>
    </form>
  </div>
</body>
</html>
