/**
 * 株価予測AI - メインJavaScript
 */

// ===== 検索サジェスト =====
(function () {
  const input = document.getElementById('searchInput');
  if (!input) return;

  let timer = null;
  let suggBox = null;

  function createSuggBox() {
    const box = document.createElement('div');
    box.id = 'suggBox';
    box.style.cssText = `
      position:absolute; top:100%; left:0; right:0;
      background:#1e2330; border:1px solid rgba(245,197,24,.3);
      border-radius:8px; margin-top:4px; z-index:200;
      max-height:300px; overflow-y:auto; box-shadow:0 8px 32px rgba(0,0,0,.5);
    `;
    input.parentElement.style.position = 'relative';
    input.parentElement.appendChild(box);
    return box;
  }

  function hideSugg() {
    if (suggBox) suggBox.innerHTML = '';
  }

  input.addEventListener('input', function () {
    clearTimeout(timer);
    const q = this.value.trim();
    if (q.length < 1) { hideSugg(); return; }

    timer = setTimeout(async () => {
      try {
        const res = await fetch(`/api/suggest.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        if (!suggBox) suggBox = createSuggBox();
        if (!data.length) { hideSugg(); return; }

        suggBox.innerHTML = data.map(item => `
          <a href="/detail.php?ticker=${encodeURIComponent(item.ticker)}"
             style="display:flex;align-items:center;gap:12px;padding:10px 16px;
                    color:#e8eaf0;border-bottom:1px solid rgba(255,255,255,.06);
                    transition:background .15s;"
             onmouseover="this.style.background='#252c3d'"
             onmouseout="this.style.background=''"
          >
            <span style="font-size:.78rem;color:#8b93a8;min-width:60px;">${item.ticker}</span>
            <span style="font-weight:600;">${item.company_name}</span>
            <span style="font-size:.72rem;color:#555e72;margin-left:auto;">${item.sector ?? ''}</span>
          </a>
        `).join('');
      } catch (e) { /* silent */ }
    }, 250);
  });

  document.addEventListener('click', function (e) {
    if (!input.contains(e.target)) hideSugg();
  });
})();

// ===== 確率バーのアニメーション =====
document.addEventListener('DOMContentLoaded', function () {
  const fills = document.querySelectorAll('.prob-bar-fill');
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        const el = entry.target;
        const target = el.dataset.width || '0%';
        setTimeout(() => { el.style.width = target; }, 100);
        observer.unobserve(el);
      }
    });
  }, { threshold: 0.1 });

  fills.forEach(el => {
    const w = el.style.width;
    el.dataset.width = w;
    el.style.width = '0%';
    observer.observe(el);
  });
});

// ===== 行クリックで詳細ページへ =====
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-href]').forEach(el => {
    el.addEventListener('click', function () {
      window.location.href = this.dataset.href;
    });
  });
});
