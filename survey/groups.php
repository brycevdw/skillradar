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
            $group_id = mysqli_insert_id($conn);
            $added = 0;
            $skipped = [];
            // process leden[] if provided
            if (isset($_POST['leden']) && is_array($_POST['leden'])) {
                foreach ($_POST['leden'] as $email) {
                    $email = trim(mysqli_real_escape_string($conn, $email));
                    if ($email === '') continue;
                    $userQ = mysqli_query($conn, "SELECT id FROM users WHERE email = '" . $email . "' LIMIT 1");
                    if ($userQ && mysqli_num_rows($userQ) > 0) {
                        $u = mysqli_fetch_assoc($userQ);
                        $member_id = $u['id'];
                        $already = mysqli_query($conn, "SELECT 1 FROM group_members WHERE group_id = $group_id AND user_id = $member_id");
                        if (mysqli_num_rows($already) === 0) {
                            mysqli_query($conn, "INSERT INTO group_members (group_id, user_id) VALUES ($group_id, $member_id)");
                            $added++;
                        }
                    } else {
                        $skipped[] = $email;
                    }
                }
            }
            $success = 'Groep succesvol aangemaakt!';
            if ($added > 0) $success .= ' ' . $added . ' lid(eren) toegevoegd.';
            if (!empty($skipped)) $error = 'Sommige e-mails zijn niet bekend: ' . implode(', ', $skipped) . '.';
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
<div class="auth-container">
        <h2 class="page-title">Groepen</h2>
        <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>
        <div class="tab-btns">
            <button id="tab-nieuw" class="active" onclick="showTab('nieuw')">Nieuwe groep</button>
            <button id="tab-beheer" onclick="showTab('beheer')">Groepen beheren</button>
        </div>
        <div id="content-nieuw" class="tab-content active">
            <div class="group-card">
                <form method="post" class="form-vertical" onsubmit="return validateGroupForm()">
                    <div>
                        <label for="group_name" style="font-weight:600;color:#1a73e8">Groepsnaam</label>
                        <input type="text" name="group_name" id="group_name" required class="input-field">
                    </div>
                    <div>
                        <label style="font-weight:600;color:#1a73e8">Leden toevoegen (e-mailadressen)</label>
                        <div id="leden-inputs" class="leden-inputs">
                            <div class="leden-input-row"><input type="email" name="leden[]" placeholder="E-mail van lid" class="input-field" required><button type="button" onclick="addLidInput(this)">+</button></div>
                        </div>
                    </div>
                    <div style="display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn-primary">Groep aanmaken</button>
                    </div>
                </form>
            </div>
        </div>
        <div id="content-beheer" class="tab-content">
            <div class="beheer-header"><h3 style="margin:0 0 1em 0;color:#333">Mijn groepen</h3></div>
            <?php if (empty($groups)): ?>
                <div class="group-card" style="text-align:center;color:#888">Je hebt nog geen groepen aangemaakt.</div>
            <?php else: ?>
                <ul class="groups-list" style="list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:0.9em;">
                    <?php foreach ($groups as $group): ?>
                                <li class="group-item">
                                    <a href="group_detail.php?id=<?= $group['id'] ?>" class="group-link">
                                        <div class="group-row">
                                            <div>
                                                <div class="group-name"><?= htmlspecialchars($group['name']) ?></div>
                                                <div class="group-sub"><?= count($group['leden']) ?> leden</div>
                                            </div>
                                            <div style="color:#1a73e8;font-weight:700">Bekijk</div>
                                        </div>
                                    </a>
                                </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
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
