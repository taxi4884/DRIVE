<?php
require_once '../../includes/head.php'; // Verbindung und Authentifizierung
require_once __DIR__ . '/error_handler.php';

// Session prüfen
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$fahrer_id = $_SESSION['user_id'];

// Persönliche Daten abrufen
try {
    $query = "
		SELECT Vorname, Nachname, Telefonnummer, Email, Strasse, Hausnummer, PLZ, Ort,
			   Fuehrerscheinnummer, FuehrerscheinGueltigkeit, PScheinGueltigkeit,
			   standard_schichtziel, standard_monatsziel
		FROM Fahrer
		WHERE FahrerID = ?
	";
    $stmt = $pdo->prepare($query);
    $stmt->execute([$fahrer_id]);
    $fahrer = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fahrer) {
        throw new RuntimeException(sprintf('Keine Daten für Fahrer %d gefunden.', $fahrer_id));
    }
} catch (PDOException $e) {
    throw new RuntimeException('Datenbankfehler beim Abrufen der persönlichen Daten.', 0, $e);
}

// Abwesenheiten (Krankheit und Urlaub) für den Fahrer abrufen
try {
    $abwesenheitenQuery = "
        SELECT abwesenheitsart, grund, status, startdatum, enddatum
        FROM FahrerAbwesenheiten
        WHERE FahrerID = ?
        ORDER BY startdatum DESC
    ";
    $stmt = $pdo->prepare($abwesenheitenQuery);
    $stmt->execute([$fahrer_id]);
    $abwesenheiten = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    throw new RuntimeException('Datenbankfehler beim Abrufen der Abwesenheiten.', 0, $e);
}

$heute = new DateTime('today');

// Führerschein- und P-Schein-Gültigkeit bewerten
function bewerteGueltigkeit(?string $datumString, DateTime $heute): array
{
    if (!$datumString) {
        return [
            'status' => 'unbekannt',
            'badgeClass' => 'badge-secondary',
            'icon' => 'fa-question-circle',
            'text' => 'Keine Angabe',
        ];
    }

    try {
        $gueltigBis = new DateTime($datumString);
    } catch (Exception $e) {
        return [
            'status' => 'unbekannt',
            'badgeClass' => 'badge-secondary',
            'icon' => 'fa-question-circle',
            'text' => 'Ungültiges Datum',
        ];
    }

    $diff = (int) $heute->diff($gueltigBis)->format('%r%a');
    $tageText = $diff >= 0 ? $diff : abs($diff);
    $suffix = $diff >= 0 ? 'Tage verbleibend' : 'Tage überfällig';

    if ($diff < 0) {
        return [
            'status' => 'abgelaufen',
            'badgeClass' => 'badge-danger',
            'icon' => 'fa-circle-xmark',
            'text' => sprintf('Abgelaufen seit %d %s', $tageText, $tageText === 1 ? 'Tag' : 'Tagen'),
        ];
    }

    if ($diff <= 7) {
        $badgeClass = 'badge-danger';
        $icon = 'fa-triangle-exclamation';
        $status = 'kritisch';
    } elseif ($diff <= 30) {
        $badgeClass = 'badge-warning';
        $icon = 'fa-exclamation-circle';
        $status = 'bald fällig';
    } else {
        $badgeClass = 'badge-success';
        $icon = 'fa-circle-check';
        $status = 'gültig';
    }

    return [
        'status' => $status,
        'badgeClass' => $badgeClass,
        'icon' => $icon,
        'text' => sprintf('%d %s', $tageText, $suffix),
    ];
}

$fuehrerscheinStatus = bewerteGueltigkeit($fahrer['FuehrerscheinGueltigkeit'] ?? null, $heute);
$pscheinStatus = bewerteGueltigkeit($fahrer['PScheinGueltigkeit'] ?? null, $heute);

function statusBadgeClass(?string $status): string
{
    return match (strtolower((string) $status)) {
        'genehmigt' => 'badge-success',
        'abgelehnt' => 'badge-danger',
        'beantragt' => 'badge-warning',
        default => 'badge-secondary',
    };
}

function statusSlug(?string $status): string
{
    $status = strtolower(trim((string) $status));
    return $status === '' ? 'unbekannt' : preg_replace('/[^a-z0-9]+/', '-', $status);
}

function statusLabel(?string $status): string
{
    $status = trim((string) $status);
    return $status === '' ? 'Nicht gesetzt' : ucfirst($status);
}

function artSlug(?string $art): string
{
    $art = strtolower(trim((string) $art));
    if ($art === '') {
        return 'sonstiges';
    }

    $slug = preg_replace('/[^a-z0-9]+/', '-', $art);
    return $slug ?: 'sonstiges';
}

function formatDatum(?string $datum): string
{
    if (!$datum) {
        return 'Keine Angabe';
    }

    try {
        return (new DateTime($datum))->format('d.m.Y');
    } catch (Exception $e) {
        return 'Keine Angabe';
    }
}

function berechneWerktage(DateTime $start, DateTime $ende): int
{
    if ($ende < $start) {
        return 0;
    }

    $tage = 0;
    $interval = new DateInterval('P1D');
    $endeInklusiv = (clone $ende)->modify('+1 day');

    foreach (new DatePeriod(clone $start, $interval, $endeInklusiv) as $datum) {
        $wochentag = (int) $datum->format('N'); // 1 (Montag) bis 7 (Sonntag)
        if ($wochentag <= 5) {
            $tage++;
        }
    }

    return $tage;
}

// Urlaubstage berechnen (Basis 30 Tage)
$jahresUrlaub = 30;
$genehmigteUrlaubstage = 0;

foreach ($abwesenheiten as $eintrag) {
    if ($eintrag['abwesenheitsart'] === 'Urlaub' && strtolower((string) $eintrag['status']) === 'genehmigt') {
        try {
            $start = new DateTime($eintrag['startdatum']);
            $ende = new DateTime($eintrag['enddatum']);
            $genehmigteUrlaubstage += berechneWerktage($start, $ende);
        } catch (Exception $e) {
            continue;
        }
    }
}

$resturlaub = max(0, $jahresUrlaub - $genehmigteUrlaubstage);

// Abwesenheiten nach Zeitraum aufteilen
$abwesenheitenZukunft = [];
$abwesenheitenVergangenheit = [];
 $abwesenheitIcons = [
    'Urlaub' => 'fa-umbrella-beach',
    'Krankheit' => 'fa-briefcase-medical',
];
foreach ($abwesenheiten as $eintrag) {
    try {
        $ende = new DateTime($eintrag['enddatum']);
    } catch (Exception $e) {
        $ende = clone $heute;
    }

    if ($ende < $heute) {
        $abwesenheitenVergangenheit[] = $eintrag;
    } else {
        $abwesenheitenZukunft[] = $eintrag;
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Persönliche Daten | DRIVE</title>
  <link rel="stylesheet" href="css/design-system.css">
  <link rel="stylesheet" href="css/driver-dashboard.css">
  <link rel="stylesheet" href="css/personal.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body class="personal-page">
  <main>
    <h1><i class="fa-solid fa-id-card-clip"></i> Persönliche Daten</h1>
	
	<?php $goalsSuccessVisible = isset($_GET['goals']); ?>
	<div
	  class="alert alert-success<?= $goalsSuccessVisible ? ' is-visible' : '' ?>"
	  id="goals-success-banner"
	  role="status"
	  aria-live="polite"
	  aria-hidden="<?= $goalsSuccessVisible ? 'false' : 'true' ?>"
	>
	  <i class="fa-solid fa-circle-check"></i>
	  <span>Deine Umsatzziele wurden gespeichert.</span>
	</div>

    <?php $urlaubSuccessVisible = isset($_GET['success']); ?>
    <div class="alert alert-success<?= $urlaubSuccessVisible ? ' is-visible' : '' ?>" id="urlaub-success-banner" role="status" aria-live="polite" aria-hidden="<?= $urlaubSuccessVisible ? 'false' : 'true' ?>">
      <i class="fa-solid fa-circle-check"></i>
      <span id="urlaub-success-text">Dein Urlaubsantrag wurde erfolgreich übermittelt.</span>
    </div>

    <div class="section-grid">
      <div class="card">
        <div class="card-header">
          <h2><i class="fa-solid fa-address-book"></i> Kontakt</h2>
        </div>
        <div class="card-list">
          <div class="card-item">
            <div class="item-header">
              <div class="item-icon"><i class="fa-solid fa-user"></i></div>
              <span class="item-label">Vorname</span>
            </div>
            <span class="item-value"><?= htmlspecialchars($fahrer['Vorname']) ?></span>
          </div>
          <div class="card-item">
            <div class="item-header">
              <div class="item-icon"><i class="fa-solid fa-id-badge"></i></div>
              <span class="item-label">Nachname</span>
            </div>
            <span class="item-value"><?= htmlspecialchars($fahrer['Nachname']) ?></span>
          </div>
          <div class="card-item">
            <div class="item-header">
              <div class="item-icon"><i class="fa-solid fa-phone"></i></div>
              <span class="item-label">Telefon</span>
            </div>
            <span class="item-value"><?= htmlspecialchars($fahrer['Telefonnummer']) ?></span>
          </div>
          <div class="card-item">
            <div class="item-header">
              <div class="item-icon"><i class="fa-solid fa-envelope"></i></div>
              <span class="item-label">E-Mail</span>
            </div>
            <span class="item-value"><a href="mailto:<?= htmlspecialchars($fahrer['Email']) ?>"><?= htmlspecialchars($fahrer['Email']) ?></a></span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2><i class="fa-solid fa-location-dot"></i> Adresse</h2>
        </div>
        <div class="card-list">
          <div class="card-item">
            <div class="item-header">
              <div class="item-icon"><i class="fa-solid fa-road"></i></div>
              <span class="item-label">Straße</span>
            </div>
            <span class="item-value"><?= htmlspecialchars($fahrer['Strasse']) ?> <?= htmlspecialchars($fahrer['Hausnummer']) ?></span>
          </div>
          <div class="card-item">
            <div class="item-header">
              <div class="item-icon"><i class="fa-solid fa-city"></i></div>
              <span class="item-label">Ort</span>
            </div>
            <span class="item-value"><?= htmlspecialchars($fahrer['PLZ']) ?> <?= htmlspecialchars($fahrer['Ort']) ?></span>
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-header">
          <h2><i class="fa-solid fa-id-card"></i> Dokumente</h2>
        </div>
        <div class="card-list">
          <div class="card-item">
            <div class="item-header">
              <div class="item-icon"><i class="fa-solid fa-passport"></i></div>
              <span class="item-label">Führerscheinnummer</span>
            </div>
            <span class="item-value"><?= htmlspecialchars($fahrer['Fuehrerscheinnummer']) ?></span>
            <div class="validity-meta">
              <span class="validity-date">Gültig bis <?= htmlspecialchars(formatDatum($fahrer['FuehrerscheinGueltigkeit'] ?? null)) ?></span>
              <span class="validity-chip <?= $fuehrerscheinStatus['badgeClass'] ?>">
                <i class="fa-solid <?= $fuehrerscheinStatus['icon'] ?>"></i>
                <?= htmlspecialchars(mb_convert_case($fuehrerscheinStatus['status'], MB_CASE_TITLE, 'UTF-8')) ?>
              </span>
              <span class="validity-date"><?= htmlspecialchars($fuehrerscheinStatus['text']) ?></span>
            </div>
          </div>
          <div class="card-item">
            <div class="item-header">
              <div class="item-icon"><i class="fa-solid fa-taxi"></i></div>
              <span class="item-label">P-Schein</span>
            </div>
            <span class="item-value">Gültig bis <?= htmlspecialchars(formatDatum($fahrer['PScheinGueltigkeit'] ?? null)) ?></span>
            <div class="validity-meta">
              <span class="validity-chip <?= $pscheinStatus['badgeClass'] ?>">
                <i class="fa-solid <?= $pscheinStatus['icon'] ?>"></i>
                <?= htmlspecialchars(mb_convert_case($pscheinStatus['status'], MB_CASE_TITLE, 'UTF-8')) ?>
              </span>
              <span class="validity-date"><?= htmlspecialchars($pscheinStatus['text']) ?></span>
            </div>
          </div>
        </div>
      </div>
    </div>

      <div class="card">
      <div class="card-header">
        <h2><i class="fa-solid fa-bullseye"></i> Meine Umsatzziele</h2>
      </div>
      <div class="card-body">
        <form action="process_ziele_update.php" method="post" class="goals-form">
          <div class="card-list">
            <div class="card-item">
              <div class="item-header">
                <div class="item-icon"><i class="fa-solid fa-sun"></i></div>
                <span class="item-label">Ziel pro Schicht (EUR)</span>
              </div>
              <input
                type="number"
                step="0.01"
                min="0"
                name="standard_schichtziel"
                class="form-control"
                value="<?= htmlspecialchars($fahrer['standard_schichtziel'] ?? '') ?>"
                placeholder="z. B. 300,00"
              >
            </div>

            <div class="card-item">
              <div class="item-header">
                <div class="item-icon"><i class="fa-solid fa-calendar-days"></i></div>
                <span class="item-label">Ziel pro Monat (EUR)</span>
              </div>
              <input
                type="number"
                step="0.01"
                min="0"
                name="standard_monatsziel"
                class="form-control"
                value="<?= htmlspecialchars($fahrer['standard_monatsziel'] ?? '') ?>"
                placeholder="z. B. 7.500,00"
              >
            </div>
          </div>

          <button type="submit" class="btn btn-primary btn-block">
            <i class="fa-solid fa-floppy-disk"></i>
            Ziele speichern
          </button>
        </form>

        <p class="helper-text">
          <i class="fa-regular fa-circle-question"></i>
          Diese Ziele sind nur für dich sichtbar und dienen als persönliche Orientierung.
          Es gibt keine Kontrolle oder Bewertung – du legst deine Ziele selbst fest.
        </p>
      </div>
    </div>

    <div class="action-bar">
      <button class="btn btn-primary" type="button" onclick="openModal('urlaubModal')">
        <i class="fa-solid fa-plane-departure"></i>
        Urlaub beantragen
      </button>
      <?php /* Anzeige der Urlaubstage vorübergehend deaktiviert, bis sie manuell validiert wurde. */ ?>
    </div>

    <section class="abwesenheit-section">
      <div class="card">
        <div class="card-header">
          <h2><i class="fa-regular fa-calendar-check"></i> Meine Abwesenheiten</h2>
        </div>
        <?php if (!empty($abwesenheiten)): ?>
          <div class="filter-bar">
            <select id="filter-art">
              <option value="">Alle Arten</option>
              <option value="urlaub">Urlaub</option>
              <option value="krankheit">Krankheit</option>
              <option value="sonstiges">Sonstiges</option>
            </select>
            <select id="filter-status">
              <option value="">Alle Status</option>
              <option value="genehmigt">Genehmigt</option>
              <option value="beantragt">Beantragt</option>
              <option value="abgelehnt">Abgelehnt</option>
              <option value="unbekannt">Nicht gesetzt</option>
            </select>
            <select id="filter-sort">
              <option value="desc">Neueste zuerst</option>
              <option value="asc">Älteste zuerst</option>
            </select>
          </div>

          <div class="timeline-wrapper" data-timeline>
            <span class="timeline-heading">Bevorstehend</span>
            <div class="timeline" id="timeline-future">
              <?php foreach ($abwesenheitenZukunft as $eintrag): ?>
                <?php
                  $status = $eintrag['status'] ?? '';
                  $statusSlug = statusSlug($status);
                  $statusLabel = statusLabel($status);
                  $badgeClass = statusBadgeClass($status);
                  $start = new DateTime($eintrag['startdatum']);
                  $ende = new DateTime($eintrag['enddatum']);
                  $dauer = $start->diff($ende)->days + 1;
                  $icon = $abwesenheitIcons[$eintrag['abwesenheitsart']] ?? 'fa-calendar-day';
                ?>
                <div class="timeline-item" data-art="<?= artSlug($eintrag['abwesenheitsart']) ?>" data-status="<?= $statusSlug ?>" data-range="future" data-start="<?= $start->format('Y-m-d') ?>">
                  <div class="timeline-item-header">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <span><?= htmlspecialchars($eintrag['abwesenheitsart']) ?></span>
                  </div>
                  <div class="timeline-dates">von <?= $start->format('d.m.Y') ?> bis <?= $ende->format('d.m.Y') ?> · <?= $dauer ?> <?= $dauer === 1 ? 'Tag' : 'Tage' ?></div>
                  <?php if (!empty($eintrag['grund'])): ?>
                    <div><?= htmlspecialchars($eintrag['grund']) ?></div>
                  <?php endif; ?>
                  <div class="timeline-meta">
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

            <span class="timeline-heading">Vergangene</span>
            <div class="timeline" id="timeline-past">
              <?php foreach ($abwesenheitenVergangenheit as $eintrag): ?>
                <?php
                  $status = $eintrag['status'] ?? '';
                  $statusSlug = statusSlug($status);
                  $statusLabel = statusLabel($status);
                  $badgeClass = statusBadgeClass($status);
                  $start = new DateTime($eintrag['startdatum']);
                  $ende = new DateTime($eintrag['enddatum']);
                  $dauer = $start->diff($ende)->days + 1;
                  $icon = $abwesenheitIcons[$eintrag['abwesenheitsart']] ?? 'fa-calendar-day';
                ?>
                <div class="timeline-item" data-art="<?= artSlug($eintrag['abwesenheitsart']) ?>" data-status="<?= $statusSlug ?>" data-range="past" data-start="<?= $start->format('Y-m-d') ?>">
                  <div class="timeline-item-header">
                    <i class="fa-solid <?= $icon ?>"></i>
                    <span><?= htmlspecialchars($eintrag['abwesenheitsart']) ?></span>
                  </div>
                  <div class="timeline-dates">von <?= $start->format('d.m.Y') ?> bis <?= $ende->format('d.m.Y') ?> · <?= $dauer ?> <?= $dauer === 1 ? 'Tag' : 'Tage' ?></div>
                  <?php if (!empty($eintrag['grund'])): ?>
                    <div><?= htmlspecialchars($eintrag['grund']) ?></div>
                  <?php endif; ?>
                  <div class="timeline-meta">
                    <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="empty-state" id="timeline-empty" style="display: none;">
            <i class="fa-regular fa-calendar"></i>
            <strong>Keine Einträge für die gewählte Filterung.</strong>
            <p>Setze die Filter zurück, um weitere Abwesenheiten einzublenden.</p>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <i class="fa-regular fa-face-smile"></i>
            <strong>Aktuell sind keine Abwesenheiten hinterlegt.</strong>
            <p>Beantrage deinen ersten Urlaub oder melde eine Abwesenheit über das Formular.</p>
          </div>
        <?php endif; ?>
      </div>
    </section>
  </main>
  
  <?php include 'bottom_nav.php'; ?>
  
  <!-- Modal für Urlaub beantragen -->
  <div id="urlaubModal" class="modal">
    <div class="modal-content">
      <span onclick="closeModal('urlaubModal')" class="close">&times;</span>
      <h2>Urlaub beantragen</h2>
      <div class="modal-feedback" id="urlaub-feedback">
        <i class="fa-solid fa-circle-info"></i>
        <div>
          <strong id="urlaub-feedback-title"></strong>
          <p id="urlaub-feedback-details"></p>
        </div>
      </div>
      <form action="process_urlaub_antrag.php" method="POST" id="urlaub-form">
        <label for="startdatum">Startdatum:</label>
        <input type="date" id="startdatum" name="startdatum" required>

        <label for="enddatum">Enddatum:</label>
        <input type="date" id="enddatum" name="enddatum" required>

        <label for="kommentar">Kommentar:</label>
        <textarea id="kommentar" name="kommentar" placeholder="Optionale Ergänzung für die Verwaltung"></textarea>

        <button type="submit" id="urlaub-submit" class="btn btn-success btn-block">
          <i class="fa-solid fa-paper-plane"></i>
          Antrag senden
        </button>
      </form>
    </div>
  </div>
  
  <script>
    function openModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.style.display = 'flex';
        document.body.classList.add('modal-open');
        const feedbackElement = document.getElementById('urlaub-feedback');
        if (feedbackElement) {
          feedbackElement.style.display = 'none';
          feedbackElement.classList.remove('error', 'success');
        }
      }
    }

    function closeModal(modalId) {
      const modal = document.getElementById(modalId);
      if (modal) {
        modal.style.display = 'none';
        document.body.classList.remove('modal-open');
      }
    }

    document.addEventListener('click', (event) => {
      const modal = document.getElementById('urlaubModal');
      if (modal && event.target === modal) {
        closeModal('urlaubModal');
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') {
        closeModal('urlaubModal');
      }
    });

    const filterArt = document.getElementById('filter-art');
    const filterStatus = document.getElementById('filter-status');
    const filterSort = document.getElementById('filter-sort');
    const futureContainer = document.getElementById('timeline-future');
    const pastContainer = document.getElementById('timeline-past');
    const timelineWrapper = document.querySelector('[data-timeline]');
    const timelineEmpty = document.getElementById('timeline-empty');
    const pageFeedback = document.getElementById('urlaub-success-banner');
	const goalsBanner = document.getElementById('goals-success-banner');
    const pageFeedbackText = document.getElementById('urlaub-success-text');
    let pageFeedbackHideTimeout = null;

    function normalizeArt(value) {
      if (!value) {
        return 'sonstiges';
      }
      value = value.toLowerCase();
      return value === 'urlaub' || value === 'krankheit' ? value : 'sonstiges';
    }

    function sortItems(container, sortOrder) {
      if (!container) {
        return;
      }
      const items = Array.from(container.querySelectorAll('.timeline-item'));
      items.sort((a, b) => {
        const aDate = new Date(a.dataset.start || 0).getTime();
        const bDate = new Date(b.dataset.start || 0).getTime();
        return sortOrder === 'asc' ? aDate - bDate : bDate - aDate;
      });
      items.forEach((item) => container.appendChild(item));
    }

    function applyFilters() {
      if (!timelineWrapper) {
        return;
      }

      const artValue = filterArt ? filterArt.value : '';
      const statusValue = filterStatus ? filterStatus.value : '';
      const sortValue = filterSort ? filterSort.value : 'desc';
      const items = timelineWrapper.querySelectorAll('.timeline-item');
      let visibleCount = 0;

      items.forEach((item) => {
        const itemArt = normalizeArt(item.dataset.art || '');
        const itemStatus = (item.dataset.status || '').toLowerCase();
        const matchesArt = !artValue || (artValue === 'sonstiges' ? itemArt === 'sonstiges' : itemArt === artValue);
        const matchesStatus = !statusValue || itemStatus === statusValue;
        const isVisible = matchesArt && matchesStatus;
        item.style.display = isVisible ? '' : 'none';
        if (isVisible) {
          visibleCount += 1;
        }
      });

      sortItems(futureContainer, sortValue);
      sortItems(pastContainer, sortValue);

      if (timelineEmpty) {
        timelineEmpty.style.display = visibleCount === 0 ? 'block' : 'none';
      }
    }

    [filterArt, filterStatus, filterSort].forEach((element) => {
      element && element.addEventListener('change', applyFilters);
    });

    applyFilters();

    if (pageFeedback && pageFeedback.classList.contains('is-visible')) {
      try {
        const url = new URL(window.location.href);
        if (url.searchParams.has('success')) {
          url.searchParams.delete('success');
          const newUrl = url.pathname + (url.search || '') + url.hash;
          window.history.replaceState({}, document.title, newUrl);
        }
      } catch (error) {
        // Ignoriere Fehler beim Aktualisieren der URL, die Anzeige des Banners bleibt davon unberührt.
      }
      pageFeedbackHideTimeout = window.setTimeout(() => {
        hidePageFeedback();
        pageFeedbackHideTimeout = null;
      }, 3000);
    }
	
	if (goalsBanner && goalsBanner.classList.contains('is-visible')) {
	  try {
		const url = new URL(window.location.href);
		if (url.searchParams.has('goals')) {
		  url.searchParams.delete('goals');
		  const newUrl = url.pathname + (url.search || '') + url.hash;
		  window.history.replaceState({}, document.title, newUrl);
		}
	  } catch (error) {}

	  window.setTimeout(() => {
		goalsBanner.classList.remove('is-visible');
		goalsBanner.setAttribute('aria-hidden', 'true');
	  }, 3000);
	}

    const urlaubForm = document.getElementById('urlaub-form');
    const feedback = document.getElementById('urlaub-feedback');
    const feedbackTitle = document.getElementById('urlaub-feedback-title');
    const feedbackDetails = document.getElementById('urlaub-feedback-details');
    const submitButton = document.getElementById('urlaub-submit');
    const initialButtonHtml = submitButton ? submitButton.innerHTML : '';

    function setFeedback(type, title, details) {
      if (!feedback || !feedbackTitle || !feedbackDetails) {
        return;
      }
      feedback.classList.remove('error', 'success');
      if (type) {
        feedback.classList.add(type);
      }
      feedbackTitle.textContent = title;
      feedbackDetails.textContent = details;
      feedback.style.display = 'flex';
    }

    function hidePageFeedback() {
      if (!pageFeedback) {
        return;
      }
      pageFeedback.classList.remove('is-visible');
      pageFeedback.setAttribute('aria-hidden', 'true');
    }

    function showPageFeedback(message) {
      if (!pageFeedback || !pageFeedbackText) {
        return;
      }

      if (pageFeedbackHideTimeout !== null) {
        window.clearTimeout(pageFeedbackHideTimeout);
      }

      pageFeedbackText.textContent = message;
      pageFeedback.classList.add('is-visible');
      pageFeedback.setAttribute('aria-hidden', 'false');
      pageFeedbackHideTimeout = window.setTimeout(() => {
        hidePageFeedback();
        pageFeedbackHideTimeout = null;
      }, 3000);
    }

    function formatDate(value) {
      if (!value) {
        return 'unbekannt';
      }
      const date = new Date(value);
      if (Number.isNaN(date.getTime())) {
        return value;
      }
      return date.toLocaleDateString('de-DE');
    }

    function formatStatus(value) {
      if (!value) {
        return 'Unbekannt';
      }
      const normalized = String(value).toLowerCase();
      return normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    if (urlaubForm) {
      urlaubForm.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (!urlaubForm.reportValidity()) {
          return;
        }

        const formData = new FormData(urlaubForm);
        if (submitButton) {
          submitButton.disabled = true;
          submitButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> wird gesendet...';
        }

        try {
          const response = await fetch('process_urlaub_antrag.php', {
            method: 'POST',
            headers: {
              'X-Requested-With': 'XMLHttpRequest',
              'Accept': 'application/json'
            },
            body: formData
          });

          if (!response.ok) {
            throw new Error('Der Server konnte den Antrag nicht verarbeiten.');
          }

          const data = await response.json();
          if (!data.success) {
            throw new Error(data.message || 'Der Antrag konnte nicht gespeichert werden.');
          }

          const start = formData.get('startdatum');
          const end = formData.get('enddatum');
          const status = data.status ? data.status : 'beantragt';
          const successMessage = `Antrag gesendet! Zeitraum: ${formatDate(start)} – ${formatDate(end)} · Status: ${formatStatus(status)}`;
          showPageFeedback(successMessage);
          urlaubForm.reset();
          closeModal('urlaubModal');
        } catch (error) {
          setFeedback('error', 'Senden fehlgeschlagen', error instanceof Error ? error.message : 'Unbekannter Fehler.');
        } finally {
          if (submitButton) {
            submitButton.disabled = false;
            submitButton.innerHTML = initialButtonHtml;
          }
        }
      });
    }
  </script>
</body>
</html>
