document.addEventListener('DOMContentLoaded', function () {
  var items = document.querySelectorAll('.conversation-item');
  var content = document.getElementById('conversation-content');
  var chatForm = document.getElementById('chat-form');
  var recipientInput = document.getElementById('chat-recipient-id');
  var subjectInput = document.getElementById('chat-subject');
  var bodyInput = document.getElementById('chat-body');
  var notificationPollInterval = null;
  var knownUnreadMessageIds = new Set();
  var unreadInitialized = false;

  function escapeHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  items.forEach(function (item) {
    item.addEventListener('click', function () {
      var otherId = parseInt(item.getAttribute('data-other-id'), 10);
      fetch('/api/messages.php?other_id=' + encodeURIComponent(otherId))
        .then(function (res) { return res.json(); })
        .then(function (messages) {
          var html = '';
          messages.forEach(function (msg) {
            var cls = (msg.sender_id === otherId) ? 'received' : 'sent';
            html += '<div class="message ' + cls + '">' +
              '<p><strong>' + escapeHtml(msg.sender_name) + '</strong> am ' + escapeHtml(msg.created_at) + '</p>' +
              '<p>' + escapeHtml(msg.body).replace(/\n/g, '<br>') + '</p>' +
              '</div>';
          });
          content.innerHTML = html;
        });
      recipientInput.value = otherId;
      subjectInput.value = item.getAttribute('data-subject');
    });
  });

  if (items.length > 0) {
    items[0].click();
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
        body: formData
      })
        .then(function (res) { return res.json(); })
        .then(function (msg) {
          var html = '<div class="message sent">' +
            '<p><strong>' + escapeHtml(msg.sender_name) + '</strong> am ' + escapeHtml(msg.created_at) + '</p>' +
            '<p>' + escapeHtml(msg.body).replace(/\n/g, '<br>') + '</p>' +
            '</div>';
          content.insertAdjacentHTML('beforeend', html);
          bodyInput.value = '';
        })
        .catch(function (err) { console.error(err); });
    });
  }
});

