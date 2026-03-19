<?php
// traitement_question.php

require_once 'check_session.php';
require_once 'db_config.php';

$user_role = $_SESSION['role'];
$id_createur = $_SESSION['id'];

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['add_question'])) {
    
    // 1. Récupération des données communes
    $quiz_id = (int)$_POST['quiz_id'];
    $enonce = trim($_POST['enonce']);
    $type_question = trim($_POST['type_question']);
    $points = ($user_role === 'ecole' && isset($_POST['points'])) ? (int)$_POST['points'] : 0;
    
    $error = '';

    // Vérification de la propriété du quiz et du statut (doit être 'en_cours_ecriture')
    $sql_check = "SELECT statut, id_createur FROM quiz WHERE id_quiz = ?";
    if ($stmt_check = $conn->prepare($sql_check)) {
        $stmt_check->bind_param("i", $quiz_id);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();
        if ($result_check->num_rows === 0) {
            $error = "Quiz introuvable.";
        } else {
            $quiz_data = $result_check->fetch_assoc();
            if ($quiz_data['id_createur'] != $id_createur) {
                $error = "Accès non autorisé à la modification de ce quiz.";
            } elseif ($quiz_data['statut'] !== 'en_cours_ecriture') {
                 $error = "Impossible d'ajouter une question : le quiz n'est pas en cours d'écriture.";
            }
        }
        $stmt_check->close();
    }
    
    if (empty($error)) {
        // --- 2. Insertion dans la table 'question' ---
        $sql_q = "INSERT INTO question (id_quiz, enonce, type_question, points) VALUES (?, ?, ?, ?)";
        if ($stmt_q = $conn->prepare($sql_q)) {
            $stmt_q->bind_param("issi", $quiz_id, $enonce, $type_question, $points);
            
            if ($stmt_q->execute()) {
                $new_question_id = $stmt_q->insert_id;
                $stmt_q->close();

                // --- 3. Gestion des Réponses (si QCM) ---
                if ($type_question === 'qcm' && isset($_POST['reponses']) && is_array($_POST['reponses'])) {
                    
                    $reponses = $_POST['reponses'];
                    $correctes_indices = isset($_POST['correcte']) ? $_POST['correcte'] : []; // Indices des bonnes réponses
                    $has_correct_answer = false;
                    
                    $sql_r = "INSERT INTO reponse_proposee (id_question, texte_reponse, est_correcte) VALUES (?, ?, ?)";
                    $stmt_r = $conn->prepare($sql_r);

                    foreach ($reponses as $index => $texte_reponse) {
                        $texte_reponse = trim($texte_reponse);
                        if (!empty($texte_reponse)) {
                            // Détermine si cette réponse est marquée comme correcte
                            $est_correcte = in_array($index, $correctes_indices) ? 1 : 0;
                            if ($est_correcte) {
                                $has_correct_answer = true;
                            }
                            
                            $stmt_r->bind_param("isi", $new_question_id, $texte_reponse, $est_correcte);
                            $stmt_r->execute();
                        }
                    }
                    $stmt_r->close();

                    // Validation : Un QCM doit avoir au moins une réponse correcte
                    if (!$has_correct_answer) {
                        // Optionnel : Gérer l'erreur et supprimer la question si aucune bonne réponse n'est cochée
                        $error = "Erreur : Un QCM doit avoir au moins une réponse correcte. La question a été ajoutée mais sans correction.";
                    }

                } elseif ($type_question === 'qcm') {
                    $error = "Erreur: Les options de réponse pour le QCM sont manquantes.";
                }
                
                if (empty($error)) {
                     $message = "Question ajoutée avec succès.";
                }
                
            } else {
                $error = "Erreur lors de l'insertion de la question : " . $conn->error;
            }
        } else {
            $error = "Erreur de préparation de la requête question.";
        }
    }
    
    // Redirection vers la page d'édition du quiz
    $redirect_url = "creation_quiz.php?id=" . $quiz_id;
    if (!empty($error)) {
        $redirect_url .= "&error=" . urlencode($error);
    } elseif (!empty($message)) {
        $redirect_url .= "&success=" . urlencode($message);
    }
    header("location: " . $redirect_url);
    exit;
}
?>