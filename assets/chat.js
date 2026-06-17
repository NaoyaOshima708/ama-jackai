(function () {
  function getSessionId() {
    var k = "sedori_session_id";
    try {
      var id = localStorage.getItem(k);
      if (!id && typeof crypto !== "undefined" && crypto.randomUUID) {
        id = crypto.randomUUID();
        localStorage.setItem(k, id);
      }
      return id || "";
    } catch (e) {
      return "";
    }
  }

  const modePurchase = document.getElementById("mode-purchase");
  const modeConsult  = document.getElementById("mode-consult");
  const messagesEl   = document.getElementById("messages");
  const form         = document.getElementById("chat-form");
  const input        = document.getElementById("message-input");
  const sendBtn      = document.getElementById("send-btn");
  const fileInput    = document.getElementById("image-input");
  const imagePreview = document.getElementById("image-preview");
  const clearImgBtn  = document.getElementById("clear-image");
  const imageRow     = document.getElementById("image-row");

  let mode = "purchase";
  let conversation = [];
  let pendingImageBase64 = null;
  let pendingImageMime   = "image/jpeg";
  let sessionPurchaseImage = null;

  // 仕入れ判断フロー用ステート
  // 'asin'      → ASINを待っている
  // 'buy_price' → 仕入れ値を待っている
  // 'condition' → コンディションを選択中
  // 'chat'      → 通常チャット中
  let purchaseStep = "asin";
  let loadedProductData = null;
  let loadedProductName = "";
  let loadedBuyPrice    = 0;
  let loadedCondition   = "新品";

  const CONDITIONS = [
    { value: "新品",          label: "新品",          icon: "✨" },
    { value: "中古-ほぼ新品",  label: "ほぼ新品",      icon: "🟢" },
    { value: "中古-非常に良い", label: "非常に良い",    icon: "🔵" },
    { value: "中古-良い",      label: "良い",          icon: "🟡" },
    { value: "中古-可",        label: "可",            icon: "🟠" },
  ];

  const ASSISTANT_NAME = "アマニャック";
  const ASSISTANT_ICON = "/assets/amanyack-icon.png";

  // --- ステートごとのプレースホルダーとガイド ---
  const PLACEHOLDER = {
    asin:      "ASINを入力してください（例: B0C1MC3SL9）",
    buy_price: "仕入れ値を入力してください（例: 3500）",
    condition: "",
    chat:      "追加で質問があれば入力してください",
    consult:   "困っていること・やりたいことを入力してください",
  };

  function updatePlaceholder() {
    if (mode === "consult") {
      input.placeholder = PLACEHOLDER.consult;
      form.style.display = "";
      return;
    }
    // conditionステップはカード選択なのでフォームを隠す
    if (purchaseStep === "condition") {
      form.style.display = "none";
    } else {
      form.style.display = "";
      input.placeholder = PLACEHOLDER[purchaseStep] || "";
    }
  }

  /** コンディション選択カードを表示する */
  function showConditionCards() {
    const wrap = document.createElement("div");
    wrap.className = "condition-cards";
    wrap.id = "condition-cards";

    CONDITIONS.forEach(function (c) {
      const btn = document.createElement("button");
      btn.type = "button";
      btn.className = "condition-card";
      btn.dataset.value = c.value;
      btn.innerHTML = '<span class="cond-icon">' + c.icon + '</span>'
                    + '<span class="cond-label">' + c.label + '</span>'
                    + (c.value !== "新品" ? '<span class="cond-sub">中古</span>' : '<span class="cond-sub">新品</span>');
      btn.addEventListener("click", function () {
        handleConditionSelect(c.value);
      });
      wrap.appendChild(btn);
    });

    messagesEl.appendChild(wrap);
    messagesEl.scrollTop = messagesEl.scrollHeight;
  }

  function removeConditionCards() {
    const el = document.getElementById("condition-cards");
    if (el) el.remove();
  }

  // --- メッセージ表示 ---
  function removeWelcomeMessages() {
    messagesEl.querySelectorAll(".msg.welcome").forEach((n) => n.remove());
  }

  function clearEmptyHint() {
    const empty = messagesEl.querySelector(".empty-hint");
    if (empty) empty.remove();
  }

  function appendMessage(role, text, extra) {
    const div = document.createElement("div");
    div.className = "msg " + role;

    if (role === "assistant") {
      const avatar = document.createElement("img");
      avatar.className = "msg-avatar";
      avatar.src = ASSISTANT_ICON;
      avatar.alt = ASSISTANT_NAME;
      avatar.width = 40;
      avatar.height = 40;

      const body = document.createElement("div");
      body.className = "msg-body";

      const label = document.createElement("div");
      label.className = "label";
      label.textContent = ASSISTANT_NAME;

      const content = document.createElement("div");
      content.className = "msg-text";
      content.textContent = text;

      body.appendChild(label);
      body.appendChild(content);

      if (extra && extra.image_reading) {
        const reading = document.createElement("details");
        reading.className = "image-reading";
        const summary = document.createElement("summary");
        summary.textContent = "📷 画像の読み取り結果を見る";
        const pre = document.createElement("pre");
        pre.textContent = extra.image_reading;
        reading.appendChild(summary);
        reading.appendChild(pre);
        body.appendChild(reading);
      }

      if (extra && extra.score) {
        body.appendChild(buildScorePanel(extra.score));
      }

      div.appendChild(avatar);
      div.appendChild(body);
    } else {
      const label = document.createElement("div");
      label.className = "label";
      label.textContent = "あなた";
      div.appendChild(label);
      div.appendChild(document.createTextNode(text));
    }

    messagesEl.appendChild(div);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    return div;
  }

  /** スコアパネル（折りたたみ） */
  function buildScorePanel(score) {
    const details = document.createElement("details");
    details.className = "score-panel";

    const summary = document.createElement("summary");
    const verdictMap = { buy: "🟢 買い", ok: "🔵 OK", caution: "🟡 慎重に", avoid: "🟠 非推奨", ng: "🔴 NG" };
    summary.textContent = "📊 スコア詳細を見る（総合 " + (score.score_total || 0) + "点 / " + (verdictMap[score.verdict] || score.verdict) + "）";
    details.appendChild(summary);

    const table = document.createElement("table");
    table.className = "score-table";
    const rows = [
      ["商品名", score.product_name || "-"],
      ["現在ランキング", score.current_rank ? score.current_rank + "位" : "-"],
      ["売値（新品最安）", score.sell_price ? score.sell_price.toLocaleString() + "円" : "-"],
      ["仕入れ値", score.buy_price ? score.buy_price.toLocaleString() + "円" : "-"],
      ["粗利", score.profit_amount != null ? score.profit_amount.toLocaleString() + "円（" + score.profit_rate + "%）" : "-"],
      ["損益分岐点", score.breakeven_price ? score.breakeven_price.toLocaleString() + "円以下で赤字" : "-"],
      ["月間販売数（推定）", score.monthly_sales != null ? score.monthly_sales + "個/月" : "-"],
      ["ランキング波頻度", { frequent: "頻繁", sometimes: "たまに", rare: "ほぼなし", unknown: "不明" }[score.rank_wave_frequency] || "-"],
      ["新品出品者数", score.new_seller_count != null ? score.new_seller_count + "人" : "-"],
      ["出品者トレンド", { increasing: "増加中⚠", stable: "安定", decreasing: "減少中" }[score.seller_trend] || "-"],
      ["Amazon本体出品", score.amazon_selling ? "出品中⚠" : "非出品"],
      ["価格トレンド", { rising: "上昇↑", stable: "安定", falling: "下落↓" }[score.price_trend] || "-"],
      ["推奨仕入れ数", score.recommended_qty != null ? score.recommended_qty + "個" : "-"],
    ];
    rows.forEach(function (r) {
      const tr = document.createElement("tr");
      const th = document.createElement("th");
      th.textContent = r[0];
      const td = document.createElement("td");
      td.textContent = r[1];
      tr.appendChild(th);
      tr.appendChild(td);
      table.appendChild(tr);
    });
    details.appendChild(table);
    return details;
  }

  // --- モード切替 ---
  function showWelcomeIfEmpty() {
    if (conversation.length !== 0) return;
    clearEmptyHint();
    removeWelcomeMessages();
    let welcome;
    if (mode === "consult") {
      welcome = "Ama-Jackの使い方の相談ですね？\n困っている画面・エラー文・やりたいことを送ってください。\n画像があればアップロードしてください。";
    } else {
      welcome = "仕入れ判断をします。\nまず仕入れたい商品のASINを入力してください。\n（例: B0C1MC3SL9）";
    }
    const el = appendMessage("assistant", welcome);
    el.classList.add("welcome");
  }

  function resetPurchaseFlow() {
    purchaseStep      = "asin";
    loadedProductData = null;
    loadedProductName = "";
    loadedBuyPrice    = 0;
    loadedCondition   = "新品";
    removeConditionCards();
  }

  function setMode(m) {
    mode = m;
    modePurchase.classList.toggle("active", m === "purchase");
    modeConsult.classList.toggle("active",  m === "consult");
    imageRow.style.display = m === "purchase" ? "flex" : "none";
    if (m !== "purchase") {
      pendingImageBase64 = null;
      pendingImageMime   = "image/jpeg";
      sessionPurchaseImage = null;
      fileInput.value    = "";
      imagePreview.classList.remove("visible");
      imagePreview.removeAttribute("src");
    }
    conversation = [];
    resetPurchaseFlow();
    removeWelcomeMessages();
    showWelcomeIfEmpty();
    updatePlaceholder();
  }

  modePurchase.addEventListener("click", () => setMode("purchase"));
  modeConsult.addEventListener("click",  () => setMode("consult"));

  // --- 画像 ---
  fileInput.addEventListener("change", function () {
    const f = fileInput.files && fileInput.files[0];
    if (!f) return;
    if (!/^image\/(jpeg|png|webp)$/i.test(f.type)) {
      alert("JPEG / PNG / WebP の画像を選んでください。");
      fileInput.value = "";
      return;
    }
    if (f.size > 8 * 1024 * 1024) {
      alert("8MB 以下の画像にしてください。");
      fileInput.value = "";
      return;
    }
    const reader = new FileReader();
    reader.onload = function () {
      const dataUrl = reader.result;
      const comma = String(dataUrl).indexOf(",");
      pendingImageBase64 = String(dataUrl).slice(comma + 1);
      pendingImageMime   = f.type || "image/jpeg";
      imagePreview.src   = dataUrl;
      imagePreview.classList.add("visible");
    };
    reader.readAsDataURL(f);
  });

  clearImgBtn.addEventListener("click", function () {
    pendingImageBase64 = null;
    pendingImageMime   = "image/jpeg";
    sessionPurchaseImage = null;
    fileInput.value    = "";
    imagePreview.classList.remove("visible");
    imagePreview.removeAttribute("src");
  });

  // --- ローディング表示 ---
  function showLoading(msg) {
    sendBtn.disabled = true;
    const loading = document.createElement("div");
    loading.className = "msg assistant loading";
    loading.innerHTML =
      '<img class="msg-avatar" src="' + ASSISTANT_ICON + '" alt="' + ASSISTANT_NAME + '" width="40" height="40">' +
      '<div class="msg-body"><div class="label">' + ASSISTANT_NAME + '</div>' +
      '<div class="msg-text"><span class="spinner"></span>' + msg + "</div></div>";
    messagesEl.appendChild(loading);
    messagesEl.scrollTop = messagesEl.scrollHeight;
    return loading;
  }

  // --- ASIN フェッチ ---
  async function handleAsinStep(asin) {
    appendMessage("user", asin);
    const loading = showLoading("商品データを取得中…");

    try {
      const res  = await fetch("/api/fetch_product.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify({ asin: asin }),
      });
      const data = await res.json();
      loading.remove();

      if (!res.ok || !data.ok) {
        appendMessage("assistant", "エラー: " + (data.error || "商品データの取得に失敗しました。"));
        return;
      }

      loadedProductData = data.product_data;
      loadedProductName = data.product_name || asin;

      const rankStr  = data.rank   ? "（現在ランキング " + data.rank.toLocaleString() + "位）" : "";
      const priceStr = data.new_price ? "新品最安値: " + data.new_price.toLocaleString() + "円" : "";
      const msg =
        "✅ 商品を取得しました！\n" +
        "📦 " + loadedProductName + "\n" +
        (priceStr ? priceStr + " " : "") + rankStr + "\n\n" +
        "仕入れ値はいくらですか？（円で入力してください）\n例: 3500";

      appendMessage("assistant", msg);
      purchaseStep = "buy_price";
      updatePlaceholder();

    } catch (err) {
      loading.remove();
      appendMessage("assistant", "エラー: 通信に失敗しました。");
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  }

  // --- 仕入れ値ステップ ---
  async function handleBuyPriceStep(text) {
    const price = parseInt(text.replace(/[,，円¥]/g, ""), 10);
    if (isNaN(price) || price <= 0) {
      appendMessage("user", text);
      appendMessage("assistant", "数字で入力してください。\n例: 3500");
      return;
    }

    loadedBuyPrice = price;
    appendMessage("user", price.toLocaleString() + "円");

    // → コンディション選択へ
    purchaseStep = "condition";
    updatePlaceholder();
    appendMessage("assistant", "商品のコンディションを選んでください。");
    showConditionCards();
  }

  // --- コンディション選択 ---
  async function handleConditionSelect(conditionValue) {
    loadedCondition = conditionValue;
    removeConditionCards();
    appendMessage("user", conditionValue);

    purchaseStep = "chat";
    updatePlaceholder();

    // 最初の判定リクエストを送る
    const userContent = "仕入れ値 " + loadedBuyPrice.toLocaleString() + "円、コンディション「" + conditionValue + "」で仕入れ判断をお願いします。";
    conversation.push({ role: "user", content: userContent });
    await sendToChatApi(userContent);
  }

  // --- チャットAPI呼び出し ---
  async function sendToChatApi(userContent) {
    const hasImage = pendingImageBase64 || (sessionPurchaseImage && sessionPurchaseImage.base64);
    const loadingMsg = hasImage ? "画像を読み取り中…" : "考え中…";
    const loading = showLoading(loadingMsg);

    const payload = {
      mode:       "purchase",
      session_id: getSessionId(),
      buy_price:  loadedBuyPrice,
      condition:  loadedCondition,
      messages:   conversation.map(function (m) { return { role: m.role, content: m.content }; }),
    };

    if (loadedProductData) {
      payload.product_data = loadedProductData;
    }

    const imageBase64 = pendingImageBase64 || (sessionPurchaseImage && sessionPurchaseImage.base64);
    const imageMime   = pendingImageMime   || (sessionPurchaseImage && sessionPurchaseImage.mime) || "image/jpeg";
    if (imageBase64) {
      payload.image      = imageBase64;
      payload.image_mime = imageMime;
      if (pendingImageBase64) {
        sessionPurchaseImage = { base64: pendingImageBase64, mime: pendingImageMime };
      }
    }

    pendingImageBase64 = null;
    fileInput.value    = "";
    imagePreview.classList.remove("visible");
    imagePreview.removeAttribute("src");

    try {
      const res  = await fetch("/api/chat.php", {
        method:  "POST",
        headers: { "Content-Type": "application/json" },
        body:    JSON.stringify(payload),
      });
      const data = await res.json();
      loading.remove();

      if (!res.ok || data.error) {
        appendMessage("assistant", "エラー: " + (data.error || "送信に失敗しました。"));
        conversation.pop();
        return;
      }

      appendMessage("assistant", data.reply, {
        image_reading: data.image_reading || null,
        score:         data.score         || null,
      });
      conversation.push({ role: "assistant", content: data.reply });

    } catch (err) {
      loading.remove();
      appendMessage("assistant", "エラー: 通信に失敗しました。");
      conversation.pop();
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  }

  // --- フォーム送信ハンドラ ---
  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    const text = input.value.trim();
    input.value = "";
    clearEmptyHint();

    // consult モード: そのままAPIへ
    if (mode === "consult") {
      if (!text) return;
      appendMessage("user", text);
      conversation.push({ role: "user", content: text });

      const loading = showLoading("考え中…");
      try {
        const res  = await fetch("/api/chat.php", {
          method:  "POST",
          headers: { "Content-Type": "application/json" },
          body:    JSON.stringify({
            mode:       "consult",
            session_id: getSessionId(),
            messages:   conversation.map(function (m) { return { role: m.role, content: m.content }; }),
          }),
        });
        const data = await res.json();
        loading.remove();
        if (!res.ok || data.error) {
          appendMessage("assistant", "エラー: " + (data.error || "送信に失敗しました。"));
          conversation.pop();
          return;
        }
        appendMessage("assistant", data.reply);
        conversation.push({ role: "assistant", content: data.reply });
      } catch (err) {
        loading.remove();
        appendMessage("assistant", "エラー: 通信に失敗しました。");
        conversation.pop();
      } finally {
        sendBtn.disabled = false;
        input.focus();
      }
      return;
    }

    // purchase モード: ステートマシン
    if (purchaseStep === "asin") {
      if (!text) return;
      // ASIN形式チェック（10文字英数字）
      const asin = text.toUpperCase().replace(/\s/g, "");
      if (!/^[A-Z0-9]{10}$/.test(asin)) {
        appendMessage("user", text);
        appendMessage("assistant", "ASINの形式が正しくありません。\n10桁の英数字で入力してください。\n例: B0C1MC3SL9");
        return;
      }
      await handleAsinStep(asin);
      return;
    }

    if (purchaseStep === "buy_price") {
      if (!text) return;
      await handleBuyPriceStep(text);
      return;
    }

    // chat ステート: 追加質問
    const hasImage = pendingImageBase64 || (sessionPurchaseImage && sessionPurchaseImage.base64);
    if (!text && !hasImage) return;

    const userDisplay  = text || "（画像付き）";
    const userContent  = text || "グラフ画面のスクリーンショットを送りました。内容を踏まえて追加コメントをしてください。";
    appendMessage("user", userDisplay);
    conversation.push({ role: "user", content: userContent });
    await sendToChatApi(userContent);
  });

  setMode("purchase");
})();
