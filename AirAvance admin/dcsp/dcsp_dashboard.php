<?php
session_start();

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DCSP') {
    header("Location: ../auth/login.html?error=unauthorized");
    exit();
}

include '../includes/db_connect.php';

// Force uppercase keys for Oracle compatibility
$conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

$user_name = $_SESSION['user'] ?? 'DCSP';
$user_initial = strtoupper(substr($user_name, 0, 1));
$success_message = $_GET['success'] ?? '';
$error_message = '';

// Period selector variables
$selected_mois = isset($_POST['mois']) ? intval($_POST['mois']) : null;
$selected_annee = isset($_POST['annee']) ? intval($_POST['annee']) : null;
$period_selected = $selected_mois !== null && $selected_annee !== null;

try {
    // If period is selected, validate that it exists in PERSONNEL_NAVIGANT
    if ($period_selected) {
        $stmt = $conn->prepare("SELECT COUNT(*) FROM PERSONNEL_NAVIGANT WHERE MOIS = :mois AND ANNEE = :annee");
        $stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
        $period_exists = $stmt->fetchColumn() > 0;
        
        if (!$period_exists) {
            $error_message = "Aucune donnée trouvée pour la période sélectionnée (Mois: " . str_pad($selected_mois, 2, '0', STR_PAD_LEFT) . ", Année: $selected_annee).";
            $period_selected = false;
        }
    }

} catch (PDOException $e) {
    $error_message = "Erreur SQL: " . $e->getMessage();
}

include 'dcsp_dashboard.phtml';
?>
