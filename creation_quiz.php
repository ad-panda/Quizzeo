<?php
// creation_quiz.php

require_once 'check_session.php';
require_once 'db_config.php';

// --- VÉRIFICATION DU RÔLE ---
$user_role = $_SESSION['role'];
if ($user_role !== 'ecole' && $user_role !== 'entreprise') {
    // Si l'utilisateur n'est ni École ni Entreprise, il est redirigé
    header("location: dashboard.php");
    exit;
}

$id_createur = $_SESSION['id'];
$mode_edition = false;
$quiz_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$quiz = ['titre' => 'Nouveau Quiz', 'description' => '', 'statut' => 'en_cours_ecriture'];
$questions = [];
$message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : '';
$error = isset($_GET['error']) ? htmlspecialchars($_GET['error']) : '';

// Fonction pour lier les paramètres dynamiquement
if (!function_exists('call_user_func_array')) {
    function call_user_func_array(callable $callback, array $params) {
        return $callback(...$params);
    }
}


// Si un ID de quiz est passé, on est en mode édition
if ($quiz_id) {
    // Récupérer les données du quiz et vérifier si l'utilisateur en est bien le créateur
    $sql_quiz = "SELECT titre, description, statut FROM quiz WHERE id_quiz = ? AND id_createur = ?";
    if ($stmt_quiz = $conn->prepare($sql_quiz)) {
        $stmt_quiz->bind_param("ii", $quiz_id, $id_createur);
        $stmt_quiz->execute();
        $result = $stmt_quiz->get_result();
        if ($result->num_rows == 1) {
            $quiz = $result->fetch_assoc();
            $mode_edition = true;
            
            // Récupérer les questions existantes (Exigence du PDF)
            $sql_questions = "SELECT id_question, enonce, type_question, points FROM question WHERE id_quiz = ?";
            
            if ($stmt_q = $conn->prepare($sql_questions)) {
                $stmt_q->bind_param("i", $quiz_id);
                $stmt_q->execute();
                $questions = $stmt_q->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt_q->close();
            }
        }
        $stmt_quiz->close();
    }
}

// --- LOGIQUE DE TRAITEMENT ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'save_base') {
    
    // Récupération et nettoyage des données
    $titre = trim($_POST['titre']);
    $description = trim($_POST['description']);
    $success = false;
    
    // Définition de la requête
    if ($quiz_id) {
        // Mise à jour (si en mode édition)
        $sql = "UPDATE quiz SET titre=?, description=? WHERE id_quiz=? AND id_createur=?";
        $message_success = "Quiz mis à jour.";
        $bind_types = "ssii";
        $bind_values = [&$titre, &$description, &$quiz_id, &$id_createur];
    } else {
        // Création initiale
        $sql = "INSERT INTO quiz (titre, description, id_createur, statut, date_creation) VALUES (?, ?, ?, 'en_cours_ecriture', NOW())";
        $message_success = "Quiz créé. Vous pouvez maintenant ajouter des questions.";
        $bind_types = "ssi";
        $bind_values = [&$titre, &$description, &$id_createur];
    }
    
    // Exécution de la requête
    if ($stmt_save = $conn->prepare($sql)) {
        
        $params = array_merge([$bind_types], $bind_values);
        
        if (call_user_func_array([$stmt_save, 'bind_param'], $params)) {
            
            if ($stmt_save->execute()) {
                $success = true;
                if (!$quiz_id) {
                    $quiz_id = $stmt_save->insert_id;
                    header("location: creation_quiz.php?id=" . $quiz_id . "&message=" . urlencode($message_success));
                    exit;
                }
                $message = $message_success;
            } else {
                $error = "Erreur lors de l'enregistrement du quiz: " . $stmt_save->error;
            }
        } else {
            $error = "Erreur de liaison des paramètres pour la requête SQL.";
        }
        $stmt_save->close();
    } else {
        $error = "Erreur de préparation de la requête SQL: " . $conn->error;
    }
    
    if (!$success && $quiz_id) {
         header("location: creation_quiz.php?id=" . $quiz_id . "&error=" . urlencode($error));
         exit;
    }
}
$conn->close();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Création de Quiz - Quizeo</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Utilisez les classes définies dans style.css */
        .quiz-form-container { max-width: 900px; margin: 50px auto; padding: 30px; background: white; border-radius: 8px; box-shadow: 0 0 15px rgba(0,0,0,0.1); }
        .question-list { margin-top: 20px; border-top: 2px solid #ddd; padding-top: 15px; }
        .question-card { border: 1px solid #ccc; padding: 15px; margin-bottom: 10px; border-radius: 5px; }
        .add-question-section { border: 2px dashed var(--color-primary); padding: 20px; margin-top: 30px; border-radius: 5px; background-color: #f8f9fa; }
        .error-message { color: var(--color-secondary); font-weight: bold; }
        .success-message { color: var(--color-success); font-weight: bold; }
    </style>
</head>
<body>
    <div class="quiz-form-container">
        <h1><?php echo $mode_edition ? 'Éditer le Quiz : ' . htmlspecialchars($quiz['titre']) : 'Nouveau Quiz'; ?></h1>
        <?php if (!empty($message)) echo "<p class='success-message'>$message</p>"; ?>
        <?php if (!empty($error)) echo "<p class='error-message'>$error</p>"; ?>

        <h2>Informations de Base</h2>
        <form method="POST">
            <input type="hidden" name="action" value="save_base">
            <div class="form-group">
                <label for="titre">Titre du Quiz</label>
                <input type="text" id="titre" name="titre" value="<?php echo htmlspecialchars($quiz['titre']); ?>" required>
            </div>
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars($quiz['description']); ?></textarea>
            </div>
            <button type="submit" class="btn-login">Sauvegarder les bases</button>
        </form>

        <?php if ($mode_edition): ?>
            <h2 style="margin-top: 40px;">Questions Actuelles (<?php echo count($questions); ?>)</h2>
            <div class="question-list">
                <?php if (empty($questions)): ?>
                    <p>Aucune question ajoutée pour l'instant.</p>
                <?php else: ?>
                    <?php foreach ($questions as $q): ?>
                        <div class="question-card">
                            <p><strong><?php echo htmlspecialchars($q['enonce']); ?></strong></p>
                            <small>Type: <?php echo htmlspecialchars($q['type_question']); ?></small>
                            <?php if ($q['points'] > 0): ?>
                                <small>| Points: <?php echo $q['points']; ?></small>
                            <?php endif; ?>
                            </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="add-question-section">
                <h2>Ajouter une Nouvelle Question</h2>
                <form action="traitement_question.php" method="POST">
                    <input type="hidden" name="quiz_id" value="<?php echo $quiz_id; ?>">
                    
                    <div class="form-group">
                        <label for="enonce">Énoncé de la question</label>
                        <textarea id="enonce" name="enonce" required></textarea>
                    </div>

                    <?php if ($user_role === 'entreprise'): ?>
                        <div class="form-group">
                            <label for="type_question">Type de question :</label>
                            <select id="type_question" name="type_question" onchange="toggleQuestionType(this.value)">
                                <option value="qcm">Choix Multiples (QCM)</option>
                                <option value="reponse_libre">Réponse Libre (Texte)</option>
                            </select>
                        </div>
                    <?php else: ?>
                        <input type="hidden" name="type_question" value="qcm">
                    <?php endif; ?>

                    <div id="qcm-options">
                        <h3>Options de Réponse (QCM)</h3>
                        <p>Ajoutez jusqu'à 4 options et cochez celle(s) qui est/sont correcte(s).</p>
                        <div class="form-group" style="display:flex; gap: 10px;">
                            <input type="text" name="reponses[]" placeholder="Option 1" required style="flex-grow: 1;">
                            <label style="margin: 0; font-weight: normal;"><input type="checkbox" name="correcte[]" value="0"> Correct</label>
                        </div>
                        <div class="form-group" style="display:flex; gap: 10px;">
                            <input type="text" name="reponses[]" placeholder="Option 2" required style="flex-grow: 1;">
                            <label style="margin: 0; font-weight: normal;"><input type="checkbox" name="correcte[]" value="1"> Correct</label>
                        </div>
                        <div class="form-group" style="display:flex; gap: 10px;">
                            <input type="text" name="reponses[]" placeholder="Option 3" required style="flex-grow: 1;">
                            <label style="margin: 0; font-weight: normal;"><input type="checkbox" name="correcte[]" value="2"> Correct</label>
                        </div>
                    </div>

                    <?php if ($user_role === 'ecole'): ?>
                        <div class="form-group">
                            <label for="points">Points attribués à cette question (max 10)</label>
                            <input type="number" id="points" name="points" min="1" max="10" required>
                        </div>
                    <?php endif; ?>

                    <button type="submit" name="add_question" class="btn-register">Ajouter la Question</button>
                </form>
            </div>
            
            <hr style="margin: 40px 0;">
            <p>
                <button class="btn-lancer" onclick="location.href='lancer_quiz.php?id=<?php echo $quiz_id; ?>'">
                    Passer le Quiz au statut "Lancé"
                </button>
            </p>
        <?php endif; ?>

        <p><a href="dashboard.php" class="link-switch">Retour au Dashboard</a></p>
    </div>

<script>
    function toggleQuestionType(type) {
        const qcmOptions = document.getElementById('qcm-options');
        // Cache ou montre les options QCM
        if (qcmOptions) {
            qcmOptions.style.display = (type === 'qcm') ? 'block' : 'none';
            // Rend les champs requis seulement si c'est un QCM
            qcmOptions.querySelectorAll('input[type="text"]').forEach(input => {
                input.required = (type === 'qcm');
            });
        }
    }
    
    document.addEventListener('DOMContentLoaded', () => {
        const selectElement = document.getElementById('type_question');
        if (selectElement) {
            toggleQuestionType(selectElement.value);
        }
    });
</script>
</body>
</html>