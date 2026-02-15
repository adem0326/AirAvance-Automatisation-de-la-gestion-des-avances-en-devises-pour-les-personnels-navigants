<?php
session_start();

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DCOA') {
    header("Location: ../../auth/login.html?error=unauthorized");
    exit();
}

include '../../includes/db_connect.php';

// Force uppercase keys for Oracle compatibility
$conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

$vouchers = [];
$error_message = '';
$is_single = false;

try {
    // Get month/year from POST request (passed from dcoa_dashboard.php) or GET request (individual voucher) or use current date
    $selected_mois = isset($_POST['mois']) ? intval($_POST['mois']) : (isset($_GET['mois']) ? intval($_GET['mois']) : null);
    $selected_annee = isset($_POST['annee']) ? intval($_POST['annee']) : (isset($_GET['annee']) ? intval($_GET['annee']) : null);
    
    // If mois/annee not provided via POST or GET, use current date
    if (!$selected_mois || !$selected_annee) {
        $selected_mois = date('n');
        $selected_annee = date('Y');
    }

    // Check if a specific matricule is requested
    $matricule = isset($_GET['matricule']) ? trim($_GET['matricule']) : null;
    
    if ($matricule) {
        // Single voucher mode
        $is_single = true;
        $personnel_sql = "SELECT MATRICULE, NOM, PRENOM, CORPS_PN, BASE, MONTANT_AVANCE, DEFICIT_CAISSE, MOIS, ANNEE
                          FROM PERSONNEL_NAVIGANT 
                          WHERE MATRICULE = :matricule AND MOIS = :mois AND ANNEE = :annee";
        
        $stmt = $conn->prepare($personnel_sql);
        $stmt->execute([':matricule' => $matricule, ':mois' => $selected_mois, ':annee' => $selected_annee]);
        $personnel_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($personnel_data)) {
            $error_message = "Personnel non trouvé: " . htmlspecialchars($matricule);
            throw new Exception($error_message);
        }
    } else {
        // Multiple vouchers mode - all personnel for selected month
        $personnel_sql = "SELECT MATRICULE, NOM, PRENOM, CORPS_PN, BASE, MONTANT_AVANCE, DEFICIT_CAISSE, MOIS, ANNEE
                          FROM PERSONNEL_NAVIGANT 
                          WHERE MOIS = :mois AND ANNEE = :annee
                          ORDER BY MATRICULE ASC";
        
        $stmt = $conn->prepare($personnel_sql);
        $stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
        $personnel_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($personnel_data)) {
            $error_message = "Aucun personnel trouvé pour la période " . str_pad($selected_mois, 2, '0', STR_PAD_LEFT) . "/" . $selected_annee;
            throw new Exception($error_message);
        }
    }

    // 2. Fetch exchange rate for the selected period
    $taux_sql = "SELECT VALEUR_TAUX, MOIS, ANNEE FROM TAUX_CHANGE WHERE MOIS = :mois AND ANNEE = :annee";
    $taux_stmt = $conn->prepare($taux_sql);
    $taux_stmt->execute([':mois' => $selected_mois, ':annee' => $selected_annee]);
    $taux_row = $taux_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$taux_row) {
        $error_message = "Taux de change non trouvé pour la période sélectionnée";
        throw new Exception($error_message);
    }

    $exchange_rate = floatval($taux_row['VALEUR_TAUX']);
    $taux_mois = intval($taux_row['MOIS']);
    $taux_annee = intval($taux_row['ANNEE']);

    // 3. Calculate for each personnel
    foreach ($personnel_data as $person) {
        $montant_avance = floatval($person['MONTANT_AVANCE'] ?? 0);
        $deficit = floatval($person['DEFICIT_CAISSE'] ?? 0);
        $corps = $person['CORPS_PN'];

        if ($corps === 'PNC') {
            // For PNC: Apply rounding logic - round deficit down to nearest 5 (mod 5)
            $adjusted_deficit = $deficit - fmod($deficit, 5);
            $net_devise = $montant_avance - $adjusted_deficit;
            $net_tnd = $net_devise * $exchange_rate;
        } else {
            // For PNT: No deficit calculations, fixed amount
            $adjusted_deficit = 0;
            $net_devise = $montant_avance;
            $net_tnd = $net_devise * $exchange_rate;
        }

        $vouchers[] = [
            'matricule' => $person['MATRICULE'],
            'nom' => $person['NOM'],
            'prenom' => $person['PRENOM'],
            'corps' => $person['CORPS_PN'],
            'base' => $person['BASE'] ?? '--',
            'montant_avance' => $montant_avance,
            'deficit' => $deficit,
            'adjusted_deficit' => $adjusted_deficit,
            'net_devise' => $net_devise,
            'exchange_rate' => $exchange_rate,
            'net_tnd' => $net_tnd,
            'mois' => intval($person['MOIS']),
            'annee' => intval($person['ANNEE']),
            'taux_mois' => $taux_mois,
            'taux_annee' => $taux_annee,
            'print_date' => date('d/m/Y H:i:s')
        ];
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
}

include 'generate_voucher.phtml';
