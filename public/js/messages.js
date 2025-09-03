document.addEventListener('DOMContentLoaded', function () {
  var items = document.querySelectorAll('.conversation-item');
  var content = document.getElementById('conversation-content');
  var chatForm = document.getElementById('chat-form');
  var recipientInput = document.getElementById('chat-recipient-id');
  var subjectInput = document.getElementById('chat-subject');
  var bodyInput = document.getElementById('chat-body');

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

