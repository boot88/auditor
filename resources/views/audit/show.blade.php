<!doctype html>
<html lang="ru">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Результат аудита #{{ $audit->id }}</title>
  <style>
    body{font-family:system-ui,Segoe UI,Arial;max-width:1000px;margin:40px auto;padding:0 16px}
    .card{border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin:14px 0}
    .muted{color:#64748b}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
    @media (max-width:800px){.grid{grid-template-columns:1fr}}
    .badge{display:inline-block;padding:4px 10px;border-radius:999px;background:#f1f5f9;color:#0f172a;font-size:12px}
    .ok{background:#dcfce7}
    .bad{background:#fee2e2}
    ul{margin:8px 0 0 18px}
    .score{font-size:34px;font-weight:700}
    .err{color:#b91c1c}
    a{color:#2563eb}
  </style>
</head>
<body>

  <a href="{{ route('audit.create') }}">← Новый аудит</a>

  <h1>Аудит сайта</h1>
  <p class="muted">Запрос: <strong>{{ $audit->url }}</strong></p>

  <div class="card" id="statusCard">
    <div class="badge" id="statusBadge">Статус: {{ $audit->status }}</div>
    <p class="muted" id="metaLine" style="margin-top:10px"></p>
    <p class="err" id="errorLine" style="display:none"></p>
  </div>

  <div id="resultRoot"></div>

<script>
const statusUrl = @json(route('audit.status', $audit));
const resultRoot = document.getElementById('resultRoot');
const statusBadge = document.getElementById('statusBadge');
const metaLine = document.getElementById('metaLine');
const errorLine = document.getElementById('errorLine');

function esc(s){ return (s||'').toString().replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m])); }

function sectionCard(title, sec){
  const issues = (sec.issues||[]).map(x=>`<li>${esc(x)}</li>`).join('');
  const recs = (sec.recommendations||[]).map(x=>`<li>${esc(x)}</li>`).join('');
  const notes = (sec.notes||[]).map(x=>`<li>${esc(x)}</li>`).join('');
  const extra = sec.what_is_this_site ? `<p><strong>Что это за сайт:</strong> ${esc(sec.what_is_this_site)}</p>` : '';
  const cta = sec.cta_ideas ? `<p><strong>Идеи CTA:</strong></p><ul>${(sec.cta_ideas||[]).map(x=>`<li>${esc(x)}</li>`).join('')}</ul>` : '';

  return `
    <div class="card">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px">
        <h2 style="margin:0">${esc(title)}</h2>
        <span class="badge">${esc(sec.score)} / 100</span>
      </div>
      ${extra}
      ${issues ? `<p><strong>Проблемы:</strong></p><ul>${issues}</ul>` : ''}
      ${recs ? `<p><strong>Рекомендации:</strong></p><ul>${recs}</ul>` : ''}
      ${notes ? `<p><strong>Примечания:</strong></p><ul>${notes}</ul>` : ''}
      ${cta}
    </div>
  `;
}

function renderResult(data){
  const s = data.sections || {};
  const quick = (data.quick_wins||[]).map(x=>`<li>${esc(x)}</li>`).join('');
  const limits = (data.risks_and_limits||[]).map(x=>`<li>${esc(x)}</li>`).join('');

  resultRoot.innerHTML = `
    <div class="card">
      <div class="grid">
        <div>
          <div class="score">${esc(data.score)} / 100</div>
          <div class="muted">Итоговая оценка</div>
        </div>
        <div>
          <p style="margin:0"><strong>Вывод:</strong> ${esc(data.summary)}</p>
        </div>
      </div>
    </div>

    <div class="card">
      <h2 style="margin-top:0">Быстрые улучшения (quick wins)</h2>
      <ul>${quick}</ul>
    </div>

    ${sectionCard('SEO', s.seo || {})}
    ${sectionCard('Контент', s.content || {})}
    ${sectionCard('Юзабилити', s.ux || {})}
    ${sectionCard('Безопасность', s.security || {})}
    ${sectionCard('Маркетинг', s.marketing || {})}

    <div class="card">
      <h2 style="margin-top:0">Ограничения анализа</h2>
      <ul>${limits}</ul>
    </div>
  `;
}

async function poll(){
  const r = await fetch(statusUrl, {headers:{'Accept':'application/json'}});
  const j = await r.json();

  statusBadge.textContent = 'Статус: ' + j.status;
  metaLine.textContent = [
    j.final_url ? ('Итоговый URL: ' + j.final_url) : null,
    j.http_status ? ('HTTP: ' + j.http_status) : null,
    j.render_ms ? ('Render: ' + j.render_ms + ' ms') : null,
    j.llm_ms ? ('LLM: ' + j.llm_ms + ' ms') : null,
  ].filter(Boolean).join(' • ');

  if (j.status === 'failed') {
    errorLine.style.display = '';
    errorLine.textContent = j.error || 'Ошибка';
    return;
  }

  if (j.status === 'done' && j.result) {
    renderResult(j.result);
    return;
  }

  setTimeout(poll, 1200);
}
poll();
</script>

</body>
</html>
