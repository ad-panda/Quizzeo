<?php
// ecole_home.php

require_once 'check_session.php';
require_once 'db_config.php';

if ($_SESSION['role'] !== 'ecole') {
    header("location: dashboard.php"); 
    exit;
}

$id_ecole = $_SESSION['id'];
$nom_ecole = $_SESSION['nom_compte'];
$quiz_list = [];

// Récupérer les quiz créés par cette école et le nombre de participations
$sql = "
    SELECT 
        q.id_quiz, q.titre, q.statut, q.est_actif, COUNT(r.id_resultat) as nb_reponses
    FROM quiz q
    LEFT JOIN resultat_utilisateur r ON q.id_quiz = r.id_quiz
    WHERE q.id_createur = ?
    GROUP BY q.id_quiz
    ORDER BY q.date_creation DESC;
";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $id_ecole);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $quiz_list[] = $row;
    }
    $stmt->close();
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard École - Quizeo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-content { max-width: 1200px; margin: 50px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .quiz-card { border: 1px solid #ccc; padding: 15px; margin-bottom: 15px; border-radius: 5px; text-align: left; }
        .btn-creer { padding: 10px 20px; background-color: #007bff; color: white; border: none; border-radius: 5px; cursor: pointer; }
    </style>
</head>
<body>
    <div class="dashboard-content">
        <h1>Tableau de Bord École 🎓</h1>
        <p>Bienvenue **<?php echo htmlspecialchars($nom_ecole); ?>**. Consultez vos quiz et les notes associées.</p>
        
        <button class="btn-creer" onclick="location.href='creation_quiz.php'">Créer un nouveau Quiz</button>
        
        <h2 style="margin-top: 30px;">Mes Quiz (<?php echo count($quiz_list); ?>)</h2>
        
        <?php if (empty($quiz_list)): ?>
            <p>Vous n'avez pas encore créé de quiz.</p>
        <?php else: ?>
            <?php foreach ($quiz_list as $quiz): ?>
                <div class="quiz-card">
                    <h3><?php echo htmlspecialchars($quiz['titre']); ?></h3>
                    <p>Statut : **<?php echo htmlspecialchars($quiz['statut']); ?>**</p>
                    <p>Nombre de réponses : **<?php echo $quiz['nb_reponses']; ?>**</p>
                    <p style="color: <?php echo $quiz['est_actif'] ? 'green' : 'red'; ?>;">
                        (Visibilité Admin: <?php echo $quiz['est_actif'] ? 'Actif' : 'Désactivé'; ?>)
                    </p>
                    
                    <?php if ($quiz['statut'] === 'en_cours_ecriture'): ?>
                        <button onclick="location.href='creation_quiz.php?id=<?php echo $quiz['id_quiz']; ?>'">Continuer l'Édition</button>
                    <?php elseif ($quiz['statut'] === 'termine'): ?>
                        <button onclick="location.href='voir_resultats_ecole.php?id=<?php echo $quiz['id_quiz']; ?>'">Voir les Notes des Étudiants</button>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <p><a href="logout.php" class="link-switch">Déconnexion</a></p>
    </div>
</body>
</html>