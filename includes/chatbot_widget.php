    <button
      type="button"
      class="chat-fab"
      id="chatFab"
      aria-label="Open AI chat"
      aria-haspopup="dialog"
      aria-controls="chatWidget"
      aria-expanded="false"
    >
      <span class="chat-fab-icon" aria-hidden="true">💬</span>
    </button>
    
    <!-- Chatbot greeting balloon -->
    <div class="chat-balloon" id="chatBalloon">
      <div class="chat-balloon-content">
        <span class="chat-balloon-icon">🤖</span>
        <span class="chat-balloon-text">Hi! I'm your RF Dental Clinic chatbot assistant.</span>
      </div>
      <div class="chat-balloon-arrow"></div>
    </div>

    <section class="chat-widget" id="chatWidget" role="dialog" aria-modal="false" aria-label="AI automated chat" style="display: none;">
      <header class="chat-header">
        <div class="chat-title">
          <div class="chat-title-main">RF Dental AI</div>
          <div class="chat-title-sub">Automated inquiry assistant</div>
        </div>
        <button type="button" class="chat-close" id="chatClose" aria-label="Close chat">✕</button>
      </header>

      <div class="chat-body" id="chatBody" aria-live="polite" aria-relevant="additions"></div>

      <form class="chat-input" id="chatForm" autocomplete="off">
        <input
          type="text"
          id="chatText"
          name="message"
          placeholder="Type your question… (e.g., price for braces, clinic hours)"
          aria-label="Chat message"
          required
        />
        <button class="btn btn-primary" type="submit">Send</button>
      </form>
    </section>

    <style>
      .typing-indicator { color: #666; font-style: italic; }
      .chat-options button:hover { background: #005daa !important; color: #fff !important; }
    </style>
    <script>
      (() => {
        const fab = document.getElementById('chatFab');
        const widget = document.getElementById('chatWidget');
        const closeBtn = document.getElementById('chatClose');
        const form = document.getElementById('chatForm');
        const input = document.getElementById('chatText');
        const body = document.getElementById('chatBody');
        const balloon = document.getElementById('chatBalloon');

        if (!fab || !widget) return;

        function addMsg(role, text) {
          const row = document.createElement('div');
          row.className = `chat-msg chat-msg-${role}`;
          const bubble = document.createElement('div');
          bubble.className = 'chat-bubble';
          bubble.textContent = text;
          row.appendChild(bubble);
          body.appendChild(row);
          body.scrollTop = body.scrollHeight;
          return bubble;
        }

        async function processChat(userMessage) {
          const typingBubble = addMsg('bot', '...');
          typingBubble.classList.add('typing-indicator');

          try {
            // Using a more robust path relative to the current script's location
            const response = await fetch('../api/ai_chat.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({ message: userMessage })
            });

            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Server returned ${response.status}: ${errorText.substring(0, 100)}`);
            }

            const data = await response.json();
            typingBubble.closest('.chat-msg').remove();

            if (data.response) {
              addMsg('bot', data.response);
            } else if (data.error) {
              addMsg('bot', "I'm sorry, I'm having an issue: " + data.error);
            } else {
              addMsg('bot', "I'm sorry, I encountered an unknown error. Please try again later.");
            }
          } catch (error) {
            console.error('Chat Error:', error);
            if (typingBubble.closest('.chat-msg')) typingBubble.closest('.chat-msg').remove();
            addMsg('bot', "I'm having trouble connecting to my brain right now. (Error: " + error.message + "). Please check your internet or ask the admin to check the API settings.");
          }
        }

        function openChat() {
          widget.style.display = 'flex';
          fab.setAttribute('aria-expanded', 'true');
          setTimeout(() => input.focus(), 0);
          if (!body.dataset.greeted) {
            addMsg('bot', "Hi! I’m RF Dental AI. How can I help you today?");
            body.dataset.greeted = '1';
          }
        }

        function closeChat() {
          widget.style.display = 'none';
          fab.setAttribute('aria-expanded', 'false');
          fab.focus();
        }

        fab.addEventListener('click', () => {
          if (widget.style.display === 'none' || widget.style.display === '') {
            openChat();
          } else {
            closeChat();
          }
        });
        closeBtn.addEventListener('click', closeChat);
        
        form.addEventListener('submit', (e) => {
          e.preventDefault();
          const text = input.value.trim();
          if (!text) return;
          addMsg('user', text);
          input.value = '';
          processChat(text);
        });

        if (balloon) {
          setTimeout(() => {
            balloon.classList.add('show');
            setTimeout(() => balloon.classList.remove('show'), 4000);
          }, 1000);
        }
      })();
    </script>
