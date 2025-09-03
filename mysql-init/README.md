# Configuration MySQL - Utilisateur phpMyAdmin

Ce dossier contient des scripts d'initialisation MySQL avec des credentials.

## ⚠️ Sécurité

Ce dossier est ignoré par Git (`.gitignore`) pour des raisons de sécurité.

## Configuration requise

Créez le fichier `01-create-phpmyadmin-user.sql` avec le contenu :

```sql
-- Script d'initialisation MySQL - Création automatique utilisateur phpMyAdmin
CREATE USER IF NOT EXISTS 'pma_admin_2025'@'%' IDENTIFIED BY 'VotreMotDePasseSecurise';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, 
      CREATE TEMPORARY TABLES, LOCK TABLES ON Acadyoquiz3.* TO 'pma_admin_2025'@'%';
GRANT SELECT ON information_schema.* TO 'pma_admin_2025'@'%';
FLUSH PRIVILEGES;
```

## Instructions

1. Créez le fichier SQL avec vos propres credentials
2. Démarrez Docker : `docker-compose up --build`
3. L'utilisateur sera créé automatiquement