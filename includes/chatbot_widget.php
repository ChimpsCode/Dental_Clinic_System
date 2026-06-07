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

<section class="chat-widget" id="chatWidget" role="dialog" aria-modal="false" aria-label="AI automated chat" hidden>
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
