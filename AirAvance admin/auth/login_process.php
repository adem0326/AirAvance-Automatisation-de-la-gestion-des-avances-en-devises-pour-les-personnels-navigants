<?php
session_start();

// NOTE: USERS_app table must have a ROLE column. If it doesn't, run:
// ALTER TABLE USERS_app ADD ROLE VARCHAR2(50) DEFAULT 'DCOA' NOT NULL;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    include '../includes/db_connect.php'; // must create $conn as a PDO object

    $form_user = $_POST['user_mat']; 
    $form_pass = $_POST['user_pass'];

    try {
        // 1. Fetch MATRICULE, MOTDEPASSE, and ROLE from USERS_app table
        $sql = "SELECT MATRICULE, MOT_DE_PASSE, NOM, PRENOM, ROLE FROM USERS_app WHERE MATRICULE = :u";
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':u', $form_user, PDO::PARAM_STR);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            // 2. Verify the hashed password
            if (password_verify($form_pass, $row['MOT_DE_PASSE'])) {
                // Store user info in session
                $_SESSION['user'] = $row['NOM'];
                $_SESSION['prenom'] = $row['PRENOM'];
                $_SESSION['matricule'] = $row['MATRICULE'];
                $_SESSION['role'] = $row['ROLE'];

                // 3. Role-based redirection
                switch ($row['ROLE']) {
                    case 'admin':
                        header("Location: ../admin/admin_dashboard.php");
                        exit();
                    case 'DCOA':
                        header("Location: ../dcoa/dcoa_dashboard.php");
                        exit();
                    case 'DCSP':
                        header("Location: ../dcsp/dcsp_dashboard.php");
                        exit();
                    case 'DGF':
                        header("Location: ../dgf/dgf_dashboard.php");
                        exit();
                    default:
                        // If role doesn't match any expected role, log out and redirect
                        session_destroy();
                        header("Location: login.html?error=invalid_role&mat=" . urlencode($form_user));
                        exit();
                }
            } else {
                header("Location: login.html?error=invalid_password&mat=" . urlencode($form_user));
                exit();
            }
        } else {
            header("Location: login.html?error=user_not_found&mat=" . urlencode($form_user));
            exit();
        }

    } catch (PDOException $e) {
        echo "Database error: " . htmlentities($e->getMessage(), ENT_QUOTES);
    }
}
?>
