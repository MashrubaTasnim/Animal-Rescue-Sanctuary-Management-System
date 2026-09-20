<?php
// If chatbot is disabled from Admin Settings, don't render anything
if (setting('chatbot_enabled', '1') !== '1') return;
?>

<!-- Font Awesome -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700&display=swap" rel="stylesheet">

<style>
    /* ===== TOGGLE BUTTON ===== */
    #chat-toggle {
        position: fixed;
        bottom: 28px;
        right: 28px;
        width: 62px;
        height: 62px;
        background: #0a1329;
        color: #ffd952;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        z-index: 10001;
        border: 2px solid rgba(252, 229, 79, 0.5);
        box-shadow: 0 4px 24px rgba(0,0,0,0.45), 0 0 0 4px rgba(255,217,82,0.08);
        transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1), box-shadow 0.3s ease;
    }
    #chat-toggle:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 30px rgba(0,0,0,0.5), 0 0 0 6px rgba(255,217,82,0.12);
    }
    #chat-toggle i { transition: transform 0.4s ease; }
    #chat-toggle:hover i { transform: rotate(15deg); }

    /* ===== CHAT CONTAINER ===== */
    #chat-container {
        position: fixed;
        bottom: 105px;
        right: 28px;
        width: 360px;
        height: 490px;
        max-height: calc(100vh - 175px);
        background: #0d1b3e;
        border-radius: 20px;
        display: none;
        flex-direction: column;
        box-shadow: 0 20px 60px rgba(0,0,0,0.55), 0 0 0 1px rgba(255,239,194,0.12);
        z-index: 10000;
        overflow: hidden;
        border: 1px solid rgba(255,239,194,0.12);
        font-family: 'Montserrat', sans-serif;
        animation: cb-slide-in 0.3s cubic-bezier(0.34,1.56,0.64,1);
    }

    @keyframes cb-slide-in {
        from { opacity: 0; transform: translateY(16px) scale(0.97); }
        to   { opacity: 1; transform: translateY(0)   scale(1); }
    }

    /* ===== HEADER ===== */
    .chat-header {
        background: #080f22;
        padding: 16px 18px 14px;
        flex-shrink: 0;
        border-bottom: 1px solid rgba(255,239,194,0.1);
    }

    .chat-header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .chat-header-brand {
        display: flex;
        align-items: center;
        gap: 9px;
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 1.5px;
        text-transform: uppercase;
        color: #ffefc2;
    }

    .chat-header-brand i {
        font-size: 1rem;
        color: #ffd952;
        animation: cb-beat 1.8s ease-in-out infinite;
    }

    @keyframes cb-beat {
        0%, 100% { transform: scale(1); }
        50%       { transform: scale(1.18); }
    }

    .chat-close-btn {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        background: rgba(255,239,194,0.07);
        border: none;
        color: rgba(255,239,194,0.5);
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1rem;
        transition: background 0.2s ease, color 0.2s ease;
        line-height: 1;
    }
    .chat-close-btn:hover {
        background: rgba(255,100,100,0.15);
        color: #ff7070;
    }

    /* ===== MODE SELECTOR ===== */
    .mode-selector {
        display: flex;
        gap: 6px;
        margin-top: 12px;
        background: rgba(255,255,255,0.05);
        padding: 4px;
        border-radius: 10px;
    }

    .mode-btn {
        flex: 1;
        font-family: 'Montserrat', sans-serif;
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.8px;
        text-transform: uppercase;
        padding: 7px 10px;
        border: none;
        border-radius: 7px;
        cursor: pointer;
        background: transparent;
        color: rgba(255,239,194,0.4);
        transition: all 0.2s ease;
    }

    .mode-btn.active {
        background: #ffc107 !important;
        color: #0a1329 !important;
        box-shadow: 0 2px 10px rgba(255,193,7,0.3);
    }

    .mode-btn:not(.active):hover {
        background: rgba(255,239,194,0.08);
        color: rgba(255,239,194,0.7);
    }

    /* ===== CHAT BOXES ===== */
    #ai-chat-box,
    #team-chat-box {
        flex: 1;
        padding: 16px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 10px;
        scrollbar-width: thin;
        scrollbar-color: rgba(255,239,194,0.15) transparent;
    }

    #ai-chat-box::-webkit-scrollbar,
    #team-chat-box::-webkit-scrollbar { width: 4px; }
    #ai-chat-box::-webkit-scrollbar-thumb,
    #team-chat-box::-webkit-scrollbar-thumb { background: rgba(255,239,194,0.15); border-radius: 4px; }

    #team-chat-box { display: none; }

    /* ===== MESSAGES ===== */
    .msg {
        padding: 10px 14px;
        border-radius: 14px;
        max-width: 82%;
        font-size: 0.8rem;
        line-height: 1.5;
        word-wrap: break-word;
        font-family: 'Montserrat', sans-serif;
        font-weight: 500;
    }

    .user-msg {
        background: linear-gradient(135deg, #ffc107, #e6a800);
        color: #0a1329;
        align-self: flex-end;
        border-bottom-right-radius: 4px;
        font-weight: 600;
    }

    .ai-msg {
        background: rgba(255,255,255,0.07);
        color: rgba(255,239,194,0.9);
        align-self: flex-start;
        border-bottom-left-radius: 4px;
        border: 1px solid rgba(255,239,194,0.08);
    }

    .team-msg {
        background: rgba(94,196,147,0.12);
        color: rgba(180,255,220,0.9);
        align-self: flex-start;
        border-bottom-left-radius: 4px;
        border: 1px solid rgba(94,196,147,0.15);
    }

    /* Role labels */
    .role-label {
        font-size: 0.6rem;
        text-transform: uppercase;
        font-weight: 700;
        letter-spacing: 1px;
        display: block;
        margin-bottom: 4px;
        opacity: 0.65;
    }
    .user-msg .role-label { color: #0a1329; text-align: right; }
    .team-msg .role-label { color: #5ec493; text-align: left; }

    /* ===== TYPING INDICATOR ===== */
    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 4px;
        padding: 12px 16px;
    }

    .typing-indicator span {
        display: inline-block;
        width: 7px;
        height: 7px;
        background: rgba(255,239,194,0.4);
        border-radius: 50%;
        animation: cb-bounce 1s infinite;
    }
    .typing-indicator span:nth-child(2) { animation-delay: 0.2s; }
    .typing-indicator span:nth-child(3) { animation-delay: 0.4s; }

    @keyframes cb-bounce {
        0%, 100% { transform: translateY(0);    opacity: 0.4; }
        50%       { transform: translateY(-5px); opacity: 1; }
    }

    /* ===== FOOTER ===== */
    .chat-footer {
        padding: 12px 14px;
        border-top: 1px solid rgba(255,239,194,0.08);
        display: flex;
        gap: 8px;
        background: #080f22;
        flex-shrink: 0;
        align-items: center;
    }

    #chat-message-input {
        flex: 1;
        background: rgba(255,255,255,0.06) !important;
        border: 1px solid rgba(255,239,194,0.15) !important;
        border-radius: 10px !important;
        color: #ffefc2 !important;
        font-family: 'Montserrat', sans-serif !important;
        font-size: 0.78rem !important;
        font-weight: 500 !important;
        padding: 9px 14px !important;
        outline: none !important;
        transition: border-color 0.2s ease;
    }

    #chat-message-input::placeholder { color: rgba(255,239,194,0.3) !important; }

    #chat-message-input:focus {
        border-color: rgba(255,193,7,0.45) !important;
        background: rgba(255,255,255,0.08) !important;
        box-shadow: none !important;
    }

    .chat-send-btn {
        width: 38px;
        height: 38px;
        border-radius: 10px;
        background: #ffc107;
        border: none;
        color: #0a1329;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
        transition: background 0.2s ease, transform 0.15s ease;
    }

    .chat-send-btn:hover {
        background: #ffd54f;
        transform: scale(1.06);
    }

    .chat-send-btn:active { transform: scale(0.97); }
</style>

<!-- ===== TOGGLE BUTTON ===== -->
<div id="chat-toggle" onclick="toggleChat()">
    <i class="fas fa-paw fa-xl"></i>
</div>

<!-- ===== CHAT WINDOW ===== -->
<div id="chat-container">
    <div class="chat-header">
        <div class="chat-header-top">
            <div class="chat-header-brand">
                <i class="fas fa-heartbeat"></i>
                <?= htmlspecialchars(setting('site_name', 'HEARTBEAT HEAVEN')) ?>
            </div>
            <button class="chat-close-btn" onclick="toggleChat()" aria-label="Close chat">&times;</button>
        </div>
        <div class="mode-selector">
            <button class="mode-btn active" id="btn-ai" onclick="setMode('ai')">
                <i class="fas fa-robot" style="margin-right:5px;"></i>AI Bot
            </button>
            <button class="mode-btn" id="btn-team" onclick="setMode('team')">
                <i class="fas fa-first-aid" style="margin-right:5px;"></i>Rescue Team
            </button>
        </div>
    </div>

    <div id="ai-chat-box">
        <div class="msg ai-msg">
            Your AI Assistant from <?= htmlspecialchars(setting('site_name', 'HEARTBEAT HEAVEN')) ?> is here. How can I help you? 🐾
        </div>
    </div>
    <div id="team-chat-box"></div>

    <div class="chat-footer">
        <input type="text" id="chat-message-input" placeholder="Type a message…">
        <button class="chat-send-btn" onclick="processSend()" aria-label="Send message">
            <i class="fas fa-paper-plane"></i>
        </button>
    </div>
</div>

<script>
    const CURRENT_USER_ID = "<?php echo $_SESSION['user_id'] ?? 'guest'; ?>";
    let currentMode = 'ai';
    let displayedIds = new Set();

    function toggleChat() {
        const container = document.getElementById('chat-container');
        container.style.display = (container.style.display === 'flex') ? 'none' : 'flex';
    }

    function setMode(mode) {
        currentMode = mode;
        document.getElementById('btn-ai').classList.toggle('active', mode === 'ai');
        document.getElementById('btn-team').classList.toggle('active', mode === 'team');
        document.getElementById('ai-chat-box').style.display = (mode === 'ai') ? 'flex' : 'none';
        document.getElementById('team-chat-box').style.display = (mode === 'team') ? 'flex' : 'none';
    }

    function showTyping() {
        const aiBox = document.getElementById('ai-chat-box');
        const el = document.createElement('div');
        el.className = 'msg ai-msg typing-indicator';
        el.id = 'typing-indicator';
        el.innerHTML = '<span></span><span></span><span></span>';
        aiBox.appendChild(el);
        aiBox.scrollTop = aiBox.scrollHeight;
    }

    function removeTyping() {
        const el = document.getElementById('typing-indicator');
        if (el) el.remove();
    }

    async function processSend() {
        const inputField = document.getElementById('chat-message-input');
        const text = inputField.value.trim();
        if (!text) return;
        inputField.value = '';

        if (currentMode === 'ai') {
            const aiBox = document.getElementById('ai-chat-box');
            aiBox.innerHTML += `<div class="msg user-msg">${text}</div>`;
            aiBox.scrollTop = aiBox.scrollHeight;
            showTyping();

            try {
                const res = await fetch('chat_handler.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ message: text })
                });
                const data = await res.json();
                removeTyping();
                if (data.reply) {
                    aiBox.innerHTML += `<div class="msg ai-msg">${data.reply}</div>`;
                } else if (data.error) {
                    aiBox.innerHTML += `<div class="msg ai-msg">⚠️ ${data.error}</div>`;
                }
                aiBox.scrollTop = aiBox.scrollHeight;
            } catch (e) {
                removeTyping();
                aiBox.innerHTML += `<div class="msg ai-msg">⚠️ <?= htmlspecialchars(setting('chatbot_fallback_message', 'Sorry, I am unavailable right now.')) ?></div>`;
                aiBox.scrollTop = aiBox.scrollHeight;
            }

        } else {
            try {
                await fetch('send_message.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        sender_id: CURRENT_USER_ID,
                        receiver_id: 'team_rescuer',
                        message: text
                    })
                });
                fetchTeamMessages();
            } catch (e) { console.error("Send failed"); }
        }
    }

    async function fetchTeamMessages() {
        if (currentMode !== 'team') return;
        try {
            const response = await fetch('get_messages.php');
            const messages = await response.json();
            const teamBox = document.getElementById('team-chat-box');

            messages.forEach(m => {
                if (!displayedIds.has(m.id)) {
                    const isMe = m.sender_id == CURRENT_USER_ID;
                    const msgClass = isMe ? 'user-msg' : 'team-msg';
                    const roleName = isMe ? "You" : "Rescue Team";
                    teamBox.innerHTML += `
                        <div class="msg ${msgClass}">
                            <span class="role-label">${roleName}</span>
                            ${m.message}
                        </div>`;
                    displayedIds.add(m.id);
                    teamBox.scrollTop = teamBox.scrollHeight;
                }
            });
        } catch (e) { console.log("Fetch Error"); }
    }

    setInterval(fetchTeamMessages, 3000);
    document.getElementById('chat-message-input').addEventListener('keypress', (e) => {
        if (e.key === 'Enter') processSend();
    });
</script>