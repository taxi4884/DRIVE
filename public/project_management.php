<?php
require_once '../includes/bootstrap.php';
$title='Projektmanagement';
include __DIR__ . '/../includes/layout.php';
function esc($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}

$pdo->exec("CREATE TABLE IF NOT EXISTS pm_projects (id INT AUTO_INCREMENT PRIMARY KEY,title VARCHAR(190) NOT NULL,notes TEXT NULL,status VARCHAR(40) NOT NULL DEFAULT 'Idee',due_date DATE NULL,created_by VARCHAR(120) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS pm_project_members (id INT AUTO_INCREMENT PRIMARY KEY,project_id INT NOT NULL,member_name VARCHAR(120) NOT NULL,UNIQUE KEY uq(project_id, member_name)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS pm_tasks (id INT AUTO_INCREMENT PRIMARY KEY,project_id INT NOT NULL,title VARCHAR(190) NOT NULL,notes TEXT NULL,status VARCHAR(40) NOT NULL DEFAULT 'Offen',priority VARCHAR(20) NOT NULL DEFAULT 'Normal',due_date DATE NULL,assignee VARCHAR(120) NULL,created_by VARCHAR(120) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS pm_project_notes (id INT AUTO_INCREMENT PRIMARY KEY,project_id INT NOT NULL,note_text TEXT NOT NULL,author VARCHAR(120) NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$pdo->exec("CREATE TABLE IF NOT EXISTS pm_notifications (id INT AUTO_INCREMENT PRIMARY KEY,project_id INT NULL,target_name VARCHAR(120) NULL,message TEXT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$actor=$_SESSION['user_name']??'System';
$view=$_GET['view']??'portfolio';
$projectId=(int)($_GET['project_id']??0);
$users=$pdo->query("SELECT Name FROM Benutzer ORDER BY Name")->fetchAll(PDO::FETCH_COLUMN)?:[];

if($_SERVER['REQUEST_METHOD']==='POST'){
  $a=$_POST['action']??'';
  if($a==='create_project'){
    $pdo->prepare("INSERT INTO pm_projects(title,notes,status,due_date,created_by) VALUES(?,?,?,?,?)")->execute([trim($_POST['title']??''),trim($_POST['notes']??''),$_POST['status']??'Idee',$_POST['due_date']?:null,$actor]);
    $pid=(int)$pdo->lastInsertId();
    if(!empty($_POST['members']) && is_array($_POST['members'])){
      $ins=$pdo->prepare("INSERT IGNORE INTO pm_project_members(project_id,member_name) VALUES(?,?)");
      foreach($_POST['members'] as $m){$m=trim((string)$m); if($m!==''){ $ins->execute([$pid,$m]); }}
    }
    header('Location: ?view=project&project_id='.$pid); exit;
  }
  if($a==='create_task'){
    $pid=(int)$_POST['project_id'];
    $asg=trim($_POST['assignee']??'');
    $allowed=0;
    if($asg!==''){ $st=$pdo->prepare("SELECT COUNT(*) FROM pm_project_members WHERE project_id=? AND member_name=?"); $st->execute([$pid,$asg]); $allowed=(int)$st->fetchColumn(); }
    if($allowed===1){
      $pdo->prepare("INSERT INTO pm_tasks(project_id,title,notes,status,priority,due_date,assignee,created_by) VALUES(?,?,?,?,?,?,?,?)")->execute([$pid,trim($_POST['title']??''),trim($_POST['notes']??''),$_POST['status']??'Offen',$_POST['priority']??'Normal',$_POST['due_date']?:null,$asg,$actor]);
    }
    header('Location: ?view=project&project_id='.$pid); exit;
  }
  
  if($a==='add_project_note'){
    $pid=(int)$_POST['project_id']; $txt=trim($_POST['note_text']??'');
    if($txt!=='') $pdo->prepare("INSERT INTO pm_project_notes(project_id,note_text,author) VALUES(?,?,?)")->execute([$pid,$txt,$actor]);
    header('Location: ?view=project&project_id='.$pid); exit;
  }
  if($a==='update_project_members'){
    $pid=(int)$_POST['project_id'];
    $pdo->prepare("DELETE FROM pm_project_members WHERE project_id=?")->execute([$pid]);
    if(!empty($_POST['members']) && is_array($_POST['members'])){
      $ins=$pdo->prepare("INSERT IGNORE INTO pm_project_members(project_id,member_name) VALUES(?,?)");
      foreach($_POST['members'] as $m){$m=trim((string)$m); if($m!=='') $ins->execute([$pid,$m]); }
    }
    header('Location: ?view=project&project_id='.$pid); exit;
  }
}

$projects=$pdo->query("SELECT p.*,(SELECT COUNT(*) FROM pm_tasks t WHERE t.project_id=p.id) task_count FROM pm_projects p ORDER BY updated_at DESC")->fetchAll(PDO::FETCH_ASSOC);
$project=null;$members=[];$tasks=[];$noteRows=[];
if($projectId){
  $s=$pdo->prepare("SELECT * FROM pm_projects WHERE id=?");$s->execute([$projectId]);$project=$s->fetch(PDO::FETCH_ASSOC);
  if($project){
    $m=$pdo->prepare("SELECT member_name FROM pm_project_members WHERE project_id=? ORDER BY member_name");$m->execute([$projectId]);$members=$m->fetchAll(PDO::FETCH_COLUMN);
    $t=$pdo->prepare("SELECT * FROM pm_tasks WHERE project_id=? ORDER BY updated_at DESC");$t->execute([$projectId]);$tasks=$t->fetchAll(PDO::FETCH_ASSOC);
    $n=$pdo->prepare("SELECT * FROM pm_project_notes WHERE project_id=? ORDER BY created_at DESC");$n->execute([$projectId]);$noteRows=$n->fetchAll(PDO::FETCH_ASSOC);
  }
}
?>
<div class='container' style='max-width:1200px;margin:16px auto'>
<h1>Projektmanagement</h1>
<p><a href='?view=portfolio'>Portfolio</a></p>
<?php if($view==='portfolio'): ?>
<button onclick="document.getElementById('newProjectModal').style.display='block'">+ Neues Projekt</button>
<div id="newProjectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.35)">
  <div style="max-width:620px;background:#fff;margin:8% auto;padding:16px;border-radius:10px;position:relative">
    <button style="position:absolute;right:8px;top:8px" onclick="document.getElementById('newProjectModal').style.display='none'">✕</button>
    <h3>Neues Projekt anlegen</h3>
    <form method='post'>
      <input type='hidden' name='action' value='create_project'>
      <input name='title' placeholder='Titel' required>
      <textarea name='notes' placeholder='Projektbeschreibung' style='width:100%;height:80px'></textarea>
      <select name='status'><option>Idee</option><option>Geplant</option><option>In Umsetzung</option></select>
      <input type='date' name='due_date'>
      <br><label>Projektteam</label><br>
      <select name='members[]' multiple size='7'><?php foreach($users as $u): ?><option value='<?=esc($u)?>'><?=esc($u)?></option><?php endforeach; ?></select><br>
      <button>Projekt erstellen</button>
    </form>
  </div>
</div>
<?php foreach($projects as $p): ?><div style='border:1px solid #ddd;padding:10px;margin:8px 0;background:#fff'><b><?=esc($p['title'])?></b><br><small><?=esc($p['notes'])?></small><br><a href='?view=project&project_id=<?=(int)$p['id']?>'>Öffnen</a></div><?php endforeach; ?>
<?php endif; ?>

<?php if($view==='project' && $project): ?>
<h2><?=esc($project['title'])?></h2>
<div style='border:1px solid #ddd;padding:10px;background:#fff;margin-bottom:10px'>
<b>Projektbeschreibung</b><br>
<?= nl2br(esc($project['notes'])) ?>
</div>
<div style='display:grid;grid-template-columns:1fr 1fr;gap:12px'>
<div style='border:1px solid #ddd;padding:10px;background:#fff'>
<h3>Projektteam</h3>
<form method='post'><input type='hidden' name='action' value='update_project_members'><input type='hidden' name='project_id' value='<?= (int)$project['id'] ?>'><select name='members[]' multiple size='8'><?php foreach($users as $u): ?><option value='<?=esc($u)?>' <?=in_array($u,$members,true)?'selected':''?>><?=esc($u)?></option><?php endforeach; ?></select><br><button>Team speichern</button></form>
</div>
<div style='border:1px solid #ddd;padding:10px;background:#fff'>
<h3>Neue Projekt-Notiz</h3>
<form method='post'><input type='hidden' name='action' value='add_project_note'><input type='hidden' name='project_id' value='<?= (int)$project['id'] ?>'><textarea name='note_text' rows='4' style='width:100%' placeholder='Neue Notiz...'></textarea><br><button>Notiz hinzufügen</button></form>
<?php foreach($noteRows as $n): ?><div style='border-top:1px solid #eee;padding-top:6px;margin-top:6px'><small><?=esc($n['created_at'])?> · <?=esc($n['author'])?></small><br><?=nl2br(esc($n['note_text']))?></div><?php endforeach; ?>
</div></div>

<div style='border:1px solid #ddd;padding:10px;background:#fff;margin-top:12px'>
<h3>Aufgabe zuweisen</h3>
<form method='post'>
  <input type='hidden' name='action' value='create_task'>
  <input type='hidden' name='project_id' value='<?= (int)$project['id'] ?>'>
  <input name='title' placeholder='Aufgabe' required>
  <input name='notes' placeholder='Notiz'>
  <select name='assignee' required><option value=''>Mitarbeiter wählen</option><?php foreach($members as $m): ?><option value='<?=esc($m)?>'><?=esc($m)?></option><?php endforeach; ?></select>
  <button>Aufgabe speichern</button>
</form>
<table style='width:100%;margin-top:8px'><tr><th>Titel</th><th>Assignee</th><th>Status</th></tr><?php foreach($tasks as $t): ?><tr><td><?=esc($t['title'])?></td><td><?=esc($t['assignee'])?></td><td><?=esc($t['status'])?></td></tr><?php endforeach; ?></table>
</div>
<?php endif; ?>
</div>
</body></html>
