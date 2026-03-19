<?php
// profil.php

require_once 'check_session.php';
require_once 'db_config.php';

$id_utilisateur = $_SESSION['id'];
$message = '';

// Récupérer les informations actuelles de l'utilisateur
$user_data = [];
$sql_fetch = "SELECT nom_compte, email, nom, prenom, adresse FROM utilisateur WHERE id_utilisateur = ?";
if ($stmt_fetch = $conn->prepare($sql_fetch)) {
    $stmt_fetch->bind_param("i", $id_utilisateur);
    $stmt_fetch->execute();
    $result_fetch = $stmt_fetch->get_result();
    if ($result_fetch->num_rows == 1) {
        $user_data = $result_fetch->fetch_assoc();
    }
    $stmt_fetch->close();
}

// Traitement du formulaire de mise à jour
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_profile'])) {
    
    // Récupération des données (Nom et Prénom sont spécifiques aux simples utilisateurs)
    $nom = trim($_POST['nom']);
    $prenom = trim($_POST['prenom']);
    $email = trim($_POST['email']);
    $nom_compte = trim($_POST['nom_compte']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $update_fields = ['nom_compte' => $nom_compte, 'email' => $email, 'nom' => $nom, 'prenom' => $prenom];
    $sql_update = "UPDATE utilisateur SET nom_compte=?, email=?, nom=?, prenom=?, adresse=? WHERE id_utilisateur=?";
    $bind_types = "sssssi";
    $bind_values = [&$nom_compte, &$email, &$nom, &$prenom, &$adresse, &$id_utilisateur];
    
    // Gestion du mot de passe
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $message = "Les nouveaux mots de passe ne correspondent pas.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
            // Ajout du champ mot_de_passe à la mise à jour
            $sql_update = "UPDATE utilisateur SET nom_compte=?, email=?, nom=?, prenom=?, adresse=?, mot_de_passe=? WHERE id_utilisateur=?";
            $bind_types = "ssssssi";
            $bind_values = [&$nom_compte, &$email, &$nom, &$prenom, &$adresse, &$hashed_password, &$id_utilisateur];
        }
    }

    if (empty($message)) {
        // Exécution de la mise à jour
        if ($stmt_update = $conn->prepare($sql_update)) {
            // Utiliser call_user_func_array pour la liaison dynamique (car le nombre de variables change)
            call_user_func_array([$stmt_update, 'bind_param'], $bind_values);

            if ($stmt_update->execute()) {
                $message = "Profil mis à jour avec succès.";
                // Mettre à jour la variable de session si le nom de compte change
                $_SESSION['nom_compte'] = $nom_compte;
                // Recharger les données pour l'affichage
                // (Normalement, on devrait refaire la requête de fetch, ici on se contente de recharger)
                $user_data['nom_compte'] = $nom_compte;
                $user_data['email'] = $email;
                $user_data['nom'] = $nom;
                $user_data['prenom'] = $prenom;
            } else {
                $message = "Erreur lors de la mise à jour : " . $conn->error;
            }
            $stmt_update->close();
        }
    }
}
$conn->close();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Gestion de Profil - Quizeo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .profile-container { max-width: 600px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
    <div class="profile-container">
        <h1>Gérer mon Profil</h1>
        <?php if (!empty($message)) echo "<p style='color: green;'>$message</p>"; ?>

        <form method="POST">
            <input type="hidden" name="update_profile" value="1">

            <div class="form-group">
                <label for="nom_compte">Nom du compte / Nom d'utilisateur</label>
                <input type="text" id="nom_compte" name="nom_compte" value="<?php echo htmlspecialchars($user_data['nom_compte'] ?? ''); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Adresse Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user_data['email'] ?? ''); ?>" required>
            </div>

            <hr>
            <h3>Informations Personnelles</h3>
            <div class="form-group">
                <label for="nom">Nom</label>
                <input type="text" id="nom" name="nom" value="<?php echo htmlspecialchars($user_data['nom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="prenom">Prénom</label>
                <input type="text" id="prenom" name="prenom" value="<?php echo htmlspecialchars($user_data['prenom'] ?? ''); ?>">
            </div>
            <div class="form-group">
                <label for="adresse">Adresse</label>
                <input type="text" id="adresse" name="adresse" value="<?php echo htmlspecialchars($user_data['adresse'] ?? ''); ?>">
            </div>
            
            <hr>
            <h3>Modifier le Mot de Passe (Laisser vide si inchangé)</h3>
            <div class="form-group">
                <label for="new_password">Nouveau Mot de Passe</label>
                <input type="password" id="new_password" name="new_password">
            </div>
            <div class="form-group">
                <label for="confirm_password">Confirmer le Mot de Passe</label>
                <input type="password" id="confirm_password" name="confirm_password">
            </div>

            <button type="submit" class="btn-register">Sauvegarder les Modifications</button>
        </form>

        <p><a href="utilisateur_home.php" class="link-switch">Retour au Dashboard</a></p>
    </div>
</body>
</html>