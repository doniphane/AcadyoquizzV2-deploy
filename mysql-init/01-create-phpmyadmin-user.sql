-- Script d'initialisation MySQL - Création automatique utilisateur phpMyAdmin
-- Exécuté automatiquement à chaque création de container

-- Créer utilisateur phpMyAdmin sécurisé
CREATE USER IF NOT EXISTS 'pma_admin_2025'@'%' IDENTIFIED BY 'PmaSecure#2025!AcadyoQuizz';

-- Droits limités uniquement sur Acadyoquiz3
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, 
      CREATE TEMPORARY TABLES, LOCK TABLES ON Acadyoquiz3.* TO 'pma_admin_2025'@'%';

-- Droits pour phpMyAdmin (sans information_schema qui pose problème)
GRANT PROCESS ON *.* TO 'pma_admin_2025'@'%';

-- Appliquer
FLUSH PRIVILEGES;

-- Confirmation
SELECT CONCAT('✅ Utilisateur phpMyAdmin créé: pma_admin_2025 avec droits sur Acadyoquiz3 uniquement') as STATUS;