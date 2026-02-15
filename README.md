# AirAvance

**Automatisation de la gestion des avances en devises pour les personnels navigants**

## 📋 Description

AirAvance est une application web conçue pour automatiser et simplifier la gestion des avances en devises destinées aux personnels navigants. Cette solution permet une gestion efficace des demandes d'avances, le suivi des transactions et la comptabilité des remboursements.

## ✨ Fonctionnalités principales

- 🔐 **Authentification sécurisée** - Système de connexion pour les utilisateurs et administrateurs
- 💰 **Gestion des avances** - Demande et suivi des avances en devises multiples
- 📊 **Tableau de bord** - Vue d'ensemble des transactions et statistiques
- 👥 **Gestion des utilisateurs** - Administration des personnels navigants
- 📝 **Historique des transactions** - Suivi complet de toutes les opérations
- 💱 **Support multi-devises** - Gestion de différentes devises internationales
- 📄 **Génération de rapports** - Export et visualisation des données

## 🚀 Technologies utilisées

- **Backend** : PHP
- **Frontend** : HTML5, CSS3, JavaScript
- **Base de données** : MySQL


## 📦 Prérequis

Avant de commencer, assurez-vous d'avoir installé :

- PHP >= 7.4
- MySQL >= 5.7 ou MariaDB >= 10.3
- Apache ou Nginx
- Composer (optionnel, pour la gestion des dépendances)

## ⚙️ Installation

### 1. Cloner le repository

```bash
git clone https://github.com/adem0326/AirAvance-Automatisation-de-la-gestion-des-avances-en-devises-pour-les-personnels-navigants.git
cd AirAvance-Automatisation-de-la-gestion-des-avances-en-devises-pour-les-personnels-navigants
```

### 2. Configuration de la base de données

> 📝 **Note** : Les requêtes SQL nécessaires se trouvent dans un fichier texte (.txt) inclus dans le projet.

1. Créez une nouvelle base de données MySQL :

```sql
CREATE DATABASE airAvance CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Importez le schéma de la base de données en utilisant les requêtes SQL du fichier texte fourni :

```bash
mysql -u votre_utilisateur -p airAvance < fichier_sql.txt
```

Ou exécutez les requêtes manuellement via phpMyAdmin ou tout autre client MySQL.

3. Configurez les paramètres de connexion dans le fichier de configuration :

```php
// config/database.php ou équivalent
define('DB_HOST', 'localhost');
define('DB_NAME', 'airAvance');
define('DB_USER', 'votre_utilisateur');
define('DB_PASS', 'votre_mot_de_passe');
```

### 3. Configuration du serveur web

#### Apache

Configurez votre VirtualHost :

```apache
<VirtualHost *:80>
    ServerName airAvance.local
    DocumentRoot /chemin/vers/AirAvance-admin
    
    <Directory /chemin/vers/AirAvance-admin>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/airAvance_error.log
    CustomLog ${APACHE_LOG_DIR}/airAvance_access.log combined
</VirtualHost>
```

### 4. Permissions des fichiers

```bash
chmod -R 755 /chemin/vers/AirAvance-admin
chmod -R 777 /chemin/vers/AirAvance-admin/uploads  # Si dossier d'uploads existe
```

## 🎯 Utilisation

### Accès à l'application

1. Démarrez votre serveur web
2. Accédez à l'application via votre navigateur : `http://localhost/AirAvance-admin` ou `http://airAvance.local`
3. Connectez-vous avec vos identifiants

### 🔑 Identifiants par défaut

Pour accéder à l'interface admin :
- **Matricule** : `admin`
- **Mot de passe** : `adminadmin`

> ⚠️ **Important** : Changez ces identifiants après votre première connexion pour des raisons de sécurité.

### Interface administrateur

L'interface administrateur permet de :
- Gérer les utilisateurs et leurs droits
- Approuver ou rejeter les demandes d'avances
- Consulter les statistiques et rapports
- Configurer les devises et taux de change
- Gérer les paramètres système

### Interface utilisateur

Les personnels navigants peuvent :
- Soumettre des demandes d'avances
- Consulter l'historique de leurs transactions
- Suivre le statut de leurs demandes
- Télécharger des justificatifs

## 📁 Structure du projet

```
AirAvance-admin/
├── assets/          # Ressources statiques (CSS, JS, images)
├── config/          # Fichiers de configuration
├── controllers/     # Contrôleurs de l'application
├── models/          # Modèles de données
├── views/           # Vues (templates HTML)
├── includes/        # Fichiers PHP communs
├── uploads/         # Fichiers téléchargés
└── index.php        # Point d'entrée de l'application
```

## 🔒 Sécurité

- Les mots de passe sont hashés avec des algorithmes sécurisés
- Protection contre les injections SQL via des requêtes préparées
- Validation et sanitisation des entrées utilisateur
- Sessions sécurisées avec timeout
- Protection CSRF pour les formulaires

## 🐛 Résolution des problèmes

### Erreur de connexion à la base de données

Vérifiez que :
- MySQL est démarré
- Les identifiants sont corrects dans le fichier de configuration
- L'utilisateur MySQL a les privilèges nécessaires

### 🔐 Dépannage : Problème de connexion avec le mot de passe admin

Si vous ne parvenez pas à vous connecter avec `adminadmin` après l'insertion SQL, cela peut être dû à une différence de configuration de votre serveur PHP. Suivez ces étapes pour générer un hash compatible avec votre environnement :

1. **Créez un nouveau fichier PHP** nommé `generer_mdp.php` à la racine de votre projet

2. **Copiez-collez le code suivant** à l'intérieur :

```php
<?php
// Ce code génère le hash correct pour votre version de PHP
echo password_hash("adminadmin", PASSWORD_DEFAULT);
?>
```

3. **Lancez ce fichier dans votre navigateur** : `http://localhost/AirAvance-admin/generer_mdp.php`

4. **Copiez la chaîne de caractères** qui s'affiche à l'écran

5. **Mettez à jour votre base de données** avec cette requête SQL en remplaçant le hash :

```sql
UPDATE "USERS_APP" 
SET "MOT_DE_PASSE" = 'COLLEZ_ICI_LE_HASH_COPIÉ' 
WHERE "MATRICULE" = 'admin';
```

6. **Supprimez le fichier `generer_mdp.php`** après utilisation par mesure de sécurité

> ⚠️ **Sécurité** : N'oubliez pas de supprimer le fichier `generer_mdp.php` une fois que vous avez mis à jour le mot de passe !

### Problèmes de permissions

```bash
sudo chown -R www-data:www-data /chemin/vers/AirAvance-admin
sudo chmod -R 755 /chemin/vers/AirAvance-admin
```

## 🤝 Contribution

Les contributions sont les bienvenues ! Pour contribuer :

1. Forkez le projet
2. Créez une branche pour votre fonctionnalité (`git checkout -b feature/AmazingFeature`)
3. Committez vos changements (`git commit -m 'Add some AmazingFeature'`)
4. Pushez vers la branche (`git push origin feature/AmazingFeature`)
5. Ouvrez une Pull Request

## 📝 Roadmap

- [ ] Intégration d'une API de taux de change en temps réel
- [ ] Application mobile pour les personnels navigants
- [ ] Module de notifications par email/SMS
- [ ] Export des rapports en PDF
- [ ] Tableau de bord analytique avancé
- [ ] Support multilingue

## 📄 Licence

Ce projet est sous licence [MIT](LICENSE) - voir le fichier LICENSE pour plus de détails.

## 👤 Auteur

**Adem**

- GitHub: [@adem0326](https://github.com/adem0326)

## 🙏 Remerciements

- Merci à tous les contributeurs qui ont participé à ce projet
- Inspiration tirée des besoins réels des compagnies aériennes
- Communauté open source pour les outils et bibliothèques utilisés

## 📞 Support

Pour toute question ou problème, veuillez :
- Ouvrir une [issue](https://github.com/adem0326/AirAvance-Automatisation-de-la-gestion-des-avances-en-devises-pour-les-personnels-navigants/issues)
- Contacter l'équipe de développement

---

⭐ Si ce projet vous a été utile, n'hésitez pas à lui donner une étoile !
