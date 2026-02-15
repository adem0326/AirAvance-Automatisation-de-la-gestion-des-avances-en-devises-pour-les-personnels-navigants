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

$user_name = $_SESSION['user'] ?? 'DCOA';
$user_initial = strtoupper(substr($user_name, 0, 1));
$success_message = '';
$error_message = '';
$total_rows = 0;

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    
    // Validate file upload
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error_message = "Erreur lors du téléchargement du fichier. Code erreur: " . $file['error'];
    } else {
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Accept CSV and Excel files
        if (!in_array($file_ext, ['csv', 'xlsx', 'xls'])) {
            $error_message = "Format de fichier non valide. Veuillez utiliser CSV ou Excel.";
        } else {
            try {
                $data = parseFile($file['tmp_name'], $file_ext);
                
                if (empty($data)) {
                    $error_message = "Le fichier est vide ou n'a pas de données valides. Vérifiez que votre fichier contient au moins 2 lignes (en-têtes + données).";
                } else {
                    // Process the data
                    $total_rows = processDCOAImport($data, $conn);
                    
                    if ($total_rows > 0) {
                        // Redirect to success page
                        header("Location: ../dcoa_dashboard.php?success=true&rows=" . $total_rows);
                        exit();
                    } else {
                        $error_message = "Aucune donnée n'a pu être importée. Vérifiez le contenu du fichier.";
                    }
                }
            } catch (Exception $e) {
                $error_message = "Erreur lors du traitement du fichier: " . $e->getMessage();
            }
        }
    }
}

/**
 * Parse file and return data array
 */
function parseFile($filePath, $ext) {
    $data = [];
    
    if ($ext === 'csv') {
        if (($handle = fopen($filePath, 'r')) !== FALSE) {
            // Skip header row
            fgetcsv($handle);
            
            while (($row = fgetcsv($handle)) !== FALSE) {
                if (count($row) >= 8 && !empty($row[0])) {
                    $data[] = [
                        'Matricule' => trim($row[0]),
                        'Nom' => trim($row[1]),
                        'Prenom' => trim($row[2]),
                        'Mois' => trim($row[3]),
                        'Annee' => trim($row[4]),
                        'Corps' => trim($row[5]),
                        'Avance' => trim($row[6]),
                        'Base' => trim($row[7])
                    ];
                }
            }
            fclose($handle);
        }
    } elseif (in_array($ext, ['xlsx', 'xls'])) {
        // Handle Excel files
        $data = parseExcel($filePath, $ext);
    } else {
        throw new Exception("Format de fichier non supporté: $ext");
    }
    
    return $data;
}

/**
 * Parse Excel file (XLSX) - Using cell column positions
 */
function parseExcel($filePath, $ext) {
    $data = [];
    
    if (!file_exists($filePath)) {
        throw new Exception("Le fichier n'existe pas: $filePath");
    }
    
    if ($ext === 'xls') {
        throw new Exception("Format XLS non supporté. Veuillez utiliser XLSX ou CSV.");
    }
    
    $zip = new ZipArchive();
    if ($zip->open($filePath) !== true) {
        throw new Exception("Impossible d'ouvrir le fichier XLSX.");
    }
    
    try {
        // Get shared strings
        $sharedStrings = [];
        $stringFile = $zip->getFromName('xl/sharedStrings.xml');
        
        if ($stringFile !== false) {
            if (preg_match_all('/<t[^>]*>([^<]*)<\/t>/', $stringFile, $matches)) {
                $sharedStrings = $matches[1];
            }
        }
        
        // Get worksheet
        $worksheetFile = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($worksheetFile === false) {
            throw new Exception("Impossible de lire la feuille de calcul.");
        }
        
        $rowNum = 0;
        // Parse each row
        if (preg_match_all('/<row[^>]*r="(\d+)"[^>]*>(.*?)<\/row>/', $worksheetFile, $rowMatches, PREG_SET_ORDER)) {
            foreach ($rowMatches as $rowMatch) {
                $rowNum++;
                
                // Skip header row
                if ($rowNum === 1) {
                    continue;
                }
                
                $rowContent = $rowMatch[2];
                $rowData = [];
                
                // Parse each cell with its column reference
                if (preg_match_all('/<c[^>]*r="([A-Z]+)(\d+)"[^>]*(?:t="([^"]*)")?[^>]*>(?:<v>([^<]*)<\/v>)?<\/c>/', $rowContent, $cellMatches, PREG_SET_ORDER)) {
                    foreach ($cellMatches as $cellMatch) {
                        $colRef = $cellMatch[1];      // A, B, C, D, etc.
                        $cellType = $cellMatch[3] ?? '';
                        $cellValue = $cellMatch[4] ?? '';
                        
                        // Convert column letter to index (A=0, B=1, C=2, etc.)
                        $colIndex = 0;
                        for ($i = 0; $i < strlen($colRef); $i++) {
                            $colIndex = $colIndex * 26 + (ord($colRef[$i]) - ord('A') + 1);
                        }
                        $colIndex--; // Make it 0-based
                        
                        // Decode cell value
                        if ($cellType === 's') {
                            $cellValue = $sharedStrings[(int)$cellValue] ?? '';
                        }
                        
                        $rowData[$colIndex] = $cellValue;
                    }
                }
                
                // Ensure we have all 8 columns
                for ($i = 0; $i < 8; $i++) {
                    if (!isset($rowData[$i])) {
                        $rowData[$i] = '';
                    }
                }
                
                ksort($rowData);
                
                // Process row if first column is not empty
                if (!empty($rowData[0])) {
                    $data[] = [
                        'Matricule' => trim($rowData[0]),
                        'Nom' => trim($rowData[1]),
                        'Prenom' => trim($rowData[2]),
                        'Mois' => trim($rowData[3]),
                        'Annee' => trim($rowData[4]),
                        'Corps' => trim($rowData[5]),
                        'Avance' => trim($rowData[6]),
                        'Base' => trim($rowData[7])
                    ];
                }
            }
        }
    } finally {
        $zip->close();
    }
    
    return $data;
}

/**
 * Process DCOA import using MERGE for efficient upsert
 */
function processDCOAImport($data, $conn) {
    $insertedCount = 0;
    
    try {
        $conn->beginTransaction();
        
        // Prepare MERGE statement once for reuse
        $mergeSql = "MERGE INTO PERSONNEL_NAVIGANT pn
                     USING (SELECT ? AS MATRICULE, ? AS MOIS, ? AS ANNEE FROM DUAL) src
                     ON (pn.MATRICULE = src.MATRICULE AND pn.MOIS = src.MOIS AND pn.ANNEE = src.ANNEE)
                     WHEN MATCHED THEN
                        UPDATE SET NOM = ?, PRENOM = ?, BASE = ?, CORPS_PN = ?, MONTANT_AVANCE = ?
                     WHEN NOT MATCHED THEN
                        INSERT (MATRICULE, NOM, PRENOM, CORPS_PN, BASE, MONTANT_AVANCE, DEFICIT_CAISSE, MOIS, ANNEE)
                        VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?)";
        
        $stmt = $conn->prepare($mergeSql);
        
        foreach ($data as $row) {
            // Validate and convert data
            $validated = validateRowData($row);
            
            // Execute MERGE statement
            $stmt->execute([
                // USING clause
                $validated['Matricule'],
                $validated['Mois'],
                $validated['Annee'],
                // WHEN MATCHED UPDATE
                $validated['Nom'],
                $validated['Prenom'],
                $validated['Base'],
                $validated['Corps'],
                $validated['MontantAvance'],
                // WHEN NOT MATCHED INSERT
                $validated['Matricule'],
                $validated['Nom'],
                $validated['Prenom'],
                $validated['Corps'],
                $validated['Base'],
                $validated['MontantAvance'],
                $validated['Mois'],
                $validated['Annee']
            ]);
            
            $insertedCount++;
        }
        
        $conn->commit();
        return $insertedCount;
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw new Exception("Erreur lors du traitement: " . $e->getMessage());
    }
}

/**
 * Validate and convert row data
 */
function validateRowData($row) {
    // Validate Corps value
    if (!in_array($row['Corps'], ['PNT', 'PNC'])) {
        throw new Exception("Corps invalide: " . $row['Corps'] . " pour le matricule " . $row['Matricule']);
    }
    
    // Convert numeric fields
    $mois = (int)$row['Mois'];
    $annee = (int)$row['Annee'];
    $montant_avance = (float)$row['Avance'];
    
    // Validate ranges
    if ($mois < 1 || $mois > 12) {
        throw new Exception("Mois invalide: " . $row['Mois'] . " pour le matricule " . $row['Matricule']);
    }
    
    if ($annee < 2000 || $annee > 2100) {
        throw new Exception("Année invalide: " . $row['Annee'] . " pour le matricule " . $row['Matricule']);
    }
    
    if ($montant_avance < 0) {
        throw new Exception("Montant d'avance invalide: " . $row['Avance'] . " pour le matricule " . $row['Matricule']);
    }
    
    return [
        'Matricule' => $row['Matricule'],
        'Nom' => $row['Nom'],
        'Prenom' => $row['Prenom'],
        'Mois' => $mois,
        'Annee' => $annee,
        'Corps' => $row['Corps'],
        'Base' => $row['Base'] ?? null,
        'MontantAvance' => $montant_avance
    ];
}

include 'import_dcoa.phtml';
?>
