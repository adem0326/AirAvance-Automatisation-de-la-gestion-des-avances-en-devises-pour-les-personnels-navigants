<?php
session_start();

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DCOA') {
    header("Location: ../auth/login.html?error=unauthorized");
    exit();
}

include '../includes/db_connect.php';

// Force uppercase keys for Oracle compatibility
$conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

$user_name = $_SESSION['user'] ?? 'DCOA';
$user_initial = strtoupper(substr($user_name, 0, 1));
$success_message = $_GET['success'] ?? '';
$error_message = '';
$warning_message = '';

// Default values for the top cards
$total_pnt = 0;
$total_pnc = 0;
$exchange_rate = '--';
$has_exchange_rate = false;
$has_dcsp = true;

// Period selector variables
$selected_mois = isset($_POST['mois']) ? intval($_POST['mois']) : null;
$selected_annee = isset($_POST['annee']) ? intval($_POST['annee']) : null;
$period_selected = $selected_mois !== null && $selected_annee !== null;
$personnel_data = [];
$can_generate_payment = false;
$period_has_taux = false;

try {
    // If period is selected, fetch Personnel for that specific period and validate TAUX_CHANGE
    if ($period_selected) {
        // 1. Count PNT for selected period
        $stmt = $conn->prepare("SELECT COUNT(*) FROM PERSONNEL_NAVIGANT WHERE CORPS_PN = 'PNT' AND MOIS = :mois AND ANNEE = :annee");
        $stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
        $total_pnt = $stmt->fetchColumn() ?: 0;

        // 2. Count PNC for selected period
        $stmt = $conn->prepare("SELECT COUNT(*) FROM PERSONNEL_NAVIGANT WHERE CORPS_PN = 'PNC' AND MOIS = :mois AND ANNEE = :annee");
        $stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
        $total_pnc = $stmt->fetchColumn() ?: 0;

        // 3. Check if the selected period has TAUX_CHANGE data
        $period_taux_sql = "SELECT COUNT(*) as cnt FROM TAUX_CHANGE WHERE MOIS = :mois AND ANNEE = :annee";
        $period_taux_stmt = $conn->prepare($period_taux_sql);
        $period_taux_stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
        $period_taux_result = $period_taux_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$period_taux_result || $period_taux_result['CNT'] == 0) {
            $warning_message = "Attention : Aucun taux de change trouvé pour la période sélectionnée (Mois: $selected_mois, Année: $selected_annee).";
            $period_has_taux = false;
            $exchange_rate = '--';
        } else {
            $period_has_taux = true;
            // Fetch the exchange rate for the selected period
            $selected_taux_sql = "SELECT VALEUR_TAUX FROM TAUX_CHANGE WHERE MOIS = :mois AND ANNEE = :annee";
            $selected_taux_stmt = $conn->prepare($selected_taux_sql);
            $selected_taux_stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
            $selected_taux_row = $selected_taux_stmt->fetch(PDO::FETCH_ASSOC);
            if ($selected_taux_row) {
                $exchange_rate = $selected_taux_row['VALEUR_TAUX'];
            }
        }

        // Fetch Personnel for the selected period
        $personnel_sql = "SELECT MATRICULE, NOM, PRENOM, CORPS_PN, BASE, DEFICIT_CAISSE, MONTANT_AVANCE, MOIS, ANNEE 
                          FROM PERSONNEL_NAVIGANT 
                          WHERE MOIS = :mois AND ANNEE = :annee
                          ORDER BY MATRICULE ASC";
        $personnel_stmt = $conn->prepare($personnel_sql);
        $personnel_stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
        $personnel_data = $personnel_stmt->fetchAll(PDO::FETCH_ASSOC);

        $can_generate_payment = !empty($personnel_data) && $period_has_taux;
    }

} catch (PDOException $e) {
    $error_message = "Erreur SQL: " . $e->getMessage();
    $personnel_data = [];
}

include 'dcoa_dashboard.phtml';
?>