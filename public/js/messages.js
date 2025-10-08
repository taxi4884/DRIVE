document.addEventListener('DOMContentLoaded', function () {
  var content = document.getElementById('conversation-content');
  var conversationList = document.querySelector('.conversation-list');
  var chatForm = document.getElementById('chat-form');
  var recipientInput = document.getElementById('chat-recipient-id');
  var subjectInput = document.getElementById('chat-subject');
  var bodyInput = document.getElementById('chat-body');
  var notificationPollInterval = null;
  var conversationPollInterval = null;
  var conversationListPollInterval = null;
  var knownUnreadMessageIds = new Set();
  var unreadInitialized = false;
  var currentOtherId = null;
  var activeConversationItem = null;
  var isConversationPolling = false;
  var conversationSnapshots = {};
  var conversationListSnapshot = '';

  function escapeHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function truncatePreview(text) {
    var cleaned = text.replace(/\s+/g, ' ').trim();
    if (cleaned.length <= 40) {
      return cleaned;
    }
    return cleaned.slice(0, 37) + '…';
  }

  function isScrolledToBottom() {
    if (!content) {
      return false;
    }
    return Math.abs(content.scrollHeight - content.clientHeight - content.scrollTop) < 5;
  }

  function scrollConversationToBottom() {
    if (!content) {
      return;
    }
    content.scrollTop = content.scrollHeight;
  }

  function setActiveConversationItem(item) {
    if (activeConversationItem) {
      activeConversationItem.classList.remove('active');
    }
    activeConversationItem = item || null;
    if (activeConversationItem) {
      activeConversationItem.classList.add('active');
    }
  }

  function renderConversation(messages, otherId, options) {
    if (!content || !Array.isArray(messages)) {
      return;
    }

    var snapshot = messages
      .map(function (msg) { return msg.id + ':' + (msg.read_at || ''); })
      .join('|');

    var forceRender = options && options.forceRender;
    if (!forceRender && conversationSnapshots[otherId] === snapshot) {
      return;
    }

    conversationSnapshots[otherId] = snapshot;

    var wasAtBottom = false;
    if (options && options.maintainScrollPosition) {
      wasAtBottom = isScrolledToBottom();
    }

    var html = '';
    messages.forEach(function (msg) {
      var cls = (msg.sender_id === otherId) ? 'received' : 'sent';
      html += '<div class="message ' + cls + '">';
      html += '<p class="message-header"><strong>' + escapeHtml(msg.sender_name) + '</strong> am ' + escapeHtml(msg.created_at) + '</p>';
      if (msg.subject) {
        html += '<p class="message-subject"><span>Betreff:</span> ' + escapeHtml(msg.subject) + '</p>';
      }
      html += '<p class="message-body">' + escapeHtml(msg.body).replace(/\n/g, '<br>') + '</p>';
      if (msg.read_at) {
        html += '<p class="message-meta">Gelesen am ' + escapeHtml(msg.read_at) + '</p>';
      }
      html += '</div>';
    });
    content.innerHTML = html;

    if (options && options.updateListPreview) {
      updateConversationPreview(otherId, messages);
    }

    var shouldScrollToBottom = Boolean(options && options.scrollToBottom);
    if (!shouldScrollToBottom && options && options.maintainScrollPosition && wasAtBottom) {
      shouldScrollToBottom = true;
    }

    if (shouldScrollToBottom) {
      scrollConversationToBottom();
    }
  }

  function fetchConversation(otherId, options) {
    if (!otherId) {
      return Promise.resolve();
    }

    return fetch('/api/messages.php?other_id=' + encodeURIComponent(otherId))
      .then(function (res) { return res.json(); })
      .then(function (messages) {
        renderConversation(messages, otherId, options || {});
      })
      .catch(function (err) {
        console.error('Fehler beim Laden der Unterhaltung:', err);
      });
  }

  function updateConversationPreview(otherId, messages) {
    if (!conversationList || !Array.isArray(messages) || messages.length === 0) {
      return;
    }

    var item = conversationList.querySelector('.conversation-item[data-other-id="' + otherId + '"]');
    if (!item) {
      return;
    }

    var lastMessage = messages[messages.length - 1];
    if (lastMessage.subject !== undefined) {
      item.setAttribute('data-subject', lastMessage.subject || '');
    }

    var preview = item.querySelector('.preview');
    if (preview) {
      preview.textContent = truncatePreview(lastMessage.body || '');
    }
  }

  function buildConversationItem(conv) {
    var item = document.createElement('div');
    item.className = 'card conversation-item';
    item.setAttribute('data-other-id', conv.other_id);
    item.setAttribute('data-subject', conv.subject || '');

    var nameEl = document.createElement('strong');
    nameEl.textContent = conv.other_name || '';
    item.appendChild(nameEl);
    item.appendChild(document.createElement('br'));

    var subjectEl = document.createElement('span');
    subjectEl.textContent = conv.subject || '';
    item.appendChild(subjectEl);
    item.appendChild(document.createElement('br'));

    var previewEl = document.createElement('span');
    previewEl.className = 'preview';
    previewEl.textContent = truncatePreview(conv.body || '');
    item.appendChild(previewEl);

    item.addEventListener('click', function () {
      selectConversation(parseInt(conv.other_id, 10), item, { scrollToBottom: true, forceRender: true });
    });

    return item;
  }

  function rebuildConversationList(conversations) {
    if (!conversationList) {
      return;
    }

    var previousOtherId = currentOtherId;
    conversationList.innerHTML = '';

    var newActiveItem = null;

    conversations.forEach(function (conv) {
      var item = buildConversationItem(conv);
      conversationList.appendChild(item);
      if (previousOtherId !== null && parseInt(conv.other_id, 10) === previousOtherId) {
        newActiveItem = item;
      }
    });

    if (newActiveItem) {
      setActiveConversationItem(newActiveItem);
    } else if (previousOtherId !== null && conversations.length > 0) {
      var firstItem = conversationList.querySelector('.conversation-item');
      if (firstItem) {
        selectConversation(parseInt(firstItem.getAttribute('data-other-id'), 10), firstItem, { scrollToBottom: false, forceRender: true });
      }
    } else if (previousOtherId === null && conversations.length > 0) {
      var initialItem = conversationList.querySelector('.conversation-item');
      if (initialItem) {
        selectConversation(parseInt(initialItem.getAttribute('data-other-id'), 10), initialItem, { scrollToBottom: true, forceRender: true });
      }
    }
  }

  function refreshConversationList(force) {
    if (!conversationList) {
      return Promise.resolve();
    }

    return fetch('/api/conversations.php')
      .then(function (res) { return res.json(); })
      .then(function (conversations) {
        if (!Array.isArray(conversations)) {
          return;
        }

        var snapshot = JSON.stringify(conversations.map(function (conv) {
          return [conv.other_id, conv.id, conv.created_at].join(':');
        }));

        if (!force && snapshot === conversationListSnapshot) {
          return;
        }

        conversationListSnapshot = snapshot;
        rebuildConversationList(conversations);
      })
      .catch(function (err) {
        console.error('Fehler beim Aktualisieren der Unterhaltungsliste:', err);
      });
  }

  function selectConversation(otherId, item, options) {
    if (!otherId || !item) {
      return;
    }

    currentOtherId = otherId;
    setActiveConversationItem(item);

    if (recipientInput) {
      recipientInput.value = otherId;
    }
    if (subjectInput) {
      subjectInput.value = item.getAttribute('data-subject') || '';
    }

    var renderOptions = {
      forceRender: options && options.forceRender,
      scrollToBottom: options && options.scrollToBottom,
      maintainScrollPosition: options && options.maintainScrollPosition,
      updateListPreview: true
    };

    fetchConversation(otherId, renderOptions).then(function () {
      ensureConversationPolling();
    });
  }

  function ensureConversationPolling() {
    if (conversationPollInterval !== null) {
      return;
    }

    conversationPollInterval = setInterval(function () {
      if (isConversationPolling || currentOtherId === null) {
        return;
      }

      isConversationPolling = true;
      fetchConversation(currentOtherId, {
        maintainScrollPosition: true,
        updateListPreview: true
      }).then(function () {
        isConversationPolling = false;
      });
    }, 5000);
  }

  function ensureConversationListPolling() {
    if (conversationListPollInterval !== null) {
      return;
    }

    conversationListPollInterval = setInterval(function () {
      refreshConversationList(false);
    }, 15000);
  }

  if (conversationList) {
    Array.prototype.forEach.call(conversationList.querySelectorAll('.conversation-item'), function (item, index) {
      item.addEventListener('click', function () {
        var otherId = parseInt(item.getAttribute('data-other-id'), 10);
        selectConversation(otherId, item, { scrollToBottom: true, forceRender: true });
      });

      if (index === 0 && currentOtherId === null) {
        var initialOtherId = parseInt(item.getAttribute('data-other-id'), 10);
        selectConversation(initialOtherId, item, { scrollToBottom: true, forceRender: true });
      }
    });

    ensureConversationListPolling();
  }

  function baselineUnreadMessages(messages) {
    messages.forEach(function (message) {
      knownUnreadMessageIds.add(message.id);
    });
  }

  function showDesktopNotification(message) {
    if (!('Notification' in window)) {
      return;
    }

    var title = 'Neue Nachricht von ' + message.sender_name;
    var body = message.subject ? message.subject : message.body;

    try {
      new Notification(title, {
        body: body,
        tag: 'message-' + message.id
      });
    } catch (err) {
      console.error('Fehler beim Anzeigen der Benachrichtigung:', err);
    }
  }

  function fetchUnreadMessages(initialLoad) {
    fetch('/api/unread_messages.php')
      .then(function (res) {
        if (!res.ok) {
          throw new Error('HTTP ' + res.status);
        }
        return res.json();
      })
      .then(function (messages) {
        if (!Array.isArray(messages)) {
          return;
        }

        if (initialLoad && !unreadInitialized) {
          baselineUnreadMessages(messages);
          unreadInitialized = true;
          return;
        }

        messages.forEach(function (message) {
          if (knownUnreadMessageIds.has(message.id)) {
            return;
          }

          knownUnreadMessageIds.add(message.id);

          if (Notification.permission === 'granted') {
            showDesktopNotification(message);
          }
        });
      })
      .catch(function (err) {
        console.error('Fehler beim Abrufen ungelesener Nachrichten:', err);
      });
  }

  function startNotificationPolling() {
    if (notificationPollInterval !== null) {
      return;
    }

    fetchUnreadMessages(true);
    notificationPollInterval = setInterval(function () {
      fetchUnreadMessages(false);
    }, 30000);
  }

  if ('Notification' in window) {
    if (Notification.permission === 'granted') {
      startNotificationPolling();
    } else if (Notification.permission !== 'denied') {
      Notification.requestPermission().then(function (permission) {
        if (permission === 'granted') {
          startNotificationPolling();
        }
      }).catch(function (err) {
        console.error('Benachrichtigungsberechtigung konnte nicht angefordert werden:', err);
      });
    }
  }

  if (chatForm) {
    chatForm.addEventListener('submit', function (e) {
      e.preventDefault();
      var formData = new FormData(chatForm);
      fetch(chatForm.action, {
        method: 'POST',
        body: formData,
        headers: {
          'Accept': 'application/json',
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
        .then(function (res) {
          if (!res.ok) {
            throw new Error('Fehler beim Senden der Nachricht (Status ' + res.status + ')');
          }
          return res.json();
        })
        .then(function (response) {
          if (response && response.error) {
            console.error(response.error);
            return;
          }

          if (bodyInput) {
            bodyInput.value = '';
          }

          if (currentOtherId !== null) {
            fetchConversation(currentOtherId, {
              forceRender: true,
              scrollToBottom: true,
              updateListPreview: true
            });
          }

          refreshConversationList(true);
        })
        .catch(function (err) {
          console.error(err);
        });
    });
  }
});

