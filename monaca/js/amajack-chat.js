(function () {
  // ===== 設定 =====
  var API_BASE   = 'https://amajackai.maspis.com/api';
  var AVATAR_SRC = 'https://amajackai.maspis.com/assets/amanyack-icon.png';
  var ASSISTANT  = 'アマニャック';

  var CONDITIONS = [
    { value: '新品',           label: '新品',       icon: '✨', sub: '新品' },
    { value: '中古-ほぼ新品',  label: 'ほぼ新品',   icon: '🟢', sub: '中古' },
    { value: '中古-非常に良い',label: '非常に良い', icon: '🔵', sub: '中古' },
    { value: '中古-良い',      label: '良い',       icon: '🟡', sub: '中古' },
    { value: '中古-可',        label: '可',         icon: '🟠', sub: '中古' },
  ];

  var PROGRESS_STEPS = [
    '商品データを解析中…',
    '利益・競合スコアを計算中…',
    'AIが仕入れ判断を作成中…',
    '回答を整形中…',
  ];

  // ===== デバイスID =====
  function getDeviceId() {
    var k = 'amajack_device_id';
    var id = localStorage.getItem(k);
    if (!id) {
      id = 'dev-' + Date.now() + '-' + Math.random().toString(36).slice(2, 10);
      localStorage.setItem(k, id);
    }
    return id;
  }
  var deviceId = getDeviceId();

  // ===== 状態 =====
  var purchaseStep      = 'asin';
  var loadedProductData = null;
  var loadedProductName = '';
  var loadedBuyPrice    = 0;
  var loadedCondition   = '新品';
  var currentSessionId  = 0;
  var conversation      = [];

  // ===== DOM =====
  var elChatMessages  = document.getElementById('aj-chat-messages');
  var elChatTitle     = document.getElementById('aj-chat-title');
  var elChatForm      = document.getElementById('aj-chat-form');
  var elInput         = document.getElementById('aj-input');
  var elBtnSend       = document.getElementById('aj-btn-send');
  var elDrawer        = document.getElementById('aj-drawer');
  var elOverlay       = document.getElementById('aj-overlay');
  var elHistoryList   = document.getElementById('aj-history-list');
  var elHistoryEmpty  = document.getElementById('aj-history-empty');

  // ===== ドロワー =====
  function openDrawer() {
    elDrawer.classList.add('open');
    elOverlay.classList.add('open');
    loadHistory();
  }
  function closeDrawer() {
    elDrawer.classList.remove('open');
    elOverlay.classList.remove('open');
  }

  document.getElementById('aj-btn-menu').addEventListener('click', openDrawer);
  elOverlay.addEventListener('click', closeDrawer);
  document.getElementById('aj-btn-new-header').addEventListener('click', function () {
    startNewChat();
  });
  document.getElementById('aj-btn-new-drawer').addEventListener('click', function () {
    closeDrawer();
    startNewChat();
  });

  // ===== 新規チャット =====
  function startNewChat() {
    purchaseStep      = 'asin';
    loadedProductData = null;
    loadedProductName = '';
    loadedBuyPrice    = 0;
    loadedCondition   = '新品';
    currentSessionId  = 0;
    conversation      = [];
    elChatMessages.innerHTML = '';
    elChatTitle.textContent  = '仕入れ判断AI';
    elChatForm.style.display = '';
    setPlaceholder('ASINを入力してください（例: B0C1MC3SL9）');
    appendHint('商品のASINを入力してください。\n（例: B0C1MC3SL9）');
  }

  function setPlaceholder(txt) { elInput.placeholder = txt; }

  function appendHint(text) {
    var d = document.createElement('div');
    d.className = 'aj-chat-hint';
    d.textContent = text;
    elChatMessages.appendChild(d);
  }

  // ===== 履歴一覧 =====
  function loadHistory() {
    fetch(API_BASE + '/history_list.php?device_id=' + encodeURIComponent(deviceId))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        elHistoryList.querySelectorAll('.aj-session-item').forEach(function (el) { el.remove(); });
        if (!data.sessions || data.sessions.length === 0) {
          elHistoryEmpty.style.display = '';
          return;
        }
        elHistoryEmpty.style.display = 'none';
        data.sessions.forEach(function (s) {
          elHistoryList.appendChild(buildSessionItem(s));
        });
      })
      .catch(function () {});
  }

  function buildSessionItem(s) {
    var date = new Date(s.updated_at).toLocaleDateString('ja-JP', {
      month: 'numeric', day: 'numeric', hour: '2-digit', minute: '2-digit'
    });
    var meta = [s.condition_type, s.buy_price ? s.buy_price.toLocaleString() + '円' : '']
      .filter(Boolean).join(' / ');

    var item = document.createElement('div');
    item.className = 'aj-session-item';
    item.innerHTML =
      '<div class="aj-session-icon">📦</div>' +
      '<div class="aj-session-body">' +
        '<div class="aj-session-title">' + esc(s.title || s.product_name || s.asin || '不明') + '</div>' +
        '<div class="aj-session-meta">' + esc(meta ? meta + ' · ' + date : date) + '</div>' +
        (s.last_reply ? '<div class="aj-session-preview">' + esc(s.last_reply) + '</div>' : '') +
      '</div>' +
      '<button class="aj-btn-delete" type="button">削除</button>';

    item.querySelector('.aj-btn-delete').addEventListener('click', function (e) {
      e.stopPropagation();
      if (!confirm('このチャットを削除しますか？')) return;
      fetch(API_BASE + '/history_delete.php', {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ device_id: deviceId, session_id: s.id })
      }).then(function () {
        item.remove();
        if (!elHistoryList.querySelector('.aj-session-item')) elHistoryEmpty.style.display = '';
      });
    });

    item.addEventListener('click', function () {
      closeDrawer();
      resumeSession(s.id);
    });
    return item;
  }

  // ===== セッション再開 =====
  function resumeSession(sid) {
    fetch(API_BASE + '/history_detail.php?device_id=' + encodeURIComponent(deviceId) + '&session_id=' + sid)
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data.ok) return;
        purchaseStep      = 'chat';
        loadedProductName = data.session.product_name || '';
        loadedCondition   = data.session.condition_type || '新品';
        loadedBuyPrice    = data.session.buy_price || 0;
        currentSessionId  = data.session.id;
        conversation      = data.messages.map(function (m) {
          return { role: m.role, content: m.content };
        });
        elChatMessages.innerHTML = '';
        elChatTitle.textContent  = data.session.title || data.session.product_name || 'チャット';
        elChatForm.style.display = '';
        setPlaceholder('追加で質問があれば入力してください');
        data.messages.forEach(function (m) { appendBubble(m.role, m.content); });
        elChatMessages.scrollTop = elChatMessages.scrollHeight;
      });
  }

  // ===== メッセージ表示 =====
  function appendBubble(role, text, extra) {
    var hint = elChatMessages.querySelector('.aj-chat-hint');
    if (hint) hint.remove();

    var div = document.createElement('div');
    div.className = 'aj-msg ' + role;

    if (role === 'assistant') {
      div.innerHTML =
        '<img class="aj-avatar" src="' + AVATAR_SRC + '" alt="' + ASSISTANT + '">' +
        '<div class="aj-msg-body">' +
          '<div class="aj-msg-name">' + ASSISTANT + '</div>' +
          '<div class="aj-msg-text"></div>' +
        '</div>';
      div.querySelector('.aj-msg-text').textContent = text;
      if (extra && extra.score) {
        div.querySelector('.aj-msg-body').appendChild(buildScorePanel(extra.score));
      }
    } else {
      div.innerHTML = '<div class="aj-bubble"></div>';
      div.querySelector('.aj-bubble').textContent = text;
    }

    elChatMessages.appendChild(div);
    elChatMessages.scrollTop = elChatMessages.scrollHeight;
    return div;
  }

  // ===== スコアパネル =====
  function buildScorePanel(score) {
    var verdictMap = { buy: '🟢 買い', ok: '🔵 OK', caution: '🟡 慎重に', avoid: '🟠 非推奨', ng: '🔴 NG' };
    var rows = [
      ['商品名',      score.product_name || '-'],
      ['ランキング',  score.current_rank ? score.current_rank.toLocaleString() + '位' : '-'],
      ['売値',        score.sell_price   ? score.sell_price.toLocaleString() + '円' : '-'],
      ['仕入れ値',    score.buy_price    ? score.buy_price.toLocaleString() + '円' : '-'],
      ['粗利',        score.profit_amount != null ? score.profit_amount.toLocaleString() + '円（' + score.profit_rate + '%）' : '-'],
      ['損益分岐点',  score.breakeven_price ? score.breakeven_price.toLocaleString() + '円以下で赤字' : '-'],
      ['月間販売数',  score.monthly_sales != null ? score.monthly_sales + '個/月' : '-'],
      ['出品者数',    score.new_seller_count != null ? score.new_seller_count + '人' : '-'],
      ['Amazon出品',  score.amazon_selling ? '出品中⚠' : '非出品'],
      ['推奨仕入れ数',score.recommended_qty != null ? score.recommended_qty + '個' : '-'],
    ];
    var html = '<summary>📊 スコア詳細（総合 ' + (score.score_total || 0) + '点 / ' + (verdictMap[score.verdict] || '') + '）</summary>'
             + '<table class="aj-score-table">';
    rows.forEach(function (r) {
      html += '<tr><th>' + esc(r[0]) + '</th><td>' + esc(String(r[1])) + '</td></tr>';
    });
    html += '</table>';
    var details = document.createElement('details');
    details.className = 'aj-score-panel';
    details.innerHTML = html;
    return details;
  }

  // ===== ローディング =====
  function showLoading(msg) {
    elBtnSend.disabled = true;
    var div = document.createElement('div');
    div.className = 'aj-msg assistant loading';
    div.innerHTML =
      '<img class="aj-avatar" src="' + AVATAR_SRC + '" alt="' + ASSISTANT + '">' +
      '<div class="aj-msg-body"><div class="aj-msg-name">' + ASSISTANT + '</div>' +
      '<div class="aj-msg-text"><span class="aj-spinner"></span> ' + msg + '</div></div>';
    elChatMessages.appendChild(div);
    elChatMessages.scrollTop = elChatMessages.scrollHeight;
    return div;
  }

  function showProgress() {
    elBtnSend.disabled = true;
    var div = document.createElement('div');
    div.className = 'aj-msg assistant loading';
    var stepsHtml = PROGRESS_STEPS.map(function (s, i) {
      return '<div class="aj-progress-step" id="aj-step-' + i + '"><span class="aj-step-dot"></span>' + s + '</div>';
    }).join('');
    div.innerHTML =
      '<img class="aj-avatar" src="' + AVATAR_SRC + '" alt="' + ASSISTANT + '">' +
      '<div class="aj-msg-body"><div class="aj-msg-name">' + ASSISTANT + '</div>' +
      '<div class="aj-msg-text aj-progress-wrap">' +
        '<div class="aj-progress-steps">' + stepsHtml + '</div>' +
        '<div class="aj-bar-track"><div class="aj-bar-fill" id="aj-bar-fill"></div></div>' +
      '</div></div>';
    elChatMessages.appendChild(div);
    elChatMessages.scrollTop = elChatMessages.scrollHeight;

    var step = 0;
    function advance() {
      if (step > 0) {
        var prev = div.querySelector('#aj-step-' + (step - 1));
        if (prev) { prev.classList.remove('active'); prev.classList.add('done'); }
      }
      var cur = div.querySelector('#aj-step-' + step);
      if (cur) cur.classList.add('active');
      var fill = div.querySelector('#aj-bar-fill');
      if (fill) fill.style.width = ((step + 1) / PROGRESS_STEPS.length * 85) + '%';
      step++;
    }
    advance();
    var t1 = setTimeout(advance, 3000);
    var t2 = setTimeout(advance, 8000);
    var t3 = setTimeout(advance, 15000);
    div._clear = function () { clearTimeout(t1); clearTimeout(t2); clearTimeout(t3); };
    return div;
  }

  // ===== ASIN取得 =====
  function handleAsin(asin) {
    appendBubble('user', asin);
    var loading = showLoading('商品データを取得中…');
    fetch(API_BASE + '/fetch_product.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ asin: asin })
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      loading.remove(); elBtnSend.disabled = false;
      if (!data.ok) { appendBubble('assistant', 'エラー: ' + (data.error || '商品データの取得に失敗しました。')); return; }
      loadedProductData = data.product_data;
      loadedProductName = data.product_name || asin;
      elChatTitle.textContent = loadedProductName;
      var msg =
        '✅ 商品を取得しました！\n📦 ' + loadedProductName + '\n' +
        (data.rank       ? '📊 現在ランキング ' + data.rank.toLocaleString() + '位\n' : '') +
        (data.new_price  ? '🆕 新品最安値: ' + data.new_price.toLocaleString() + '円\n' : '') +
        (data.used_price ? '♻️ 中古最安値: ' + data.used_price.toLocaleString() + '円\n' : '') +
        '\n仕入れ値はいくらですか？（円で入力してください）\n例: 3500';
      appendBubble('assistant', msg);
      purchaseStep = 'buy_price';
      setPlaceholder('仕入れ値を入力してください（例: 3500）');
    })
    .catch(function () { loading.remove(); elBtnSend.disabled = false; appendBubble('assistant', 'エラー: 通信に失敗しました。'); });
  }

  // ===== 仕入れ値 =====
  function handleBuyPrice(text) {
    var price = parseInt(text.replace(/[,，円¥]/g, ''), 10);
    if (isNaN(price) || price <= 0) {
      appendBubble('user', text);
      appendBubble('assistant', '数字で入力してください。\n例: 3500');
      return;
    }
    loadedBuyPrice = price;
    appendBubble('user', price.toLocaleString() + '円');
    purchaseStep = 'condition';
    elChatForm.style.display = 'none';
    appendBubble('assistant', '商品のコンディションを選んでください。');
    showConditionCards();
  }

  // ===== コンディション =====
  function showConditionCards() {
    var wrap = document.createElement('div');
    wrap.className = 'aj-cond-cards';
    CONDITIONS.forEach(function (c) {
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'aj-cond-card';
      btn.innerHTML =
        '<span class="aj-cond-icon">' + c.icon + '</span>' +
        '<span class="aj-cond-label">' + c.label + '</span>' +
        '<span class="aj-cond-sub">' + c.sub + '</span>';
      btn.addEventListener('click', function () { handleCondition(c.value, wrap); });
      wrap.appendChild(btn);
    });
    elChatMessages.appendChild(wrap);
    elChatMessages.scrollTop = elChatMessages.scrollHeight;
  }

  function handleCondition(val, cardsEl) {
    loadedCondition = val;
    cardsEl.remove();
    elChatForm.style.display = '';
    appendBubble('user', val);
    purchaseStep = 'chat';
    setPlaceholder('追加で質問があれば入力してください');
    var userContent = '仕入れ値 ' + loadedBuyPrice.toLocaleString() + '円、コンディション「' + val + '」で仕入れ判断をお願いします。';
    conversation.push({ role: 'user', content: userContent });
    sendToChat(userContent, true);
  }

  // ===== チャットAPI =====
  function sendToChat(userContent, isFirst) {
    var loading = isFirst ? showProgress() : showLoading('考え中…');
    var payload = {
      mode: 'purchase',
      buy_price: loadedBuyPrice,
      condition: loadedCondition,
      messages: conversation.map(function (m) { return { role: m.role, content: m.content }; })
    };
    if (loadedProductData) payload.product_data = loadedProductData;

    fetch(API_BASE + '/chat.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
      if (loading._clear) loading._clear();
      loading.remove(); elBtnSend.disabled = false;
      if (data.error) { appendBubble('assistant', 'エラー: ' + data.error); conversation.pop(); return; }
      appendBubble('assistant', data.reply, { score: data.score || null });
      conversation.push({ role: 'assistant', content: data.reply });
      saveSession();
    })
    .catch(function () {
      if (loading._clear) loading._clear();
      loading.remove(); elBtnSend.disabled = false;
      appendBubble('assistant', 'エラー: 通信に失敗しました。時間をおいて再試行してください。');
      conversation.pop();
    });
  }

  // ===== 履歴保存 =====
  function saveSession() {
    var payload = {
      device_id:    deviceId,
      asin:         (loadedProductData && loadedProductData.shiire_asin) || '',
      product_name: loadedProductName,
      condition:    loadedCondition,
      buy_price:    loadedBuyPrice || null,
      messages:     conversation
    };
    if (currentSessionId > 0) payload.session_id = currentSessionId;
    fetch(API_BASE + '/history_save.php', {
      method: 'POST', headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) { if (data.ok && data.session_id) currentSessionId = data.session_id; })
    .catch(function () {});
  }

  // ===== フォーム送信 =====
  elChatForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var text = elInput.value.trim();
    elInput.value = '';
    autoResize();
    if (!text) return;

    if (purchaseStep === 'asin') {
      var asin = text.toUpperCase().replace(/\s/g, '');
      if (!/^[A-Z0-9]{10}$/.test(asin)) {
        appendBubble('user', text);
        appendBubble('assistant', 'ASINの形式が正しくありません。\n10桁の英数字で入力してください。\n例: B0C1MC3SL9');
        return;
      }
      handleAsin(asin);
      return;
    }
    if (purchaseStep === 'buy_price') { handleBuyPrice(text); return; }

    appendBubble('user', text);
    conversation.push({ role: 'user', content: text });
    sendToChat(text, false);
  });

  function autoResize() {
    elInput.style.height = 'auto';
    elInput.style.height = Math.min(elInput.scrollHeight, 110) + 'px';
  }
  elInput.addEventListener('input', autoResize);

  function esc(s) {
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;')
      .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // ===== 初期表示 =====
  startNewChat();

})();
