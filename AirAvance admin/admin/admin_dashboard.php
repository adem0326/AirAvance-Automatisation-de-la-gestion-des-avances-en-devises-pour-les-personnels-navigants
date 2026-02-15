<?php
session_start();

// Security check: Ensure user is authenticated and has admin role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../auth/login.html?error=unauthorized");
    exit();
}

include '../includes/db_connect.php';

// NOTE: USERS_app table must have a ROLE column. If it doesn't, run:
// ALTER TABLE USERS_app ADD ROLE VARCHAR2(50) DEFAULT 'DCOA' NOT NULL;

// Initialize variables
$user_name = $_SESSION['user'] ?? 'Admin';
$user_initial = strtoupper(substr($user_name, 0, 1));
$success_message = '';
$error_message = '';
$users = [];

// Fetch all users from database
function fetchAllUsers($conn, $sql) {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

try {
    $sql = "SELECT MATRICULE, NOM, PRENOM, ROLE FROM USERS_app ORDER BY NOM ASC";
    $users = fetchAllUsers($conn, $sql);
} catch (PDOException $e) {
    $error_message = "Erreur lors du chargement des utilisateurs: " . htmlentities($e->getMessage(), ENT_QUOTES);
}

// Handle Create/Modify User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'save_user') {
    $matricule = trim($_POST['matricule'] ?? '');
    $nom = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $role = trim($_POST['role'] ?? '');
    $motdepasse = trim($_POST['motdepasse'] ?? '');

    if (empty($matricule) || empty($nom) || empty($prenom) || empty($role)) {
        $error_message = "Tous les champs sont obligatoires.";
    } else {
        try {
            // Check if user already exists
            $check_sql = "SELECT COUNT(*) AS CNT FROM USERS_app WHERE MATRICULE = :mat";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bindParam(':mat', $matricule, PDO::PARAM_STR);
            $check_stmt->execute();
            $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($check_result['CNT'] > 0) {
                // Update existing user
                if (!empty($motdepasse)) {
                    $hashed_pass = password_hash($motdepasse, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE USERS_app SET NOM = :nom, PRENOM = :prenom, ROLE = :role, MOT_DE_PASSE = :pwd WHERE MATRICULE = :mat";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bindParam(':mat', $matricule, PDO::PARAM_STR);
                    $update_stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
                    $update_stmt->bindParam(':prenom', $prenom, PDO::PARAM_STR);
                    $update_stmt->bindParam(':role', $role, PDO::PARAM_STR);
                    $update_stmt->bindParam(':pwd', $hashed_pass, PDO::PARAM_STR);
                    $update_stmt->execute();
                } else {
                    $update_sql = "UPDATE USERS_app SET NOM = :nom, PRENOM = :prenom, ROLE = :role WHERE MATRICULE = :mat";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bindParam(':mat', $matricule, PDO::PARAM_STR);
                    $update_stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
                    $update_stmt->bindParam(':prenom', $prenom, PDO::PARAM_STR);
                    $update_stmt->bindParam(':role', $role, PDO::PARAM_STR);
                    $update_stmt->execute();
                }
                $success_message = "Utilisateur modifié avec succès.";
            } else {
                // Create new user
                if (empty($motdepasse)) {
                    $error_message = "Le mot de passe est requis pour créer un nouvel utilisateur.";
                } else {
                    $hashed_pass = password_hash($motdepasse, PASSWORD_DEFAULT);
                    $insert_sql = "INSERT INTO USERS_app (MATRICULE, NOM, PRENOM, ROLE, MOT_DE_PASSE) VALUES (:mat, :nom, :prenom, :role, :pwd)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $insert_stmt->bindParam(':mat', $matricule, PDO::PARAM_STR);
                    $insert_stmt->bindParam(':nom', $nom, PDO::PARAM_STR);
                    $insert_stmt->bindParam(':prenom', $prenom, PDO::PARAM_STR);
                    $insert_stmt->bindParam(':role', $role, PDO::PARAM_STR);
                    $insert_stmt->bindParam(':pwd', $hashed_pass, PDO::PARAM_STR);
                    $insert_stmt->execute();
                    $success_message = "Utilisateur créé avec succès.";
                }
            }

            // Refresh user list
            $users = fetchAllUsers($conn, $sql);
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données: " . htmlentities($e->getMessage(), ENT_QUOTES);
        }
    }
}

// Handle Delete User
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] === 'delete_user') {
    $matricule_delete = trim($_POST['matricule_delete'] ?? '');

    if (empty($matricule_delete)) {
        $error_message = "Veuillez fournir un matricule valide.";
    } else {
        try {
            // Prevent admin from deleting themselves
            if ($matricule_delete === $_SESSION['matricule']) {
                $error_message = "Vous ne pouvez pas supprimer votre propre compte.";
            } else {
                $delete_sql = "DELETE FROM USERS_app WHERE MATRICULE = :mat";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bindParam(':mat', $matricule_delete, PDO::PARAM_STR);
                $delete_stmt->execute();

                if ($delete_stmt->rowCount() > 0) {
                    $success_message = "Utilisateur supprimé avec succès.";
                } else {
                    $error_message = "Utilisateur non trouvé.";
                }

                // Refresh user list
                $users = fetchAllUsers($conn, $sql);
            }
        } catch (PDOException $e) {
            $error_message = "Erreur de base de données: " . htmlentities($e->getMessage(), ENT_QUOTES);
        }
    }
}

// Include the HTML template
include 'admin_dashboard.phtml';
?>
