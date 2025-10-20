<?php
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Haal rol op
$role = 'student';
$r = mysqli_query($conn, "SELECT role FROM users WHERE id = " . intval($user_id));
if ($r && mysqli_num_rows($r) > 0) {
    $row = mysqli_fetch_assoc($r);
    $role = $row['role'];
}

$survey_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

?>

<main style="padding:2em">
    <div class="auth-container" style="max-width:1000px;margin:0 auto;padding:2em">
        <h2>Resultaten vragenlijsten</h2>

        <?php if (!$survey_id): ?>
            <p>Kies een vragenlijst om resultaten te bekijken:</p>
            <ul>
                <?php
                $sr = mysqli_query($conn, "SELECT s.id, s.title FROM surveys s ORDER BY s.created_at DESC");
                while ($s = mysqli_fetch_assoc($sr)):
                ?>
                    <li><a href="?id=<?= $s['id'] ?>"><?= htmlspecialchars($s['title']) ?></a></li>
                <?php endwhile; ?>
            </ul>
        <?php else: ?>
            <?php
            // Haal vragen op die specifiek gekoppeld zijn aan deze survey
            $qres = mysqli_query($conn, "SELECT q.id, q.question_text FROM questions q JOIN survey_questions sq ON q.id = sq.question_id WHERE sq.survey_id = " . $survey_id . " ORDER BY q.id");
            $questions = [];
            while ($qr = mysqli_fetch_assoc($qres)) $questions[] = $qr;

            // Toon verschillende weergaves op basis van rol
            if ($role === 'teacher') {
                echo '<h3>Geaggregeerde resultaten</h3>';
                foreach ($questions as $q) {
                    // gemiddelde en aantal
                    $stat = mysqli_query($conn, "SELECT COUNT(*) AS cnt, AVG(score) AS avg FROM responses WHERE question_id = " . intval($q['id']) . " AND survey_id = " . $survey_id);
                    $s = mysqli_fetch_assoc($stat);
                    $cnt = $s['cnt'] ?? 0;
                    $avg = $s['avg'] ? number_format($s['avg'],2) : '-';
                    echo '<div class="feature-card" style="margin-bottom:0.8em;padding:1em">';
                    echo '<strong>' . htmlspecialchars($q['question_text']) . '</strong>';
                    echo '<p>Aantal antwoorden: ' . $cnt . ' — Gemiddelde score: ' . $avg . '</p>';
                    echo '</div>';
                }
            } else {
                echo '<h3>Jouw antwoorden</h3>';
                foreach ($questions as $q) {
                    $resp = mysqli_query($conn, "SELECT score, created_at FROM responses WHERE question_id = " . intval($q['id']) . " AND survey_id = " . $survey_id . " AND user_id = " . $user_id . " LIMIT 1");
                    $ans = mysqli_fetch_assoc($resp);
                    echo '<div class="feature-card" style="margin-bottom:0.8em;padding:1em">';
                    echo '<strong>' . htmlspecialchars($q['question_text']) . '</strong>';
                    if ($ans) {
                        echo '<p>Je score: ' . intval($ans['score']) . ' — ingediend op ' . $ans['created_at'] . '</p>';
                    } else {
                        echo '<p>Je hebt nog niet geantwoord op deze vraag.</p>';
                    }
                    echo '</div>';
                }
            }
            ?>
        <?php endif; ?>
    </div>
</main>