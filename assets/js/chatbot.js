(function() {
  'use strict';

  if (!document.getElementById('chatFab')) return;

  var fab = document.getElementById('chatFab');
  var widget = document.getElementById('chatWidget');
  var closeBtn = document.getElementById('chatClose');
  var form = document.getElementById('chatForm');
  var input = document.getElementById('chatText');
  var body = document.getElementById('chatBody');
  var balloon = document.getElementById('chatBalloon');

  function addMsg(role, text) {
    var row = document.createElement('div');
    row.className = 'chat-msg chat-msg-' + role;
    var bubble = document.createElement('div');
    bubble.className = 'chat-bubble';
    bubble.textContent = text;
    row.appendChild(bubble);
    body.appendChild(row);
    body.scrollTop = body.scrollHeight;
    return bubble;
  }

  function addComponent(el) {
    body.appendChild(el);
    body.scrollTop = body.scrollHeight;
  }

  function normalize(s) {
    return (s || '').toLowerCase().replace(/\s+/g, ' ').trim();
  }

  function renderServiceList(title) {
    var container = document.createElement('div');
    container.className = 'chat-service-container';

    var header = document.createElement('div');
    header.className = 'chat-service-header';
    header.textContent = title || 'Our Services';
    container.appendChild(header);

    (window.SERVICES || []).forEach(function(svc) {
      var item = document.createElement('div');
      item.className = 'chat-service-item';
      item.setAttribute('role', 'button');
      item.setAttribute('tabindex', '0');

      var nameSpan = document.createElement('span');
      nameSpan.className = 'chat-service-name';
      nameSpan.textContent = svc.name;

      var priceSpan = document.createElement('span');
      priceSpan.className = 'chat-service-price';
      priceSpan.textContent = svc.price;

      item.appendChild(nameSpan);
      item.appendChild(priceSpan);

      item.addEventListener('click', function() { renderServiceActions(svc); });
      item.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') {
          e.preventDefault();
          renderServiceActions(svc);
        }
      });

      container.appendChild(item);
    });

    var hint = document.createElement('span');
    hint.className = 'chat-service-hint';
    hint.textContent = 'Click a service to check price or book';
    container.appendChild(hint);

    addComponent(container);
  }

  function renderServiceActions(svc) {
    var container = document.createElement('div');
    container.className = 'chat-service-actions';

    var selected = document.createElement('div');
    selected.className = 'chat-service-selected';
    selected.textContent = 'Selected: ' + svc.name;
    container.appendChild(selected);

    var btnGroup = document.createElement('div');
    btnGroup.className = 'chat-action-buttons';

    var priceBtn = document.createElement('button');
    priceBtn.type = 'button';
    priceBtn.className = 'chat-action-btn chat-action-btn-price';
    priceBtn.textContent = 'Check Price';
    priceBtn.addEventListener('click', function() {
      container.remove();
      showServicePrice(svc);
    });

    var bookBtn = document.createElement('button');
    bookBtn.type = 'button';
    bookBtn.className = 'chat-action-btn chat-action-btn-book';
    bookBtn.textContent = 'Book Appointment';
    bookBtn.addEventListener('click', function() {
      container.remove();
      chatbotStartBooking(svc);
    });

    btnGroup.appendChild(priceBtn);
    btnGroup.appendChild(bookBtn);
    container.appendChild(btnGroup);
    addComponent(container);
  }

  function showServicePrice(svc) {
    addMsg('bot', svc.name + ': ' + svc.price + ' \u2022 ' + svc.duration + '. Would you like to book?');

    var container = document.createElement('div');
    container.className = 'chat-service-actions';

    var btnGroup = document.createElement('div');
    btnGroup.className = 'chat-action-buttons';

    var bookBtn = document.createElement('button');
    bookBtn.type = 'button';
    bookBtn.className = 'chat-action-btn chat-action-btn-book';
    bookBtn.textContent = 'Book Appointment';
    bookBtn.addEventListener('click', function() {
      container.remove();
      chatbotStartBooking(svc);
    });

    var backBtn = document.createElement('button');
    backBtn.type = 'button';
    backBtn.className = 'chat-action-btn chat-action-btn-back';
    backBtn.textContent = '\u2190 Back to Services';
    backBtn.addEventListener('click', function() {
      container.remove();
      renderServiceList();
    });

    btnGroup.appendChild(bookBtn);
    btnGroup.appendChild(backBtn);
    container.appendChild(btnGroup);
    addComponent(container);
  }

  function chatbotStartBooking(svc) {
    window.__chatbotService = svc;
    var modal = document.getElementById('registrationModal');
    if (modal) {
      modal.style.display = 'flex';
      document.body.style.overflow = 'hidden';
    }
  }

  function renderInquireSection() {
    var card = document.createElement('div');
    card.className = 'chat-inquire-card';

    var phoneLine = document.createElement('div');
    phoneLine.className = 'chat-inquire-phone';
    phoneLine.innerHTML = 'You can also talk to our clinic directly at<br><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640" width="20" height="20" style="vertical-align:middle; margin-right:6px;"><path d="M224.2 89C216.3 70.1 195.7 60.1 176.1 65.4L170.6 66.9C106 84.5 50.8 147.1 66.9 223.3C104 398.3 241.7 536 416.7 573.1C493 589.3 555.5 534 573.1 469.4L574.6 463.9C580 444.2 569.9 423.6 551.1 415.8L453.8 375.3C437.3 368.4 418.2 373.2 406.8 387.1L368.2 434.3C297.9 399.4 241.3 341 208.8 269.3L253 233.3C266.9 222 271.6 202.9 264.8 186.3L224.2 89z"/></svg> 0967 187 8603';
    card.appendChild(phoneLine);

    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'chat-inquire-btn';
    btn.textContent = 'Contact Us';
    btn.addEventListener('click', function() {
      closeChat();
      var contact = document.getElementById('contact');
      if (contact) contact.scrollIntoView({ behavior: 'smooth' });
    });
    card.appendChild(btn);

    addComponent(card);
  }

  function isServiceQuery(nq) {
    return /\b(services?)\b/.test(nq) ||
           /\b(procedures?|treatments?)\b/.test(nq) ||
           /\b(prices?|cost|rates?|fees?)\b/.test(nq) ||
           /how much/.test(nq) ||
           /magkano/.test(nq) ||
           /what do you do/.test(nq) ||
           /do you offer/.test(nq) ||
           /are available/.test(nq);
  }

  function isBookingQuery(nq) {
    return /\b(book|appointment|schedule|reserve)\b/.test(nq);
  }

  var typingRow = null;

  async function processChat(msg) {
    var nq = normalize(msg);

    if (isServiceQuery(nq)) {
      addMsg('bot', 'Here are our available services. Click any service to check the price or book an appointment:');
      renderServiceList();
      return;
    }

    if (isBookingQuery(nq)) {
      addMsg('bot', "I'd be happy to help you book! Please select a service you're interested in:");
      renderServiceList('Book an Appointment');
      return;
    }

    var typingBubble = addMsg('bot', '...');
    typingBubble.classList.add('typing-indicator');
    typingRow = typingBubble.closest('.chat-msg');

    try {
      var response = await fetch('../api/ai_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ message: msg })
      });

      if (!response.ok) {
        var errorText = await response.text();
        throw new Error('Server returned ' + response.status + ': ' + errorText.substring(0, 100));
      }

      var data = await response.json();
      if (typingRow) typingRow.remove();
      typingRow = null;

      if (data.response) {
        var text = data.response;
        var typePrefix = text.substring(0, 14);

        if (typePrefix === '[TYPE:SERVICES]') {
          var msgText = text.substring(14).trim();
          if (msgText) addMsg('bot', msgText);
          renderServiceList();
        } else if (typePrefix === '[TYPE:BOOKING]') {
          var msgText = text.substring(13).trim();
          if (msgText) addMsg('bot', msgText);
          renderServiceList('Book an Appointment');
        } else {
          addMsg('bot', text);
        }
      } else if (data.error) {
        addMsg('bot', "I'm sorry, I'm having an issue: " + data.error);
      } else {
        addMsg('bot', "I'm sorry, I encountered an unknown error. Please try again later.");
      }
    } catch (error) {
      console.error('Chat Error:', error);
      if (typingRow) typingRow.remove();
      typingRow = null;
      addMsg('bot', "I'm having trouble connecting to my brain right now. (Error: " + error.message + ").");
    }
  }

  function openChat() {
    widget.hidden = false;
    fab.setAttribute('aria-expanded', 'true');
    setTimeout(function() { input.focus(); }, 0);
    if (!body.dataset.greeted) {
      addMsg('bot', "Hello! Welcome to RF Dental Clinic. I'm RF-ChatBot, your friendly AI receptionist. How may I assist you today? Are you looking for information about our services, pricing, or perhaps you'd like to book an appointment? Please let me know what you'd like to inquire about.");
      renderInquireSection();
      body.dataset.greeted = '1';
    }
  }

  function closeChat() {
    widget.hidden = true;
    fab.setAttribute('aria-expanded', 'false');
    fab.focus();
  }

  fab.addEventListener('click', function() { widget.hidden ? openChat() : closeChat(); });
  closeBtn.addEventListener('click', closeChat);
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !widget.hidden) closeChat();
  });

  form.addEventListener('submit', function(e) {
    e.preventDefault();
    var text = input.value.trim();
    if (!text) return;
    addMsg('user', text);
    input.value = '';
    processChat(text);
  });



  if (balloon) {
    setTimeout(function() {
      balloon.classList.add('show');
      setTimeout(function() {
        balloon.classList.remove('show');
      }, 4000);
    }, 1000);
  }

  // Expose functions needed by other scripts
  window.chatbotStartBooking = chatbotStartBooking;
})();
