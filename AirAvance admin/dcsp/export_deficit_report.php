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

$deficit_data = [];
$error_message = '';

try {
    // Get month/year from GET request
    $selected_mois = isset($_GET['mois']) ? intval($_GET['mois']) : null;
    $selected_annee = isset($_GET['annee']) ? intval($_GET['annee']) : null;
    
    if (!$selected_mois || !$selected_annee) {
        throw new Exception("Période non fournie");
    }

    // 1. Fetch deficit details for the selected period
    $deficit_sql = "SELECT 
                        dd.DEF_NUMETA,
                        dd.DEF_VOL,
                        dd.DEF_DATVOL,
                        dd.DEF_MATPNC,
                        pn.NOM,
                        pn.PRENOM,
                        pn.CORPS_PN,
                        pn.BASE,
                        dd.DEF_MOIS,
                        dd.DEF_ANNEE,
                        dd.DEF_TOTVEN,
                        dd.DEF_MONENC,
                        dd.DEF_ECART
                    FROM DEFICITS_DETAIL dd
                    LEFT JOIN PERSONNEL_NAVIGANT pn 
                        ON dd.DEF_MATPNC = pn.MATRICULE 
                        AND dd.DEF_MOIS = pn.MOIS 
                        AND dd.DEF_ANNEE = pn.ANNEE
                    WHERE dd.DEF_MOIS = :mois AND dd.DEF_ANNEE = :annee
                    ORDER BY dd.DEF_MATPNC, dd.DEF_VOL ASC";
    
    $stmt = $conn->prepare($deficit_sql);
    $stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
    $deficit_details = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($deficit_details)) {
        throw new Exception("Aucun enregistrement de déficit trouvé pour la période " . str_pad($selected_mois, 2, '0', STR_PAD_LEFT) . "/" . $selected_annee);
    }

    // 2. Fetch exchange rate for the selected period
    $taux_sql = "SELECT VALEUR_TAUX FROM TAUX_CHANGE WHERE MOIS = :mois AND ANNEE = :annee";
    $taux_stmt = $conn->prepare($taux_sql);
    $taux_stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
    $taux_row = $taux_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taux_row) {
        throw new Exception("Taux de change non trouvé pour la période sélectionnée");
    }

    $exchange_rate = floatval($taux_row['VALEUR_TAUX']);

    // 3. Calculate deficit in TND for each record
    foreach ($deficit_details as $deficit) {
        $deficit_ecart = floatval($deficit['DEF_ECART'] ?? 0);
        $deficit_tnd = $deficit_ecart * $exchange_rate;

        $deficit_data[] = [
            'num_eta' => $deficit['DEF_NUMETA'],
            'vol' => $deficit['DEF_VOL'],
            'dat_vol' => $deficit['DEF_DATVOL'],
            'matricule' => $deficit['DEF_MATPNC'],
            'nom' => $deficit['NOM'] ?? '--',
            'prenom' => $deficit['PRENOM'] ?? '--',
            'corps' => $deficit['CORPS_PN'] ?? '--',
            'base' => $deficit['BASE'] ?? '--',
            'mois' => intval($deficit['DEF_MOIS']),
            'annee' => intval($deficit['DEF_ANNEE']),
            'tot_ven' => floatval($deficit['DEF_TOTVEN'] ?? 0),
            'mon_enc' => floatval($deficit['DEF_MONENC'] ?? 0),
            'deficit_ecart' => $deficit_ecart,
            'deficit_tnd' => $deficit_tnd,
            'exchange_rate' => $exchange_rate
        ];
    }

    // Generate CSV file
    $filename = 'Rapport_Deficit_' . str_pad($selected_mois, 2, '0', STR_PAD_LEFT) . '-' . $selected_annee . '.csv';
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Create CSV file
    echo createCSVFile($deficit_data, $selected_mois, $selected_annee, $exchange_rate);
    exit;

} catch (Exception $e) {
    // If error, redirect back to dashboard with error message
    header("Location: dcsp_dashboard.php?error=" . urlencode($e->getMessage()));
    exit;
}

/**
 * Create CSV file content
 */
function createCSVFile($data, $mois, $annee, $exchange_rate) {
    $output = "";
    
    // Headers
    $output .= implode(",", [
        "N° Eta",
        "Vol",
        "Date Vol",
        "Matricule",
        "Nom & Prénom",
        "Corps",
        "Total Vendu",
        "Montant Encaissé",
        "Déficit (EUR)",
        "Déficit (TND)"
    ]) . "\n";
    
    // Data rows
    foreach ($data as $record) {
        $output .= implode(",", [
            $record['num_eta'],
            $record['vol'],
            $record['dat_vol'],
            $record['matricule'],
            $record['nom'] . ' ' . $record['prenom'],
            $record['corps'],
            $record['tot_ven'],
            $record['mon_enc'],
            $record['deficit_ecart'],
            $record['deficit_tnd']
        ]) . "\n";
    }
    
    return $output;
}
?>
