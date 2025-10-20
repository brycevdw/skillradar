<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Toon lijst van beschikbare enquêtes (optie: via ?id= voor direct openen)
$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $survey_id = intval($_POST['survey_id'] ?? 0);
    // For each question -> score
    $answers = $_POST['answer'] ?? [];

    // Haal survey info
    $sq = mysqli_query($conn, "SELECT anonymous FROM surveys WHERE id = " . $survey_id . " LIMIT 1");
    $an = 1;
    if ($sq && mysqli_num_rows($sq) > 0) {
        $sr = mysqli_fetch_assoc($sq);
        $an = intval($sr['anonymous']);
    }

    foreach ($answers as $question_id => $score) {
        $q_id = intval($question_id);
        $s = intval($score);
        if ($an) {
            // anonymous: user_id NULL
            $stmt = mysqli_prepare($conn, "INSERT INTO responses (survey_id, question_id, user_id, score) VALUES (?, ?, NULL, ?)");
            mysqli_stmt_bind_param($stmt, 'iii', $survey_id, $q_id, $s);
        } else {
            $stmt = mysqli_prepare($conn, "INSERT INTO responses (survey_id, question_id, user_id, score) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iiii', $survey_id, $q_id, $user_id, $s);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }

    echo '<div class="feature-card" style="margin:1em auto;max-width:800px;background:#e6fff2;color:#064a2c;padding:1em">Dank! Je antwoorden zijn opgeslagen.</div>';
}

// Als survey_id gegeven, laad vragen gekoppeld aan deze survey
$questions = [];
if ($survey_id) {
    $qres = mysqli_query($conn, "SELECT q.id, q.question_text FROM questions q JOIN survey_questions sq ON q.id = sq.question_id WHERE sq.survey_id = " . $survey_id . " ORDER BY q.id");
    if ($qres) {
        while ($qr = mysqli_fetch_assoc($qres)) $questions[] = $qr;
    }
}

?>

<main style="padding:2em">
    <div class="auth-container" style="max-width:900px;margin:0 auto;padding:2em">
        <h2>Enquêtes invullen</h2>
        <p>Kies een vragenlijst en vul deze in.</p>

        <div style="margin-top:1em">
            <h3>Beschikbare vragenlijsten</h3>
            <ul>
                <?php
                $sr = mysqli_query($conn, "SELECT s.id, s.title, g.name AS group_name FROM surveys s LEFT JOIN `groups` g ON s.group_id = g.id ORDER BY s.created_at DESC");
                while ($s = mysqli_fetch_assoc($sr)):
                ?>
                    <li style="margin-bottom:0.5em"><a href="?id=<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></a> <?= $s['group_name'] ? ' - ' . htmlspecialchars($s['group_name']) : '' ?></li>
                <?php endwhile; ?>
            </ul>
        </div>

        <?php if ($survey_id && !empty($questions)): ?>
            <form method="post" style="margin-top:1em">
                <input type="hidden" name="survey_id" value="<?= $survey_id ?>">
                <?php foreach ($questions as $q): ?>
                    <div style="margin-bottom:1em">
                        <label style="display:block;font-weight:600;"><?= htmlspecialchars($q['question_text']) ?></label>
                        <div style="display:flex;gap:0.5em;margin-top:0.5em">
                            <?php for ($i=1;$i<=5;$i++): ?>
                                <label style="background:#fff;padding:0.5em 0.8em;border-radius:8px;border:1px solid #ddd">
                                    <input type="radio" name="answer[<?= $q['id'] ?>]" value="<?= $i ?>" required> <?= $i ?>
                                </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div>
                    <button class="btn-primary" type="submit">Verzend antwoorden</button>
                </div>
            </form>
        <?php elseif ($survey_id): ?>
            <p>Geen vragen gevonden voor deze vragenlijst.</p>
        <?php endif; ?>
    </div>
</main>
