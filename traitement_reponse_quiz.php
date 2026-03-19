<?php
// traitement_reponse_quiz.php

require_once 'check_session.php';
require_once 'db_config.php';

$id_utilisateur = $_SESSION['id'];
$quiz_id = isset($_POST['quiz_id']) ? (int)$_POST['quiz_id'] : 0;
$reponses_qcm = isset($_POST['reponse_qcm']) ? $_POST['reponse_qcm'] : [];
$reponses_texte = isset($_POST['reponse_texte']) ? $_POST['reponse_texte'] : [];

$error = '';
$redirect_url = 'utilisateur_home.php';

if ($quiz_id > 0) {
    
    // DÉMARRAGE DE LA TRANSACTION
    $conn->begin_transaction();

    // 1. Détermination du rôle du créateur et vérification si l'utilisateur a déjà répondu
    $sql_quiz_info = "
        SELECT 
            u.role as createur_role
        FROM quiz q 
        JOIN utilisateur u ON q.id_createur = u.id_utilisateur 
        WHERE q.id_quiz = ?";

    if ($stmt_info = $conn->prepare($sql_quiz_info)) {
        $stmt_info->bind_param("i", $quiz_id);
        $stmt_info->execute();
        $quiz_info = $stmt_info->get_result()->fetch_assoc();
        $stmt_info->close();
        
        $createur_role = $quiz_info['createur_role'];
        
        // 2. Récupération des corrections (Questions, Points, Réponses correctes)
        $sql_correction = "
            SELECT 
                q.id_question, q.type_question, q.points, rp.id_reponse_proposee, rp.est_correcte
            FROM question q
            LEFT JOIN reponse_proposee rp ON q.id_question = rp.id_question
            WHERE q.id_quiz = ?
            ORDER BY q.id_question;
        ";
        
        $user_score = 0;
        $total_points = 0;
        $questions_data = []; // Pour stocker les détails de correction et les réponses utilisateur

        if ($stmt_corr = $conn->prepare($sql_correction)) {
            $stmt_corr->bind_param("i", $quiz_id);
            $stmt_corr->execute();
            $result_corr = $stmt_corr->get_result();

            while ($row = $result_corr->fetch_assoc()) {
                $qid = $row['id_question'];
                
                if (!isset($questions_data[$qid])) {
                    $questions_data[$qid] = [
                        'type' => $row['type_question'],
                        'points' => $row['points'],
                        'correct_answers' => [],
                        'submitted_value' => null, // Stocke l'ID de réponse choisie ou le texte
                        'est_correct' => null // Pour l'enregistrement détaillé
                    ];
                    // Calcul du total des points
                    if ($createur_role === 'ecole') {
                        $total_points += $row['points'];
                    }
                }
                if ($row['type_question'] === 'qcm' && $row['est_correcte']) {
                    $questions_data[$qid]['correct_answers'][] = $row['id_reponse_proposee'];
                }
            }
            $stmt_corr->close();
        }

        $correct_qcm_count = 0;
        $qcm_question_count = 0;

        // 3. CALCUL DES POINTS ET MISE EN PLACE DES DONNÉES D'INSERTION
        foreach ($questions_data as $qid => &$data) {
            
            if ($data['type'] === 'qcm' && isset($reponses_qcm[$qid])) {
                $qcm_question_count++;
                $submitted_rid = (int)$reponses_qcm[$qid];
                $data['submitted_value'] = $submitted_rid;
                
                // Vérification si la réponse est correcte
                if (in_array($submitted_rid, $data['correct_answers'])) {
                    $data['est_correct'] = 1;
                    $correct_qcm_count++;
                    if ($createur_role === 'ecole') {
                        $user_score += $data['points'];
                    }
                } else {
                    $data['est_correct'] = 0;
                }

            } elseif ($data['type'] === 'reponse_libre' && isset($reponses_texte[$qid])) {
                // Pour les réponses libres (Entreprise), on stocke juste le texte
                $data['submitted_value'] = trim($reponses_texte[$qid]);
                $data['est_correct'] = null; // Correction manuelle
            }
        }
        unset($data);

        // 4. Conversion du score total (si école)
        $note_sur_10 = null;
        if ($createur_role === 'ecole' && $total_points > 0) {
            // Remise du score sur 10 (avec deux décimales)
            $note_sur_10 = round(($user_score / $total_points) * 10, 2);
        }
        
        // Calcul du pourcentage de réussite (utile pour Entreprise, et pour l'info générale)
        $pourcentage_reussi = ($qcm_question_count > 0) ? round(($correct_qcm_count / $qcm_question_count) * 100, 1) : 0;


        // 5. Insertion dans resultat_utilisateur (Résultat Global)
        $sql_res = "INSERT INTO resultat_utilisateur (id_utilisateur, id_quiz, date_soumission, note_totale, pourcentage_reussi) VALUES (?, ?, NOW(), ?, ?)";
        
        // Utilisation de variables temporaires pour la liaison (gestion des types FLOAT/NULL)
        $note_temp = $note_sur_10; 
        $pourcentage_temp = $pourcentage_reussi;
        
        if ($stmt_res = $conn->prepare($sql_res)) {
            // Note: On utilise 'd' (double) pour lier les valeurs flottantes
            $stmt_res->bind_param("iidd", $id_utilisateur, $quiz_id, $note_temp, $pourcentage_temp);
            
            if ($stmt_res->execute()) {
                $resultat_id = $stmt_res->insert_id;
                $stmt_res->close();
                
                // 6. Insertion des réponses détaillées (reponse_utilisateur)
                $sql_rep = "INSERT INTO reponse_utilisateur (id_resultat, id_question, reponse_texte, id_reponse_choisie, est_correct) VALUES (?, ?, ?, ?, ?)";
                $stmt_rep = $conn->prepare($sql_rep);
                
                foreach ($questions_data as $qid => $data) {
                    $id_q = $qid;
                    $rep_choisie = ($data['type'] === 'qcm') ? $data['submitted_value'] : null;
                    $rep_texte = ($data['type'] === 'reponse_libre') ? $data['submitted_value'] : null;
                    $is_correct = ($data['type'] === 'qcm') ? $data['est_correct'] : null;

                    // Les valeurs NULL doivent être gérées. On les passe en type 's' pour les rendre optionnelles.
                    // Note: C'est une simplification pour éviter les problèmes de bind_param 'i'/'d' avec NULL.
                    $type = "iissi"; 
                    
                    if ($stmt_rep->bind_param("iissi", $resultat_id, $id_q, $rep_texte, $rep_choisie, $is_correct)) {
                        if (!$stmt_rep->execute()) {
                            $error = "Erreur lors de l'enregistrement des réponses détaillées.";
                            $conn->rollback();
                            break;
                        }
                    } else {
                        $error = "Erreur de liaison des réponses détaillées.";
                        $conn->rollback();
                        break;
                    }
                }
                $stmt_rep->close();
                
                if (empty($error)) {
                    $conn->commit();
                    $message = "Vos réponses ont été enregistrées. ";
                    if ($createur_role === 'ecole') {
                         $message .= "Note obtenue : **{$note_sur_10} / 10** (sur un score total possible de {$total_points} points).";
                    } else {
                         $message .= "Merci de votre participation ! Les résultats seront analysés par l'entreprise.";
                    }
                }
                
            } else {
                $error = "Erreur lors de l'enregistrement du résultat global: " . $stmt_res->error;
                $conn->rollback();
            }
        } else {
            $error = "Erreur de préparation de la requête résultat global.";
        }
    }
} else {
    $error = "ID du quiz invalide ou aucune réponse soumise.";
}

$conn->close();

if (!empty($error)) {
    header("location: " . $redirect_url . "?error=" . urlencode($error));
} else {
    header("location: " . $redirect_url . "?message=" . urlencode($message));
}
exit;
?>