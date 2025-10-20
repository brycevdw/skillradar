<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

// Zorg dat gebruiker ingelogd is en docent is
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Haal rol van gebruiker op
$role = null;
$res = mysqli_query($conn, "SELECT role FROM users WHERE id = " . intval($user_id));
if ($res && mysqli_num_rows($res) > 0) {
    $row = mysqli_fetch_assoc($res);
    $role = $row['role'];
}

if ($role !== 'teacher') {
    echo '<div class="auth-container" style="max-width:800px;margin:2em auto;"><h2>Toegang geweigerd</h2><p>Alleen docenten kunnen enquêtes aanmaken.</p></div>';
    exit;
}

// Zorg dat er een 'survey' skill bestaat
$skill_id = null;
$q = mysqli_query($conn, "SELECT id FROM skills WHERE name = 'Survey' LIMIT 1");
if ($q && mysqli_num_rows($q) > 0) {
    $r = mysqli_fetch_assoc($q);
    $skill_id = $r['id'];
} else {
    mysqli_query($conn, "INSERT INTO skills (name, description) VALUES ('Survey','Automatisch aangemaakte skill voor enquêtes')");
    $skill_id = mysqli_insert_id($conn);
}

// Handle DELETE request
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $delete_id = intval($_GET['id']);
    mysqli_query($conn, "DELETE FROM surveys WHERE id = $delete_id AND created_by = $user_id");
    header('Location: survey.php?deleted=1');
    exit;
}

// Handel POST af: opslaan van survey + vragen
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim(mysqli_real_escape_string($conn, $_POST['title'] ?? ''));
    $group_id = intval($_POST['group_id'] ?? 0);
    $anonymous = isset($_POST['anonymous']) ? 1 : 0;
    $questions = $_POST['questions'] ?? [];

    $errors = [];
    if ($title === '') $errors[] = 'Vul een titel in.';
    if (empty($questions)) $errors[] = 'Voeg minimaal 1 vraag toe.';
    
    // FIX: Controleer of group_id bestaat als het niet 0 is
    if ($group_id > 0) {
        $check_group = mysqli_query($conn, "SELECT id FROM `groups` WHERE id = $group_id LIMIT 1");
        if (!$check_group || mysqli_num_rows($check_group) === 0) {
            $errors[] = 'Geselecteerde groep bestaat niet. Maak eerst een groep aan.';
        }
    }

    if (empty($errors)) {
        // FIX: Als group_id 0 is, maak dan een standaard groep aan
        if ($group_id === 0) {
            $default_group_name = "Algemene groep - " . date('Y-m-d H:i:s');
            $stmt = mysqli_prepare($conn, "INSERT INTO `groups` (name, created_by) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'si', $default_group_name, $user_id);
            mysqli_stmt_execute($stmt);
            $group_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
        }
        
        // Insert survey
        $stmt = mysqli_prepare($conn, "INSERT INTO surveys (title, group_id, created_by, anonymous) VALUES (?, ?, ?, ?)");
        mysqli_stmt_bind_param($stmt, 'siii', $title, $group_id, $user_id, $anonymous);
        mysqli_stmt_execute($stmt);
        $survey_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        // Insert questions and link to survey
        $stmtQ = mysqli_prepare($conn, "INSERT INTO questions (skill_id, question_text, question_type, question_options) VALUES (?, ?, ?, ?)");
        $stmtLink = mysqli_prepare($conn, "INSERT INTO survey_questions (survey_id, question_id) VALUES (?, ?)");
        
        $question_types = $_POST['question_types'] ?? [];
        $options = $_POST['options'] ?? [];
        
        foreach ($questions as $index => $qtext) {
            $qtext = trim($qtext);
            if ($qtext === '') continue;
            
            $qtype = $question_types[$index] ?? 'scale';
            $qoptions = null;
            
            if ($qtype === 'choice' && isset($options[$index])) {
                $qoptions = trim($options[$index]);
            }
            
            mysqli_stmt_bind_param($stmtQ, 'isss', $skill_id, $qtext, $qtype, $qoptions);
            mysqli_stmt_execute($stmtQ);
            $question_id = mysqli_insert_id($conn);
            mysqli_stmt_bind_param($stmtLink, 'ii', $survey_id, $question_id);
            mysqli_stmt_execute($stmtLink);
        }
        mysqli_stmt_close($stmtQ);
        mysqli_stmt_close($stmtLink);

        header('Location: survey.php?created=1');
        exit;
    }
}

// Haal beschikbare groepen op
$groups = [];
$gq = mysqli_query($conn, "SELECT id, name FROM `groups` WHERE created_by = $user_id ORDER BY name");
if ($gq) {
    while ($gr = mysqli_fetch_assoc($gq)) $groups[] = $gr;
}

// Haal bestaande surveys op
$existing_surveys = [];
$sq = mysqli_query($conn, "SELECT s.id, s.title, s.created_at, g.name as group_name 
                           FROM surveys s 
                           LEFT JOIN `groups` g ON s.group_id = g.id 
                           WHERE s.created_by = $user_id 
                           ORDER BY s.created_at DESC");
if ($sq) {
    while ($sr = mysqli_fetch_assoc($sq)) $existing_surveys[] = $sr;
}

?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vragenlijst Beheer - Gilde Skillsradar</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
    .scale-options {
        display: flex;
        gap: 0.5em;
        margin-top: 0.8em;
        flex-wrap: wrap;
    }

    .scale-option {
        display: flex;
        flex-direction: column;
        align-items: center;
        cursor: pointer;
    }

    .scale-option input[type="radio"] {
        display: none;
    }

    .scale-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        border: 2px solid transparent;
    }

    .scale-option:hover .scale-icon {
        background: #e3f2fd;
        transform: scale(1.1);
    }

    .scale-option input[type="radio"]:checked + .scale-icon {
        background: linear-gradient(135deg, #1a73e8, #4fc3f7);
        border-color: #1a73e8;
    }

    .scale-icon svg {
        fill: #999;
        transition: fill 0.3s ease;
    }

    .scale-option input[type="radio"]:checked + .scale-icon svg {
        fill: white;
    }

    .scale-label {
        margin-top: 0.3em;
        font-size: 0.85em;
        color: #666;
        font-weight: 500;
    }

    .boolean-options {
        display: flex;
        gap: 1em;
        margin-top: 0.8em;
    }

    .boolean-option {
        flex: 1;
        padding: 1em;
        border: 2px solid #e0e0e0;
        border-radius: 12px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 0.8em;
    }

    .boolean-option:hover {
        border-color: #1a73e8;
        background: #f8f9fa;
    }

    .boolean-option input[type="radio"] {
        display: none;
    }

    .boolean-option input[type="radio"]:checked + .boolean-content {
        color: #1a73e8;
        font-weight: 600;
    }

    .survey-list-item {
        background: white;
        padding: 1.5em;
        border-radius: 12px;
        border: 2px solid #e0e0e0;
        margin-bottom: 1em;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s ease;
    }

    .survey-list-item:hover {
        border-color: #1a73e8;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(26,115,232,0.15);
    }

    .survey-actions {
        display: flex;
        gap: 0.8em;
    }

    .btn-edit, .btn-delete {
        padding: 0.6em 1.2em;
        border-radius: 8px;
        border: none;
        cursor: pointer;
        font-weight: 500;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 0.5em;
    }

    .btn-edit {
        background: #e3f2fd;
        color: #1a73e8;
    }

    .btn-edit:hover {
        background: #1a73e8;
        color: white;
    }

    .btn-delete {
        background: #fee2e2;
        color: #dc2626;
    }

    .btn-delete:hover {
        background: #dc2626;
        color: white;
    }

    .question-item {
        background: white;
        padding: 1.5em;
        border-radius: 12px;
        border: 2px solid #e0e0e0;
        margin-bottom: 1.5em;
        transition: all 0.3s ease;
    }

    .question-item:hover {
        border-color: #1a73e8;
    }
    </style>
</head>
<body>

<main style="padding:3em 2em;background:#f9f9f9;min-height:100vh">
    <div class="auth-container" style="max-width:900px;margin:0 auto;background:white;border-radius:12px;box-shadow:0 4px 15px rgba(0,0,0,0.05);overflow:hidden">
        <div style="background:#1a73e8;padding:2em;text-align:center;position:relative">
            <h2 style="color:white;font-size:1.8em;margin-bottom:0.5em;font-weight:600">Vragenlijst beheer</h2>
            <p style="color:rgba(255,255,255,0.9);max-width:600px;margin:0 auto;font-size:0.95em">
                Maak nieuwe vragenlijsten of beheer bestaande enquêtes
            </p>
        </div>
        <div style="padding:3em 2em 2em">

        <?php if (!empty($errors)): ?>
            <div style="background:#fee2e2;border-left:4px solid #dc2626;padding:1em 1.2em;margin-bottom:2em;border-radius:8px">
                <div style="color:#dc2626;font-weight:500;margin-bottom:0.3em">Er zijn enkele problemen gevonden:</div>
                <ul style="color:#7f1d1d;margin:0;padding-left:1.5em">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['created'])): ?>
            <div style="background:#ecfdf5;border-left:4px solid #059669;padding:1.2em;margin-bottom:2em;border-radius:8px;display:flex;align-items:center;gap:1em">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                    <path d="M20 6L9 17l-5-5" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div>
                    <div style="color:#059669;font-weight:500">Vragenlijst succesvol aangemaakt</div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:1.2em;margin-bottom:2em;border-radius:8px">
                <div style="color:#92400e;font-weight:500">Vragenlijst succesvol verwijderd</div>
            </div>
        <?php endif; ?>

        <!-- Bestaande vragenlijsten -->
        <?php if (!empty($existing_surveys)): ?>
            <div style="margin-bottom:3em">
                <h3 style="color:#1a73e8;margin-bottom:1.5em;display:flex;align-items:center;gap:0.5em">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                        <path d="M3 4h14M3 10h10M3 16h14" stroke="#1a73e8" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    Mijn vragenlijsten
                </h3>
                <?php foreach ($existing_surveys as $survey): ?>
                    <div class="survey-list-item">
                        <div>
                            <h4 style="margin:0 0 0.3em;color:#1a73e8"><?php echo htmlspecialchars($survey['title']); ?></h4>
                            <p style="margin:0;color:#666;font-size:0.9em">
                                <?php echo $survey['group_name'] ? htmlspecialchars($survey['group_name']) : 'Algemene groep'; ?> • 
                                <?php echo date('d-m-Y H:i', strtotime($survey['created_at'])); ?>
                            </p>
                        </div>
                        <div class="survey-actions">
                            <a href="view_survey.php?id=<?php echo $survey['id']; ?>" class="btn-edit">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M8 2v12M2 8h12" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                </svg>
                                Bekijk
                            </a>
                            <button class="btn-delete" onclick="if(confirm('Weet je zeker dat je deze vragenlijst wilt verwijderen?')) window.location='survey.php?delete=1&id=<?php echo $survey['id']; ?>'">
                                <svg width="16" height="16" viewBox="0 0 16 16" fill="none">
                                    <path d="M2 4h12M5.333 4V2.667a1.333 1.333 0 011.334-1.334h2.666a1.333 1.333 0 011.334 1.334V4m2 0v9.333a1.333 1.333 0 01-1.334 1.334H4.667a1.333 1.333 0 01-1.334-1.334V4h9.334z" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                                Verwijder
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h3 style="color:#1a73e8;margin-bottom:1.5em;display:flex;align-items:center;gap:0.5em">
            <svg width="20" height="20" viewBox="0 0 20 20" fill="none">
                <path d="M10 4v12M4 10h12" stroke="#1a73e8" stroke-width="2" stroke-linecap="round"/>
            </svg>
            Nieuwe vragenlijst maken
        </h3>

        <form method="post">
            <div class="form-group" style="margin-bottom:2em">
                <label style="display:block;color:#444;font-weight:500;margin-bottom:0.5em">Titel vragenlijst</label>
                <input type="text" name="title" placeholder="Bijv. Tussentijdse evaluatie" required 
                    style="width:100%;padding:0.8em 1em;border:2px solid #e0e0e0;border-radius:8px;font-size:1em;transition:all 0.3s ease;background:#fff">
            </div>

            <div class="form-group" style="margin-bottom:2em">
                <label style="display:block;color:#444;font-weight:500;margin-bottom:0.5em">Selecteer groep</label>
                <select name="group_id" style="width:100%;padding:0.8em 1em;border:2px solid #e0e0e0;border-radius:8px;font-size:1em;background:#fff;cursor:pointer">
                    <option value="0">Maak nieuwe algemene groep aan</option>
                    <?php foreach ($groups as $gr): ?>
                        <option value="<?= $gr['id'] ?>"><?= htmlspecialchars($gr['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <small style="color:#666;display:block;margin-top:0.5em">
                    Heb je nog geen groep? <a href="groups.php" style="color:#1a73e8;text-decoration:underline">Maak een groep aan</a>. Er wordt automatisch een nieuwe aangemaakt als je geen groep kiest.
                </small>
            </div>

            <div class="form-group" style="margin-bottom:2em;background:#f8f9fa;padding:1.2em;border-radius:8px">
                <label style="display:flex;align-items:center;gap:0.8em;cursor:pointer">
                    <input type="checkbox" name="anonymous" checked style="width:20px;height:20px;cursor:pointer">
                    <span style="color:#444">Anonieme vragenlijst</span>
                </label>
            </div>

            <div style="margin:2.5em 0 1.5em">
                <h3 style="color:#1a73e8;font-weight:600">Vragen</h3>
            </div>
            <div id="questions-wrap"></div>

            <button type="button" id="add-question" class="btn-primary" 
                style="background:#f8f9fa;color:#1a73e8;border:2px solid #1a73e8;margin:2em 0;width:100%;padding:1em;cursor:pointer;font-weight:500;border-radius:8px">
                + Nieuwe vraag toevoegen
            </button>

            <div style="margin-top:3em;border-top:2px solid #f0f0f0;padding-top:2em;display:flex;gap:1em">
                <button type="submit" class="btn-primary" style="background:#1a73e8;color:white;border:none;padding:1em 2.5em;border-radius:12px;cursor:pointer;font-weight:500;">
                    Vragenlijst opslaan
                </button>
                <a href="../dashboard/dashboard.php" style="padding:1em 2em;border-radius:12px;background:#f0f0f0;color:#666;text-decoration:none;display:flex;align-items:center">
                    Terug naar dashboard
                </a>
            </div>
        </form>
    </div>
</main>

<script>
let questionCount = 0;

function createQuestionHTML(index) {
    return `
        <div class="question-item" data-index="${index}">
            <div style="display:flex;gap:1em;margin-bottom:1em;flex-wrap:wrap">
                <textarea name="questions[]" placeholder="Typ hier je vraag..." rows="2" required
                    style="flex-grow:1;min-width:300px;padding:1em;border-radius:8px;border:2px solid #e0e0e0;resize:vertical"></textarea>
                <select name="question_types[]" class="question-type-select" onchange="updateQuestionPreview(this)"
                    style="padding:0.8em;border:2px solid #e0e0e0;border-radius:8px;background:#fff;cursor:pointer;min-width:150px">
                    <option value="scale">Schaal (1-5)</option>
                    <option value="text">Open antwoord</option>
                    <option value="choice">Meerkeuze</option>
                    <option value="boolean">Ja/Nee</option>
                </select>
            </div>
            <div class="question-options-input" style="display:none;margin-bottom:1em">
                <input type="text" name="options[]" placeholder="Optie 1, Optie 2, Optie 3" 
                    style="width:100%;padding:0.8em;border-radius:8px;border:2px solid #e0e0e0">
                <small style="color:#666;display:block;margin-top:0.5em">Scheid opties met komma's</small>
            </div>
            <div class="question-preview" style="margin-top:1em;padding:1em;background:#f8f9fa;border-radius:8px">
                <small style="color:#666;display:block;margin-bottom:0.8em;font-weight:500">Preview:</small>
                <div class="preview-scale">
                    <div class="scale-options">
                        ${[1,2,3,4,5].map(i => `
                            <label class="scale-option">
                                <input type="radio" name="preview_${index}" disabled>
                                <div class="scale-icon">
                                    <svg width="24" height="24" viewBox="0 0 24 24">
                                        <path d="M12 2l2.4 7.4h7.6l-6 4.4 2.3 7.2-6.3-4.6-6.3 4.6 2.3-7.2-6-4.4h7.6z"/>
                                    </svg>
                                </div>
                                <span class="scale-label">${i}</span>
                            </label>
                        `).join('')}
                    </div>
                </div>
                <div class="preview-text" style="display:none">
                    <textarea disabled placeholder="Open antwoord..." rows="3" style="width:100%;padding:0.8em;border-radius:8px;border:2px solid #e0e0e0;background:white"></textarea>
                </div>
                <div class="preview-choice" style="display:none">
                    <div style="color:#666;font-style:italic">Meerkeuze opties verschijnen hier nadat je ze invult...</div>
                </div>
                <div class="preview-boolean" style="display:none">
                    <div class="boolean-options">
                        <label class="boolean-option">
                            <input type="radio" name="bool_preview_${index}" disabled>
                            <div class="boolean-content" style="font-size:1.1em">✓ Ja</div>
                        </label>
                        <label class="boolean-option">
                            <input type="radio" name="bool_preview_${index}" disabled>
                            <div class="boolean-content" style="font-size:1.1em">✗ Nee</div>
                        </label>
                    </div>
                </div>
            </div>
            <button type="button" class="remove-q" onclick="removeQuestion(this)" 
                style="background:#fee2e2;color:#dc2626;border:none;padding:0.6em 1em;border-radius:8px;cursor:pointer;margin-top:1em;font-weight:500">
                Verwijder vraag
            </button>
        </div>
    `;
}

function updateQuestionPreview(select) {
    const item = select.closest('.question-item');
    const type = select.value;
    const optionsInput = item.querySelector('.question-options-input');
    const previews = {
        scale: item.querySelector('.preview-scale'),
        text: item.querySelector('.preview-text'),
        choice: item.querySelector('.preview-choice'),
        boolean: item.querySelector('.preview-boolean')
    };

    // Hide all previews
    Object.values(previews).forEach(p => p.style.display = 'none');
    
    // Show relevant preview
    if (previews[type]) previews[type].style.display = 'block';
    
    // Show/hide options input for multiple choice
    optionsInput.style.display = type === 'choice' ? 'block' : 'none';
}

function removeQuestion(btn) {
    const item = btn.closest('.question-item');
    item.style.opacity = '0';
    item.style.transform = 'translateX(20px)';
    item.style.transition = 'all 0.3s ease';
    setTimeout(() => item.remove(), 300);
}

document.getElementById('add-question').addEventListener('click', function() {
    const wrap = document.getElementById('questions-wrap');
    wrap.insertAdjacentHTML('beforeend', createQuestionHTML(questionCount++));
});

// Add initial question on page load
document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('add-question').click();
});
</script>
</body>
</html>