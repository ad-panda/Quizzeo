<?php
// admin_home.php

require_once 'check_session.php';
require_once 'db_config.php';

// Vérification spécifique au rôle Administrateur
if ($_SESSION['role'] !== 'administrateur') {
    header("location: dashboard.php"); 
    exit;
}

$nom_admin = $_SESSION['nom_compte'];

// 1. Récupération des Utilisateurs (sauf l'admin lui-même)
$users = [];
$sql_users = "SELECT id_utilisateur, nom_compte, role, est_actif FROM utilisateur WHERE role != 'administrateur' ORDER BY role, nom_compte";
$result_users = $conn->query($sql_users);
if ($result_users && $result_users->num_rows > 0) {
    while ($row = $result_users->fetch_assoc()) {
        $users[] = $row;
    }
}

// 2. Récupération des Quiz
$quizzes = [];
$sql_quizzes = "SELECT id_quiz, titre, statut, est_actif FROM quiz ORDER BY date_creation DESC";
$result_quizzes = $conn->query($sql_quizzes);
if ($result_quizzes && $result_quizzes->num_rows > 0) {
    while ($row = $result_quizzes->fetch_assoc()) {
        $quizzes[] = $row;
    }
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Dashboard Administrateur - Quizeo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .dashboard-content { max-width: 1200px; margin: 50px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .data-list { list-style: none; padding: 0; }
        .data-list li { padding: 10px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .action-btn { margin-left: 10px; padding: 5px 10px; border: none; border-radius: 5px; cursor: pointer; color: white; }
        .btn-activate { background-color: #28a745; }
        .btn-deactivate { background-color: #dc3545; }
    </style>
</head>
<body>
    <div class="dashboard-content">
        <h1>Tableau de Bord Administrateur 👑</h1>
        <p>Bienvenue **<?php echo htmlspecialchars($nom_admin); ?>**. Gérez les utilisateurs et les quiz de la plateforme.</p>

        <h2>Gérer les Utilisateurs (<?php echo count($users); ?>)</h2>
        <ul class="data-list">
            <?php foreach ($users as $user): ?>
            <?php
                $user_id = $user['id_utilisateur'];
                $is_active = $user['est_actif'];
                $btn_text = $is_active ? 'Désactiver' : 'Activer';
                $btn_class = $is_active ? 'btn-deactivate' : 'btn-activate';
                $action_value = $is_active ? 'deactivate' : 'activate';
            ?>
            <li>
                <span>
                    **<?php echo htmlspecialchars($user['nom_compte']); ?>** (Rôle: <?php echo htmlspecialchars($user['role']); ?>)
                    <small style="color: <?php echo $is_active ? 'green' : 'red'; ?>;">
                        [Statut: <?php echo $is_active ? 'Actif' : 'Inactif'; ?>]
                    </small>
                </span>
                <form action="traitement_admin.php" method="POST" style="display: inline;">
                    <input type="hidden" name="type" value="user">
                    <input type="hidden" name="id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="action" value="<?php echo $action_value; ?>">
                    <button type="submit" class="action-btn <?php echo $btn_class; ?>"><?php echo $btn_text; ?></button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        
        <hr>

        <h2>Gérer les Quiz (<?php echo count($quizzes); ?>)</h2>
        <ul class="data-list">
            <?php foreach ($quizzes as $quiz): ?>
            <?php
                $quiz_id = $quiz['id_quiz'];
                $is_active = $quiz['est_actif'];
                $btn_text = $is_active ? 'Désactiver' : 'Activer';
                $btn_class = $is_active ? 'btn-deactivate' : 'btn-activate';
                $action_value = $is_active ? 'deactivate' : 'activate';
            ?>
            <li>
                <span>
                    **<?php echo htmlspecialchars($quiz['titre']); ?>** (Statut: <?php echo htmlspecialchars($quiz['statut']); ?>)
                    <small style="color: <?php echo $is_active ? 'green' : 'red'; ?>;">
                        [Visibilité: <?php echo $is_active ? 'Visible' : 'Masqué'; ?>]
                    </small>
                </span>
                <form action="traitement_admin.php" method="POST" style="display: inline;">
                    <input type="hidden" name="type" value="quiz">
                    <input type="hidden" name="id" value="<?php echo $quiz_id; ?>">
                    <input type="hidden" name="action" value="<?php echo $action_value; ?>">
                    <button type="submit" class="action-btn <?php echo $btn_class; ?>"><?php echo $btn_text; ?></button>
                </form>
            </li>
            <?php endforeach; ?>
        </ul>
        
        <p><a href="logout.php" class="link-switch">Déconnexion</a></p>
    </div>
</body>
</html>