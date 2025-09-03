document.addEventListener('DOMContentLoaded', function () {
  var items = document.querySelectorAll('.conversation-item');
  var content = document.getElementById('conversation-content');

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
    });
  });

  if (items.length > 0) {
    items[0].click();
  }
});

