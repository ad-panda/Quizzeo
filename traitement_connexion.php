<?php
// traitement_connexion.php (SANS HACHAGE - POUR TESTS UNIQUEMENT)

session_start();

require_once 'db_config.php'; 

$login_error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $nom_compte = $conn->real_escape_string(trim($_POST['nom_compte']));
    $mot_de_passe = $_POST['mot_de_passe']; // Mot de passe en clair
    $role_choisi = $conn->real_escape_string(trim($_POST['role']));
    
    $login_error = "Nom de compte, mot de passe ou rôle incorrect.";

    $sql = "SELECT id_utilisateur, nom_compte, mot_de_passe, role, est_actif FROM utilisateur WHERE nom_compte = ? AND role = ?";
    
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $param_nom_compte, $param_role);
        $param_nom_compte = $nom_compte;
        $param_role = $role_choisi;
        
        if ($stmt->execute()) {
            $stmt->store_result();
            
            if ($stmt->num_rows == 1) {
                // $stored_password contient la chaîne en clair de la BDD (ex: "Admin2025")
                $stmt->bind_result($id, $nom_compte_db, $stored_password, $role, $est_actif);
                
                if ($stmt->fetch()) {
                    
                    // Nettoyage de la donnée BDD lue
                    $stored_password = trim($stored_password);
                    
                    // !!! VÉRIFICATION NON SÉCURISÉE : Comparaison de chaînes en clair !!!
                    if ($mot_de_passe === $stored_password) {
                        
                        if ($est_actif) {
                            // SUCCÈS
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["nom_compte"] = $nom_compte_db;
                            $_SESSION["role"] = $role;
                            
                            header("location: dashboard.php");
                            exit;
                        } else {
                            $login_error = "Votre compte a été désactivé par l'administrateur.";
                        }
                    } else {
                        $login_error = "Nom de compte, mot de passe ou rôle incorrect."; 
                    }
                }
            } else {
                $login_error = "Nom de compte, mot de passe ou rôle incorrect.";
            }
        } else {
            $login_error = "Erreur technique lors de la connexion.";
        }
        $stmt->close();
    } else {
        $login_error = "Erreur technique interne.";
    }
    
    $conn->close();

    // Affichage de l'erreur (méthode de débogage laissée en place)
    ?>
    <!DOCTYPE html>
    <html lang="fr">
    <head>
        <title>Erreur de Connexion</title>
        <link rel="stylesheet" href="style.css">
    </head>
    <body>
        <div class="form-container" style="background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;">
            <h1>Échec de la Connexion</h1>
            <p>**Erreur :** <?php echo htmlspecialchars($login_error); ?></p>
            <p>Tentez à nouveau la connexion depuis <a href="index.html">la page de connexion</a>.</p>
        </div>
    </body>
    </html>
    <?php
    exit;

} else {
    header("location: index.html");
    exit;
}
?>