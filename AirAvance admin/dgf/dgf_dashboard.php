<?php
session_start();

// Security check: Ensure user is authenticated and has DGF role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DGF') {
    header("Location: ../auth/login.html?error=unauthorized");
    exit();
}

include '../includes/db_connect.php';
$conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

// Initialize variables
$user_name = $_SESSION['user'] ?? 'DGF';
$user_initial = strtoupper(substr($user_name, 0, 1));
$success_message = '';
$error_message = '';

// Defaults for the form
$form_mois = (int)($_GET['mois'] ?? 1);
$form_annee = (int)($_GET['annee'] ?? 2026);
$form_valeur = '';

// Handle POST actions: save (insert/update) and delete
if ($_SERVER["REQUEST_METHOD"] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'save_taux') {
        $mois = (int)($_POST['mois'] ?? 0);
        $annee = (int)($_POST['annee'] ?? 0);
        $valeur = trim($_POST['valeur_taux'] ?? '');

        // Basic validation
        if ($mois < 1 || $mois > 12) {
            $error_message = "Mois invalide.";
        } elseif ($annee < 1900 || $annee > 2100) {
            $error_message = "Année invalide.";
        } elseif (!is_numeric($valeur)) {
            $error_message = "Valeur invalide.";
        } else {
            // Normalize valeur to decimal string with dot
            $valeur = str_replace(',', '.', $valeur);

            try {
                // Check existence
                $check_sql = "SELECT COUNT(*) AS CNT FROM TAUX_CHANGE WHERE MOIS = :mois AND ANNEE = :annee";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bindParam(':mois', $mois, PDO::PARAM_INT);
                $check_stmt->bindParam(':annee', $annee, PDO::PARAM_INT);
                $check_stmt->execute();
                $cnt = (int)$check_stmt->fetchColumn();

                if ($cnt > 0) {
                    // Update
                    $update_sql = "UPDATE TAUX_CHANGE SET VALEUR_TAUX = :val WHERE MOIS = :mois AND ANNEE = :annee";
                    $upd = $conn->prepare($update_sql);
                    $upd->bindParam(':val', $valeur);
                    $upd->bindParam(':mois', $mois, PDO::PARAM_INT);
                    $upd->bindParam(':annee', $annee, PDO::PARAM_INT);
                    $upd->execute();
                    $success_message = "Taux mis à jour avec succès.";
                } else {
                    // Insert
                    $insert_sql = "INSERT INTO TAUX_CHANGE (MOIS, ANNEE, VALEUR_TAUX) VALUES (:mois, :annee, :val)";
                    $ins = $conn->prepare($insert_sql);
                    $ins->bindParam(':mois', $mois, PDO::PARAM_INT);
                    $ins->bindParam(':annee', $annee, PDO::PARAM_INT);
                    $ins->bindParam(':val', $valeur);
                    $ins->execute();
                    $success_message = "Taux ajouté avec succès.";
                }

                

            } catch (PDOException $e) {
                $error_message = "Erreur SQL: " . htmlentities($e->getMessage(), ENT_QUOTES);
            }
        }
    }

    if ($action === 'delete_taux') {
        $mois_d = (int)($_POST['mois_delete'] ?? 0);
        $annee_d = (int)($_POST['annee_delete'] ?? 0);

        if ($mois_d < 1 || $mois_d > 12 || $annee_d < 1900) {
            $error_message = "Mois/Année invalide pour la suppression.";
        } else {
            try {
                $del_sql = "DELETE FROM TAUX_CHANGE WHERE MOIS = :mois AND ANNEE = :annee";
                $del = $conn->prepare($del_sql);
                $del->bindParam(':mois', $mois_d, PDO::PARAM_INT);
                $del->bindParam(':annee', $annee_d, PDO::PARAM_INT);
                $del->execute();

                if ($del->rowCount() > 0) {
                    $success_message = "Taux supprimé avec succès.";
                } else {
                    $error_message = "Aucun enregistrement trouvé pour suppression.";
                }
            } catch (PDOException $e) {
                $error_message = "Erreur SQL: " . htmlentities($e->getMessage(), ENT_QUOTES);
            }
        }
    }
}


// Fetch history
$taux_list = [];
try {
    $list_sql = "SELECT MOIS, ANNEE, VALEUR_TAUX FROM TAUX_CHANGE ORDER BY ANNEE DESC, MOIS DESC";
    $list_stmt = $conn->query($list_sql);
    $taux_list = $list_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Erreur SQL: " . htmlentities($e->getMessage(), ENT_QUOTES);
}

include __DIR__ . '/dgf_dashboard.phtml';
exit;
