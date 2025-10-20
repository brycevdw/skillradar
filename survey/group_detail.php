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

$group_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($group_id <= 0) {
    header('Location: groups.php');
    exit;
}

$success = '';
$error = '';

// Update group name
if (isset($_POST['edit_group_name'])) {
    $name = trim(mysqli_real_escape_string($conn, $_POST['edit_group_name'] ?? ''));
    if ($name === '') {
        $error = 'Groepsnaam mag niet leeg zijn.';
    } else {
        mysqli_query($conn, "UPDATE `groups` SET name = '" . $name . "' WHERE id = $group_id AND created_by = $user_id");
        $success = 'Groepsnaam bijgewerkt.';
    }
}

// Add member(s) - accept single member_email or array leden[]
if (isset($_POST['member_email']) || isset($_POST['leden'])) {
    $emails = [];
    if (isset($_POST['leden']) && is_array($_POST['leden'])) {
        foreach ($_POST['leden'] as $e) {
            $e = trim(mysqli_real_escape_string($conn, $e));
            if ($e !== '') $emails[] = $e;
        }
    }
    if (isset($_POST['member_email'])) {
        $me = trim(mysqli_real_escape_string($conn, $_POST['member_email'] ?? ''));
        if ($me !== '') $emails[] = $me;
    }
    if (empty($emails)) {
        $error = 'Vul minimaal één geldig e-mailadres in.';
    } else {
        foreach ($emails as $email) {
            $userQ = mysqli_query($conn, "SELECT id FROM users WHERE email = '" . $email . "' LIMIT 1");
            if ($userQ && mysqli_num_rows($userQ) > 0) {
                $u = mysqli_fetch_assoc($userQ);
                $member_id = $u['id'];
                $already = mysqli_query($conn, "SELECT 1 FROM group_members WHERE group_id = $group_id AND user_id = $member_id");
                if (mysqli_num_rows($already) === 0) {
                    mysqli_query($conn, "INSERT INTO group_members (group_id, user_id) VALUES ($group_id, $member_id)");
                    $success = 'Lid(eren) toegevoegd.';
                } else {
                    // don't overwrite success; append to error
                    $error .= ($error ? ' ' : '') . 'Een of meer gebruikers waren al lid.';
                }
            } else {
                $error .= ($error ? ' ' : '') . 'Geen gebruiker gevonden met e-mail: ' . htmlspecialchars($email) . '.';
            }
        }
    }
}

// Remove member
if (isset($_GET['remove']) && is_numeric($_GET['remove'])) {
    $remove_id = intval($_GET['remove']);
    mysqli_query($conn, "DELETE FROM group_members WHERE group_id = $group_id AND user_id = $remove_id");
    $success = 'Lid verwijderd.';
}

// Delete group
if (isset($_GET['delete']) && $_GET['delete'] == '1') {
    mysqli_query($conn, "DELETE FROM `groups` WHERE id = $group_id AND created_by = $user_id");
    header('Location: groups.php');
    exit;
}

// Fetch group
$gQ = mysqli_query($conn, "SELECT id, name FROM `groups` WHERE id = $group_id AND created_by = $user_id LIMIT 1");
if (!$gQ || mysqli_num_rows($gQ) === 0) {
    header('Location: groups.php');
    exit;
}
$group = mysqli_fetch_assoc($gQ);

// Fetch members
$members = [];
$mQ = mysqli_query($conn, "SELECT u.id, u.email FROM group_members gm JOIN users u ON gm.user_id = u.id WHERE gm.group_id = $group_id");
while ($r = mysqli_fetch_assoc($mQ)) $members[] = $r;
?>
<link rel="stylesheet" href="../assets/css/style.css">
<div class="auth-container">
    <a href="groups.php" class="btn-link" style="display:inline-block;margin-bottom:1em;color:#1a73e8;font-weight:600">← Terug naar groepen</a>
    <div class="group-card">
        <h2 style="margin-top:0;color:#1a73e8">Groep bewerken</h2>
        <?php if ($success): ?><div class="success-msg"><?= htmlspecialchars($success) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="error-msg"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <form method="post" onsubmit="return validateGroupForm();" class="form-vertical">
            <div>
                <label for="edit_group_name" style="font-weight:600;color:#1a73e8">Groepsnaam</label>
                <input type="text" name="edit_group_name" id="edit_group_name" value="<?= htmlspecialchars($group['name']) ?>" required class="input-field">
            </div>

            <div>
                <label style="font-weight:600;color:#1a73e8">Leden</label>
                <ul class="leden-list">
                    <?php foreach ($members as $m): ?>
                        <li>
                            <span><?= htmlspecialchars($m['email']) ?></span>
                            <a href="?remove=<?= $m['id'] ?>" onclick="return confirm('Lid verwijderen?')" class="btn-danger" style="margin-left:auto">Verwijder</a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div>
                <label style="font-weight:600;color:#1a73e8">Leden toevoegen (e-mailadressen)</label>
                <div id="leden-inputs" class="leden-inputs">
                    <div class="leden-input-row"><input type="email" name="leden[]" placeholder="E-mail van lid" class="input-field"><button type="button" onclick="addLidInput(this)">+</button></div>
                </div>
            </div>

            <div style="display:flex;gap:1em;justify-content:flex-end;">
                <button type="submit" class="btn-primary">Opslaan</button>
                <a href="?delete=1" onclick="return confirm('Weet je zeker dat je deze groep wilt verwijderen?')" class="btn-danger">Verwijder groep</a>
            </div>
        </form>
    </div>
</div>

<script>
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
    return true; // geen extra vereisten bij edit
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php';
