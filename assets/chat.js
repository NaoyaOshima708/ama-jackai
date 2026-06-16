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
  const modeConsult = document.getElementById("mode-consult");
  const messagesEl = document.getElementById("messages");
  const form = document.getElementById("chat-form");
  const input = document.getElementById("message-input");
  const sendBtn = document.getElementById("send-btn");
  const fileInput = document.getElementById("image-input");
  const imagePreview = document.getElementById("image-preview");
  const clearImgBtn = document.getElementById("clear-image");
  const imageRow = document.getElementById("image-row");

  let mode = "purchase";
  let conversation = [];
  let pendingImageBase64 = null;
  let pendingImageMime = "image/jpeg";
  /** 同一商品のグラフ画像を追加質問まで保持 */
  let sessionPurchaseImage = null;

  const ASSISTANT_NAME = "アマニャック";
  const ASSISTANT_ICON = "/assets/amanyack-icon.png";

  function removeWelcomeMessages() {
    const nodes = messagesEl.querySelectorAll(".msg.welcome");
    nodes.forEach((n) => n.remove());
  }

  function welcomeTextForMode(m) {
    if (m === "consult") {
      return (
        "Ama-Jackの使い方の相談ですね？\n" +
        "困っている画面・エラー文・やりたいことを送ってください。\n" +
        "画像があればアップロードしてください。"
      );
    }
    return (
      "商品の仕入れ判断ですか？\n" +
      "商品画像やAma-Jackでの検索画像（グラフ）があればアップロードしてください。\n" +
      "ない場合は、商品詳細（ASIN、価格、新品/中古、仕入れ値）を送ってください。"
    );
  }

  function showWelcomeIfEmpty() {
    if (conversation.length !== 0) return;
    clearEmptyHint();
    removeWelcomeMessages();
    appendMessage("assistant", welcomeTextForMode(mode));
    const last = messagesEl.lastElementChild;
    if (last) last.classList.add("welcome");
  }

  function setMode(m) {
    mode = m;
    modePurchase.classList.toggle("active", m === "purchase");
    modeConsult.classList.toggle("active", m === "consult");
    imageRow.style.display = m === "purchase" ? "flex" : "none";
    if (m !== "purchase") {
      pendingImageBase64 = null;
      pendingImageMime = "image/jpeg";
      sessionPurchaseImage = null;
      fileInput.value = "";
      imagePreview.classList.remove("visible");
      imagePreview.removeAttribute("src");
    }

    // まだ会話が始まっていない場合は、アマニャックから最初に案内する
    showWelcomeIfEmpty();
  }

  modePurchase.addEventListener("click", () => setMode("purchase"));
  modeConsult.addEventListener("click", () => setMode("consult"));

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
  }

  function clearEmptyHint() {
    const empty = messagesEl.querySelector(".empty-hint");
    if (empty) empty.remove();
  }

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
      pendingImageMime = f.type || "image/jpeg";
      imagePreview.src = dataUrl;
      imagePreview.classList.add("visible");
    };
    reader.readAsDataURL(f);
  });

  clearImgBtn.addEventListener("click", function () {
    pendingImageBase64 = null;
    pendingImageMime = "image/jpeg";
    sessionPurchaseImage = null;
    fileInput.value = "";
    imagePreview.classList.remove("visible");
    imagePreview.removeAttribute("src");
  });

  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    const text = input.value.trim();
    const hasImage =
      pendingImageBase64 ||
      (sessionPurchaseImage && sessionPurchaseImage.base64);
    if (mode === "consult" && !text) return;
    if (mode === "purchase" && !text && !hasImage) return;

    clearEmptyHint();
    const userDisplay = text || (hasImage ? "（グラフ画像付き）" : "");
    appendMessage("user", userDisplay);

    const userContent =
      text ||
      (mode === "purchase" && hasImage
        ? "グラフ画面のスクリーンショットを送りました。仕入れ判断フォーマットで判定してください。"
        : "");

    conversation.push({ role: "user", content: userContent });

    const payload = {
      mode: mode,
      session_id: getSessionId(),
      messages: conversation.map(function (m) {
        return { role: m.role, content: m.content };
      }),
    };

    if (mode === "purchase") {
      const imageBase64 =
        pendingImageBase64 ||
        (sessionPurchaseImage && sessionPurchaseImage.base64);
      const imageMime =
        pendingImageMime ||
        (sessionPurchaseImage && sessionPurchaseImage.mime) ||
        "image/jpeg";
      if (imageBase64) {
        payload.image = imageBase64;
        payload.image_mime = imageMime;
        if (pendingImageBase64) {
          sessionPurchaseImage = {
            base64: pendingImageBase64,
            mime: pendingImageMime,
          };
        }
      }
    }

    input.value = "";
    const savedImg = pendingImageBase64;
    const savedSessionImg = sessionPurchaseImage;
    pendingImageBase64 = null;
    fileInput.value = "";
    imagePreview.classList.remove("visible");
    imagePreview.removeAttribute("src");

    sendBtn.disabled = true;
    const loading = document.createElement("div");
    loading.className = "msg assistant loading";
    const loadingText =
      mode === "purchase" && hasImage ? "画像を読み取り中…" : "考え中…";
    loading.innerHTML =
      '<img class="msg-avatar" src="' +
      ASSISTANT_ICON +
      '" alt="' +
      ASSISTANT_NAME +
      '" width="40" height="40">' +
      '<div class="msg-body"><div class="label">' +
      ASSISTANT_NAME +
      '</div><div class="msg-text"><span class="spinner"></span>' +
      loadingText +
      "</div></div>";
    messagesEl.appendChild(loading);
    messagesEl.scrollTop = messagesEl.scrollHeight;

    try {
      const res = await fetch("/api/chat.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload),
      });
      const data = await res.json();
      loading.remove();

      if (!res.ok || data.error) {
        appendMessage("assistant", "エラー: " + (data.error || "送信に失敗しました。"));
        conversation.pop();
        if (savedImg) pendingImageBase64 = savedImg;
        if (!sessionPurchaseImage && savedSessionImg) sessionPurchaseImage = savedSessionImg;
        return;
      }

      appendMessage("assistant", data.reply, {
        image_reading: data.image_reading || null,
      });
      conversation.push({ role: "assistant", content: data.reply });
    } catch (err) {
      loading.remove();
      appendMessage("assistant", "エラー: 通信に失敗しました。");
      conversation.pop();
      if (savedImg) pendingImageBase64 = savedImg;
      if (!sessionPurchaseImage && savedSessionImg) sessionPurchaseImage = savedSessionImg;
    } finally {
      sendBtn.disabled = false;
      input.focus();
    }
  });

  setMode("purchase");
})();
