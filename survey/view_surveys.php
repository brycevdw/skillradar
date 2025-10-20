<?php
// survey/view_surveys.php
include '../includes/header.php';
include '../includes/db.php';


// Haal alle vragenlijsten op die door de huidige gebruiker zijn aangemaakt
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login/login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

$sql = "SELECT id, title, created_at FROM surveys WHERE created_by = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

?>
<link rel="stylesheet" href="../assets/css/style.css">
<div class="container">
    <h2>Mijn Vragenlijsten</h2>
    <table class="survey-table">
        <thead>
            <tr>
                <th>Titel</th>
                <th>Aangemaakt op</th>
                <th>Acties</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['title']); ?></td>
                <td><?php echo htmlspecialchars($row['created_at']); ?></td>
                <td>
                    <a href="view_survey.php?id=<?php echo $row['id']; ?>" class="btn">Bekijk</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

