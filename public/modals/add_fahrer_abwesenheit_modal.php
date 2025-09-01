<!-- modals/add_fahrer_abwesenheit_modal.php -->
<div id="fahrerAbwesenheitModal" class="modal" style="display: none;">
  <div class="modal-content">
    <span onclick="closeModal('fahrerAbwesenheitModal')" class="close">&times;</span>
    <h2>Abwesenheit eintragen</h2>
    <form action="modals/process_fahrer_abwesenheit.php" method="POST">
      <label for="fahrer_id">Fahrer:</label>
      <select name="fahrer_id" id="fahrer_id" required>
        <?php
        // Fahrer abrufen, um die Dropdown-Liste zu füllen
        $stmt = $pdo->prepare("SELECT vorname, nachname, FahrerID FROM Fahrer ORDER BY nachname ASC");
        $stmt->execute();
        $fahrerList = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($fahrerList as $f):
        ?>
          <option value="<?= htmlspecialchars($f['FahrerID']); ?>">
            <?= htmlspecialchars($f['nachname'] . ', ' . $f['vorname']); ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="abwesenheitsart">Abwesenheitsart:</label>
      <select name="abwesenheitsart" id="abwesenheitsart" required onchange="toggleGrundOptions()">
        <option value="Krankheit">Krankheit</option>
        <option value="Urlaub">Urlaub</option>
      </select>

      <label for="grund">Grund:</label>
      <select name="grund" id="grund" required>
        <!-- Optionen werden basierend auf der Abwesenheitsart dynamisch geändert -->
        <option value="krank">krank</option>
        <option value="Kind krank">Kind krank</option>
        <option value="Urlaub">Urlaub</option>
        <option value="unbezahlter Urlaub">unbezahlter Urlaub</option>
      </select>

      <label for="startdatum">Startdatum:</label>
      <input type="date" name="startdatum" id="startdatum" required>

      <label for="enddatum">Enddatum:</label>
      <input type="date" name="enddatum" id="enddatum" required>

      <label for="kommentar">Kommentar:</label>
      <textarea name="kommentar" id="kommentar"></textarea>

      <button type="submit">Eintragen</button>
    </form>
  </div>
</div>

<script>
  function toggleGrundOptions() {
    const abwesenheitsart = document.getElementById('abwesenheitsart').value;
    const grundSelect = document.getElementById('grund');
    grundSelect.innerHTML = ''; // Reset der Optionen

    if (abwesenheitsart === 'Krankheit') {
      const krankOption = document.createElement('option');
      krankOption.value = 'krank';
      krankOption.text = 'krank';
      const kindKrankOption = document.createElement('option');
      kindKrankOption.value = 'Kind krank';
      kindKrankOption.text = 'Kind krank';
      grundSelect.add(krankOption);
      grundSelect.add(kindKrankOption);
    } else if (abwesenheitsart === 'Urlaub') {
      const urlaubOption = document.createElement('option');
      urlaubOption.value = 'Urlaub';
      urlaubOption.text = 'Urlaub';
      const unbezahlterUrlaubOption = document.createElement('option');
      unbezahlterUrlaubOption.value = 'unbezahlter Urlaub';
      unbezahlterUrlaubOption.text = 'unbezahlter Urlaub';
      grundSelect.add(urlaubOption);
      grundSelect.add(unbezahlterUrlaubOption);
    }
  }
</script>

<style>
  /* Einfaches Modal-Design */
  .modal {
    display: none; /* Hidden by default */
    position: fixed; 
    z-index: 1; 
    left: 0;
    top: 0;
    width: 100%; 
    height: 100%; 
    overflow: auto; 
    background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
  }

  .modal-content {
    background-color: #fefefe;
    margin: 10% auto; 
    padding: 20px;
    border: 1px solid #888;
    width: 50%; 
    border-radius: 8px;
  }

  .close {
    color: #aaa;
    float: right;
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
  }

  .close:hover,
  .close:focus {
    color: black;
    text-decoration: none;
  }

  /* Weitere CSS-Anpassungen nach Bedarf */
</style>
