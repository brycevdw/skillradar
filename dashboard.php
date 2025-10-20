<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
include 'includes/header.php';
?>

<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Gilde Skillsradar</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="assets/img/favicon.png">
</head>

<body class="dashboard-page">

<section class="dashboard-hero">
    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1>Welkom terug, <?php echo htmlspecialchars($_SESSION['user_name']); ?></h1>
            <p>Beheer jouw resultaten, vragenlijsten en instellingen op één plek.</p>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <div class="icon-circle">
                    <img src="https://cdn-icons-png.flaticon.com/512/4149/4149675.png" alt="Resultaten Icon">
                </div>
                <h3>Mijn Resultaten</h3>
                <p>Bekijk persoonlijke voortgang met overzichtelijke grafieken en statistieken.</p>
                <a href="#" class="btn-primary">Bekijk resultaten</a>
            </div>

            <div class="dashboard-card">
                <div class="icon-circle">
                    <img src="https://cdn-icons-png.flaticon.com/512/4712/4712035.png" alt="Vragenlijst Icon">
                </div>
                <h3>Nieuwe Vragenlijst</h3>
                <p>Start een nieuwe vragenlijst om inzicht te krijgen in jouw samenwerking.</p>
                <a href="#" class="btn-primary">Start vragenlijst</a>
            </div>

            <div class="dashboard-card">
                <div class="icon-circle">
                    <img src="https://cdn-icons-png.flaticon.com/512/4149/4149671.png" alt="Team Icon">
                </div>
                <h3>Mijn Team</h3>
                <p>Bekijk resultaten van je teamleden en ontdek groeikansen samen.</p>
                <a href="#" class="btn-primary">Bekijk team</a>
            </div>

            <div class="dashboard-card">
                <div class="icon-circle">
                    <img src="https://cdn-icons-png.flaticon.com/512/4712/4712030.png" alt="Instellingen Icon">
                </div>
                <h3>Instellingen</h3>
                <p>Beheer je profiel, wachtwoord en voorkeuren binnen jouw account.</p>
                <a href="#" class="btn-primary">Open instellingen</a>
            </div>
        </div>
    </div>

    <!-- Floating shapes -->
    <div class="floating-shape shape1"></div>
    <div class="floating-shape shape2"></div>
</section>

</body>
</html>
