Pour accéder à l’interface admin, le matricule est admin et le mot de passe est adminadmin

Dépannage (Si le mot de passe ne fonctionne pas):
Si vous ne parvenez pas à vous connecter avec adminadmin après l'insertion SQL, cela peut être dû à une différence de configuration de votre serveur PHP. Suivez ces étapes pour générer un hash compatible avec votre environnement :

Créez un nouveau fichier PHP nommé generer_mdp.php.

Copiez-collez le code suivant à l'intérieur :

<?php
// Ce code génère le hash correct pour votre version de PHP
echo password_hash("adminadmin", PASSWORD_DEFAULT);
?>

Lancez ce fichier dans votre navigateur.
Copiez la chaîne de caractères qui s'affiche à l'écran.
Mettez à jour votre base de données avec cette requête SQL en remplaçant le hash :

SQL
UPDATE "USERS_APP" 
SET "MOT_DE_PASSE" = 'COLLEZ_ICI_LE_HASH_COPIÉ' 
WHERE "MATRICULE" = 'admin';
Supprimez le fichier generer_mdp.php après utilisation par mesure de sécurité.