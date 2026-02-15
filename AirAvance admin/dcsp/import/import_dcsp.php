<?php
session_start();

// Security check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'DCSP') {
    header("Location: ../../auth/login.html?error=unauthorized");
    exit();
}

include '../../includes/db_connect.php';

// Force uppercase keys for Oracle compatibility
$conn->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);

$user_name = $_SESSION['user'] ?? 'DCSP';
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
        
        if ($file_ext !== 'csv') {
            $error_message = "Format de fichier non valide. Veuillez utiliser CSV.";
        } else {
            try {
                $data = parseFile($file['tmp_name'], $file_ext);
                
                if (empty($data)) {
                    $error_message = "Aucune donnée valide trouvée dans le fichier. Vérifiez que:\n" .
                                   "- Le fichier a au moins 2 lignes (en-têtes + données)\n" .
                                   "- Les 5 premiers champs (NumEta, Vol, Matricule, Mois, Année) ne sont pas vides\n" .
                                   "- Le fichier contient exactement 9 colonnes (A-I)\n" .
                                   "Consultez les logs PHP pour plus de détails sur les lignes ignorées.";
                } else {
                    // Process the data
                    $total_rows = processDCSPImport($data, $conn);
                    
                    if ($total_rows > 0) {
                        // Redirect to DCSP dashboard
                        header("Location: ../dcsp_dashboard.php?success=dcsp&rows=" . $total_rows);
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
 * Parse CSV file and return data array
 * Reads all 9 columns from CSV (A through I):
 * A: DEF_NUMETA (Batch ID) - index 0
 * B: DEF_VOL (Flight #) - index 1
 * C: DEF_DATVOL (Date) - index 2
 * D: DEF_MATPNC (Matricule) - index 3
 * E: DEF_MOIS (Month) - index 4
 * F: DEF_ANNEE (Year) - index 5
 * G: DEF_TOTVEN (Sales) - index 6
 * H: DEF_MONENC (Collected) - index 7
 * I: DEF_ECART (Deficit) - index 8
 */
function parseFile($filePath, $ext) {
    $data = [];
    $rowNum = 0;
    $skippedRows = [];
    
    if (!file_exists($filePath)) {
        throw new Exception("Le fichier n'existe pas: $filePath");
    }
    
    if (($handle = fopen($filePath, 'r')) !== FALSE) {
        // Skip header row
        $header = fgetcsv($handle);
        if ($header === false) {
            throw new Exception("Impossible de lire l'en-tête du fichier.");
        }
        
        while (($row = fgetcsv($handle)) !== FALSE) {
            $rowNum++;
            
            // Skip empty rows
            if (empty($row) || empty($row[0])) {
                continue;
            }
            
            // Make sure we have enough columns (9 for all fields A-I)
            if (count($row) < 9) {
                $skippedRows[] = "Ligne $rowNum: seulement " . count($row) . " colonnes (9 requises)";
                continue;
            }
            
            // Extract all values from correct columns
            $numeta = trim($row[0] ?? '');         // Column A (Batch ID)
            $vol = trim($row[1] ?? '');            // Column B (Flight #)
            $datvol = trim($row[2] ?? '');         // Column C (Date DD/MM/YYYY)
            $matricule = trim($row[3] ?? '');      // Column D (Matricule)
            $mois = trim($row[4] ?? '');           // Column E (Month)
            $annee = trim($row[5] ?? '');          // Column F (Year)
            $totven = trim($row[6] ?? '');         // Column G (Sales)
            $monenc = trim($row[7] ?? '');         // Column H (Collected)
            $ecart = trim($row[8] ?? '');          // Column I (Deficit)
            
            // Only add if we have all required fields for MERGE
            if (!empty($numeta) && !empty($vol) && !empty($matricule) && !empty($mois) && !empty($annee)) {
                $data[] = [
                    'NumEta' => $numeta,
                    'Vol' => $vol,
                    'DatVol' => !empty($datvol) ? $datvol : '',
                    'Matricule' => $matricule,
                    'Mois' => $mois,
                    'Annee' => $annee,
                    'TotVen' => !empty($totven) ? $totven : 0,
                    'MonEnc' => !empty($monenc) ? $monenc : 0,
                    'Ecart' => !empty($ecart) ? $ecart : 0
                ];
            } else {
                $missing = [];
                if (empty($numeta)) $missing[] = "NumEta";
                if (empty($vol)) $missing[] = "Vol";
                if (empty($matricule)) $missing[] = "Matricule";
                if (empty($mois)) $missing[] = "Mois";
                if (empty($annee)) $missing[] = "Année";
                $skippedRows[] = "Ligne $rowNum: champs manquants (" . implode(", ", $missing) . ")";
            }
        }
        fclose($handle);
        
        // Log skipped rows for debugging
        if (!empty($skippedRows)) {
            error_log("Rows skipped during parsing: " . implode(" | ", $skippedRows));
        }
    } else {
        throw new Exception("Impossible d'ouvrir le fichier CSV.");
    }
    
    return $data;
}

/**
 * Process DCSP import using UPSERT approach
 * Try to update existing record, if no rows affected then insert new row
 * Match Condition: Match rows on (DEF_NUMETA, DEF_MATPNC, DEF_VOL)
 * After import, sync PERSONNEL_NAVIGANT with SUM of deficits
 */
function processDCSPImport($data, $conn) {
    $insertedCount = 0;
    $errorLog = [];
    
    try {
        $conn->beginTransaction();
        
        // Prepare UPDATE statement
        $updateSql = "UPDATE DEFICITS_DETAIL 
                      SET DEF_DATVOL = TO_DATE(?, 'DD/MM/YYYY'),
                          DEF_MOIS = ?,
                          DEF_ANNEE = ?,
                          DEF_TOTVEN = ?,
                          DEF_MONENC = ?,
                          DEF_ECART = ?
                      WHERE DEF_NUMETA = ? 
                        AND DEF_MATPNC = ? 
                        AND DEF_VOL = ?";
        
        // Prepare INSERT statement
        $insertSql = "INSERT INTO DEFICITS_DETAIL (DEF_NUMETA, DEF_VOL, DEF_DATVOL, DEF_MATPNC, 
                                                   DEF_MOIS, DEF_ANNEE, DEF_TOTVEN, DEF_MONENC, DEF_ECART)
                      VALUES (?, ?, TO_DATE(?, 'DD/MM/YYYY'), ?, ?, ?, ?, ?, ?)";
        
        $updateStmt = $conn->prepare($updateSql);
        $insertStmt = $conn->prepare($insertSql);
        
        foreach ($data as $idx => $row) {
            try {
                // Validate and convert data
                $validated = validateRowData($row);
                
                // Try UPDATE first
                $updateStmt->execute([
                    $validated['DatVol'],
                    $validated['Mois'],
                    $validated['Annee'],
                    $validated['TotVen'],
                    $validated['MonEnc'],
                    $validated['Ecart'],
                    $validated['NumEta'],
                    $validated['Matricule'],
                    $validated['Vol']
                ]);
                
                $rowsAffected = $updateStmt->rowCount();
                
                // If no rows updated, INSERT new record
                if ($rowsAffected === 0) {
                    $insertStmt->execute([
                        $validated['NumEta'],
                        $validated['Vol'],
                        $validated['DatVol'],
                        $validated['Matricule'],
                        $validated['Mois'],
                        $validated['Annee'],
                        $validated['TotVen'],
                        $validated['MonEnc'],
                        $validated['Ecart']
                    ]);
                }
                
                $insertedCount++;
                
            } catch (Exception $e) {
                $errorMsg = "Ligne " . ($idx + 2) . " (NumEta: " . ($row['NumEta'] ?? '?') . 
                           ", Vol: " . ($row['Vol'] ?? '?') . 
                           ", Matricule: " . ($row['Matricule'] ?? '?') . "): " . $e->getMessage();
                $errorLog[] = $errorMsg;
                error_log("DCSP Import Error - " . $errorMsg);
            }
        }
        
        // Step 2: Sync PERSONNEL_NAVIGANT with aggregated deficits
        if ($insertedCount > 0) {
            syncPersonnelDeficits($conn);
        }
        
        // Log errors to database if there are any
        if (!empty($errorLog)) {
            logImportErrors($conn, $errorLog, 'DCSP');
        }
        
        $conn->commit();
        return $insertedCount;
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        error_log("DCSP Import Critical Error: " . $e->getMessage());
        throw new Exception("Erreur lors du traitement: " . $e->getMessage());
    }
}

/**
 * Synchronize PERSONNEL_NAVIGANT DEFICIT_CAISSE with sum of DEFICITS_DETAIL
 * Updates PERSONNEL_NAVIGANT.DEFICIT_CAISSE = SUM(DEF_ECART) 
 * grouped by MATRICULE, MOIS, and ANNEE
 */
function syncPersonnelDeficits($conn) {
    try {
        $syncSql = "UPDATE PERSONNEL_NAVIGANT pn
                    SET DEFICIT_CAISSE = (
                        SELECT COALESCE(SUM(dd.DEF_ECART), 0)
                        FROM DEFICITS_DETAIL dd
                        WHERE dd.DEF_MATPNC = pn.MATRICULE
                          AND dd.DEF_MOIS = pn.MOIS
                          AND dd.DEF_ANNEE = pn.ANNEE
                    )
                    WHERE EXISTS (
                        SELECT 1
                        FROM DEFICITS_DETAIL dd
                        WHERE dd.DEF_MATPNC = pn.MATRICULE
                          AND dd.DEF_MOIS = pn.MOIS
                          AND dd.DEF_ANNEE = pn.ANNEE
                    )";
        
        $syncStmt = $conn->prepare($syncSql);
        $syncStmt->execute();
        
    } catch (Exception $e) {
        throw new Exception("Erreur lors de la synchronisation PERSONNEL_NAVIGANT: " . $e->getMessage());
    }
}

/**
 * Validate and convert row data
 */
function validateRowData($row) {
    // Validate required fields
    if (empty($row['NumEta'])) {
        throw new Exception("NumEta (Batch ID) vide");
    }
    if (empty($row['Vol'])) {
        throw new Exception("Vol (Flight #) vide");
    }
    if (empty($row['Matricule'])) {
        throw new Exception("Matricule vide");
    }
    
    // Convert values
    $mois = (int)$row['Mois'];
    $annee = (int)$row['Annee'];
    $totven = (float)($row['TotVen'] ?? 0);
    $monenc = (float)($row['MonEnc'] ?? 0);
    $ecart = (float)($row['Ecart'] ?? 0);
    
    // Validate mois range
    if ($mois < 1 || $mois > 12) {
        throw new Exception("Mois invalide: " . $row['Mois'] . " pour le matricule " . $row['Matricule']);
    }
    
    // Validate annee range
    if ($annee < 2000 || $annee > 2100) {
        throw new Exception("Année invalide: " . $row['Annee'] . " pour le matricule " . $row['Matricule']);
    }
    
    // Convert date from MM/DD/YYYY to DD/MM/YYYY format
    $datVol = $row['DatVol'] ?? '';
    if (!empty($datVol)) {
        // Parse MM/DD/YYYY format
        $dateParts = explode('/', $datVol);
        if (count($dateParts) === 3) {
            $month = str_pad($dateParts[0], 2, '0', STR_PAD_LEFT);
            $day = str_pad($dateParts[1], 2, '0', STR_PAD_LEFT);
            $year = $dateParts[2];
            // Convert to DD/MM/YYYY format for Oracle
            $datVol = $day . '/' . $month . '/' . $year;
        }
    }
    
    return [
        'NumEta' => $row['NumEta'],
        'Vol' => $row['Vol'],
        'DatVol' => $datVol,
        'Matricule' => $row['Matricule'],
        'Mois' => $mois,
        'Annee' => $annee,
        'TotVen' => $totven,
        'MonEnc' => $monenc,
        'Ecart' => abs($ecart)
    ];
}

/**
 * Log import errors to database for audit trail
 */
function logImportErrors($conn, $errorLog, $type) {
    try {
        foreach ($errorLog as $error) {
            $logSql = "INSERT INTO IMPORT_ERRORS (ERROR_TYPE, ERROR_MESSAGE, CREATED_AT) 
                       VALUES (?, ?, SYSDATE)";
            $logStmt = $conn->prepare($logSql);
            $logStmt->execute([$type, $error]);
        }
    } catch (Exception $e) {
        // Silently fail - don't break the import if error logging fails
        error_log("Error logging import errors: " . $e->getMessage());
    }
}

include 'import_dcsp.phtml';
?>
