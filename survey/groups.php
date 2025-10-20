<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// --- Groep toevoegen, bewerken, verwijderen ---
$success = '';
$error = '';

// Groep verwijderen
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $group_id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM `groups` WHERE id = $group_id AND created_by = $user_id");
    $success = 'Groep verwijderd!';
}

// Groep bewerken
if (isset($_POST['edit_group_id'])) {
    $edit_id = intval($_POST['edit_group_id']);
    $edit_name = trim(mysqli_real_escape_string($conn, $_POST['edit_group_name'] ?? ''));
    if ($edit_name !== '') {
        mysqli_query($conn, "UPDATE `groups` SET name = '$edit_name' WHERE id = $edit_id AND created_by = $user_id");
        $success = 'Groep bijgewerkt!';
    } else {
        $error = 'Groepsnaam mag niet leeg zijn.';
    }
}

// Groep toevoegen
if (isset($_POST['group_name'])) {
    $group_name = trim(mysqli_real_escape_string($conn, $_POST['group_name'] ?? ''));
    if ($group_name === '') {
        $error = 'Vul een groepsnaam in.';
    } else {
        $stmt = mysqli_prepare($conn, "INSERT INTO `groups` (name, created_by) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, 'si', $group_name, $user_id);
        if (mysqli_stmt_execute($stmt)) {
            $success = 'Groep succesvol aangemaakt!';
        } else {
            $error = 'Er is iets misgegaan bij het opslaan.';
        }
        mysqli_stmt_close($stmt);
    }
}

// --- Leden toevoegen aan groep ---
if (isset($_POST['add_member_group_id']) && isset($_POST['member_email'])) {
    $group_id = intval($_POST['add_member_group_id']);
    $email = trim(mysqli_real_escape_string($conn, $_POST['member_email']));
    $userQ = mysqli_query($conn, "SELECT id FROM users WHERE email = '" . $email . "' LIMIT 1");
    if ($userQ && mysqli_num_rows($userQ) > 0) {
        $userRow = mysqli_fetch_assoc($userQ);
        $member_id = $userRow['id'];
        // Check of al lid
        $already = mysqli_query($conn, "SELECT 1 FROM group_members WHERE group_id = $group_id AND user_id = $member_id");
        if (mysqli_num_rows($already) === 0) {
            mysqli_query($conn, "INSERT INTO group_members (group_id, user_id) VALUES ($group_id, $member_id)");
            $success = 'Lid toegevoegd!';
        } else {
            $error = 'Deze gebruiker is al lid van de groep.';
        }
    } else {
        $error = 'Geen gebruiker gevonden met dit e-mailadres.';
    }
}

// --- Haal groepen en leden op ---
$groups = [];
$res = mysqli_query($conn, "SELECT id, name FROM `groups` WHERE created_by = $user_id ORDER BY name ASC");
while ($row = mysqli_fetch_assoc($res)) {
    // Haal leden op
    $leden = [];
    $ledenQ = mysqli_query($conn, "SELECT u.email FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = " . $row['id']);
    while ($l = mysqli_fetch_assoc($ledenQ)) $leden[] = $l['email'];
    $row['leden'] = $leden;
    $groups[] = $row;
}
?>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.tab-btns {
  display: flex;
  gap: 1em;
  margin-bottom: 2em;
  justify-content: center;
}
.tab-btns button {
  background: #e3f2fd;
  color: #1a73e8;
  border: none;
  padding: 0.9em 2.5em;
  border-radius: 50px;
  font-weight: 700;
  font-size: 1.1em;
  cursor: pointer;
  transition: background 0.2s;
}
.tab-btns button.active, .tab-btns button:hover {
  background: #1a73e8;
  color: #fff;
}
.tab-content { display: none; }
.tab-content.active { display: block; }
.group-card {
  background: #fff;
  border-radius: 16px;
  box-shadow: 0 4px 18px rgba(26,115,232,0.07);
  padding: 2em 2em 1.5em 2em;
  margin-bottom: 2em;
}
.leden-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.7em;
  margin: 0 0 1em 0;
  padding: 0;
  list-style: none;
}
.leden-list li {
    background: #e3f2fd;
    color: #1a73e8;
    border-radius: 8px;
    padding: 0.4em 1em;
    font-size: 0.98em;
}
.leden-inputs {
    display: flex;
    flex-direction: column;
    gap: 0.7em;
    margin-bottom: 1em;
}
.leden-input-row {
        display: flex;
        gap: 0.3em;
        align-items: center;
        position: relative;
}
.leden-input-row input[type=email] {
    flex: 1 1 200px;
    padding: 0.7em 1em;
    border-radius: 8px;
    border: 2px solid #e0e0e0;
    font-size: 1em;
    transition: border 0.2s;
    background: #f8f9fa;
}
.leden-input-row input[type=email]:focus {
    border: 2px solid #1a73e8;
    outline: none;
    background: #fff;
}
    .leden-input-row button {
        background: #1a73e8;
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 36px;
        height: 36px;
        font-size: 1.3em;
        font-weight: bold;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.2s, transform 0.2s;
        box-shadow: 0 2px 8px rgba(26,115,232,0.08);
        margin-left: 0.1em;
        margin-top: 0;
        position: relative;
        top: 0;
    }
.leden-input-row button:disabled {
    background: #b3d1f7;
    color: #fff;
    cursor: not-allowed;
}
.leden-input-row button:hover:not(:disabled) {
    background: #155fc1;
    transform: scale(1.08);
}
</style>
<div class="auth-container" style="max-width:900px;margin:2em auto;">
    <h2 style="color:#1a73e8;font-weight:700;margin-bottom:1.5em;text-align:center">Groepen</h2>
        <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="tab-btns">
            <button id="tab-nieuw" class="active" onclick="showTab('nieuw')">Nieuwe groep</button>
            <button id="tab-beheer" onclick="showTab('beheer')">Groepen beheren</button>
        </div>
        <div id="content-nieuw" class="tab-content active">
            <div class="group-card">
                <form method="post" style="display:flex;flex-direction:column;gap:1.2em;align-items:stretch;" onsubmit="return validateGroupForm()">
                    <div>
                        <label for="group_name" style="font-weight:600;color:#1a73e8">Groepsnaam</label>
                        <input type="text" name="group_name" id="group_name" required style="width:100%;padding:0.8em 1em;border-radius:8px;border:2px solid #e0e0e0;font-size:1em">
                    </div>
                    <div>
                        <label style="font-weight:600;color:#1a73e8">Leden toevoegen (e-mailadressen)</label>
                        <div id="leden-inputs" class="leden-inputs">
                            <div class="leden-input-row"><input type="email" name="leden[]" placeholder="E-mail van lid" required><button type="button" onclick="addLidInput(this)">+</button></div>
                        </div>
                    </div>
                    <button type="submit" class="btn-primary" style="padding:0.8em 2.5em;">Groep aanmaken</button>
                </form>
            </div>
        </div>
        <div id="content-beheer" class="tab-content">
            <div class="beheer-header"><h3>Mijn groepen</h3></div>
            <?php if (empty($groups)): ?>
                <div class="group-card" style="text-align:center;color:#888">Je hebt nog geen groepen aangemaakt.</div>
            <?php endif; ?>
            <?php foreach ($groups as $group): ?>
                <div class="group-card">
                    <form method="post" style="display:flex;gap:1em;align-items:center;flex-wrap:wrap;margin-bottom:1em">
                        <input type="hidden" name="edit_group_id" value="<?= $group['id'] ?>">
                        <input type="text" name="edit_group_name" value="<?= htmlspecialchars($group['name']) ?>" style="flex:1 1 200px;padding:0.7em 1em;border-radius:8px;border:2px solid #e0e0e0;font-size:1em">
                        <button type="submit" class="btn-primary" style="padding:0.6em 1.5em">Opslaan</button>
                        <a href="?delete=<?= $group['id'] ?>" onclick="return confirm('Weet je zeker dat je deze groep wilt verwijderen?')" class="btn-primary" style="background:#f44336;border:none;padding:0.6em 1.5em">Verwijder</a>
                    </form>
                    <div style="margin-bottom:0.7em;font-weight:600;color:#1a73e8">Leden:</div>
                    <ul class="leden-list">
                        <?php foreach ($group['leden'] as $lid): ?>
                            <li><?= htmlspecialchars($lid) ?></li>
                        <?php endforeach; ?>
                    </ul>
                    <form method="post" style="display:flex;gap:1em;align-items:center;flex-wrap:wrap">
                        <input type="hidden" name="add_member_group_id" value="<?= $group['id'] ?>">
                        <input type="email" name="member_email" placeholder="Voeg lid toe via e-mail" required style="flex:1 1 200px;padding:0.7em 1em;border-radius:8px;border:2px solid #e0e0e0;font-size:1em">
                        <button type="submit" class="btn-primary" style="padding:0.6em 1.5em">Toevoegen</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <script>
    function showTab(tab) {
        document.getElementById('tab-nieuw').classList.remove('active');
        document.getElementById('tab-beheer').classList.remove('active');
        document.getElementById('content-nieuw').classList.remove('active');
        document.getElementById('content-beheer').classList.remove('active');
        if(tab === 'nieuw') {
            document.getElementById('tab-nieuw').classList.add('active');
            document.getElementById('content-nieuw').classList.add('active');
        } else {
            document.getElementById('tab-beheer').classList.add('active');
            document.getElementById('content-beheer').classList.add('active');
        }
    }
    function addLidInput(btn) {
        const row = btn.parentElement;
        const clone = row.cloneNode(true);
        clone.querySelector('input').value = '';
        clone.querySelector('button').onclick = function() { removeLidInput(this); };
        clone.querySelector('button').textContent = '-';
        document.getElementById('leden-inputs').appendChild(clone);
        btn.disabled = true;
    }
    function removeLidInput(btn) {
        btn.parentElement.remove();
    }
    function validateGroupForm() {
        // Minimaal 1 lid verplicht
        const inputs = document.querySelectorAll('#leden-inputs input[type=email]');
        let filled = 0;
        inputs.forEach(i => { if(i.value.trim() !== '') filled++; });
        if(filled === 0) { alert('Voeg minimaal 1 lid toe.'); return false; }
        return true;
    }
    </script>
