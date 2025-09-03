document.addEventListener('DOMContentLoaded', function () {
  var items = document.querySelectorAll('.conversation-list [data-other-id]');
  var content = document.getElementById('conversation-content');

  function escapeHtml(str) {
    return str
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  items.forEach(function (item) {
    item.addEventListener('click', function () {
      var otherId = item.getAttribute('data-other-id');
      fetch('/api/messages.php?other_id=' + encodeURIComponent(otherId))
        .then(function (res) { return res.json(); })
        .then(function (messages) {
          var html = '';
          messages.forEach(function (msg) {
            html += '<div class="message">' +
              '<p><strong>' + escapeHtml(msg.sender_name) + '</strong> am ' + escapeHtml(msg.created_at) + '</p>' +
              '<p>' + escapeHtml(msg.body).replace(/\n/g, '<br>') + '</p>' +
              '</div>';
          });
          content.innerHTML = html;
        });
    });
  });
});

