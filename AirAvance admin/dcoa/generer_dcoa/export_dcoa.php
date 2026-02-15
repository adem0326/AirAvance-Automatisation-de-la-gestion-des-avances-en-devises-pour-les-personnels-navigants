<?php
session_start();

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DCOA') {
    header("Location: ../auth/login.html?error=unauthorized");
    exit();
}

include '../../includes/db_connect.php';

// Force uppercase keys for Oracle compatibility
$conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

try {
    // Get mois and annee from POST request (passed from dcoa_dashboard.php)
    $selected_mois = isset($_POST['mois']) ? intval($_POST['mois']) : null;
    $selected_annee = isset($_POST['annee']) ? intval($_POST['annee']) : null;

    // Validate that both mois and annee are provided
    if (!$selected_mois || !$selected_annee || $selected_mois < 1 || $selected_mois > 12) {
        throw new Exception("Période invalide. Veuillez sélectionner une période valide.");
    }

    // 1. Fetch all personnel for selected month and year
    $personnel_sql = "SELECT MATRICULE, NOM, PRENOM, CORPS_PN, BASE, MONTANT_AVANCE, DEFICIT_CAISSE, MOIS, ANNEE
                      FROM PERSONNEL_NAVIGANT 
                      WHERE MOIS = :mois AND ANNEE = :annee
                      ORDER BY MATRICULE ASC";
    
    $stmt = $conn->prepare($personnel_sql);
    $stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
    $personnel_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Fetch exchange rate for the selected period
    $taux_sql = "SELECT VALEUR_TAUX FROM TAUX_CHANGE WHERE MOIS = :mois AND ANNEE = :annee";
    $taux_stmt = $conn->prepare($taux_sql);
    $taux_stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
    $taux_row = $taux_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taux_row) {
        throw new Exception("Aucun taux de change trouvé pour la période sélectionnée (Mois: $selected_mois, Année: $selected_annee).");
    }
    
    $exchange_rate = floatval($taux_row['VALEUR_TAUX']);

    // 3. Prepare Excel data with calculations
    $excel_rows = [];

    // Process each personnel record
    foreach ($personnel_data as $person) {
        $matricule = $person['MATRICULE'];
        $nom = $person['NOM'];
        $prenom = $person['PRENOM'];
        $corps = $person['CORPS_PN'];
        $base = $person['BASE'] ?? '--';
        $montant_avance = floatval($person['MONTANT_AVANCE'] ?? 0);
        $deficit = floatval($person['DEFICIT_CAISSE'] ?? 0);

        // Calculate based on TYPE_PN (CORPS_PN)
        if ($corps === 'PNC') {
            // For PNC: Apply rounding logic (mod 5)
            $adjusted_deficit = $deficit - fmod($deficit, 5);
            $avance_nette = $montant_avance - $adjusted_deficit;
            $avance_tnd = $avance_nette * $exchange_rate;
        } else {
            // For PNT: No deficit calculations, fixed 400 * exchange rate
            $adjusted_deficit = 0;
            $avance_nette = $montant_avance;
            $avance_tnd = 400 * $exchange_rate;
        }

        $excel_rows[] = [
            $matricule,
            $nom,
            $prenom,
            $corps,
            $base,
            $montant_avance,
            $deficit - $adjusted_deficit,
            $avance_nette,
            $avance_tnd
        ];
    }

    // 4. Generate Excel file using CSV format (Excel compatible)
    $filename = 'Export_DCOA_' . str_pad($selected_mois, 2, '0', STR_PAD_LEFT) . '_' . $selected_annee . '_' . date('Y-m-d_H-i-s') . '.csv';

    // Set headers for download
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Output CSV with UTF-8 BOM for Excel compatibility
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 (ensures Excel recognizes special characters)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Header row
    $headers = ['Matricule', 'Nom', 'Prénom', 'Corps', 'Base', 'Montant Avance', 'Déficit Arrondi', 'Avance Nette', 'Avance en TND'];
    fputcsv($output, $headers, ',');

    // Data rows
    foreach ($excel_rows as $row) {
        fputcsv($output, $row, ',');
    }

    fclose($output);
    exit();

} catch (PDOException $e) {
    header("Location: ../dcoa_dashboard.php?error=" . urlencode("Erreur lors de l'export: " . $e->getMessage()));
    exit();
} catch (Exception $e) {
    header("Location: ../dcoa_dashboard.php?error=" . urlencode("Erreur: " . $e->getMessage()));
    exit();
}
?>
