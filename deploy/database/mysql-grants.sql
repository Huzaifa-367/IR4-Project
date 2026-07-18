-- IR4 MySQL 8 least-privilege grants (DOC-17 / DOC-19 / DOC-20)
-- Run as a MySQL administrator. Replace CHANGE_ME_* passwords before apply.
-- Production supports MySQL 8 only.

CREATE DATABASE IF NOT EXISTS `ir4` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `ir4_restore` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'ir4_app'@'localhost' IDENTIFIED BY 'CHANGE_ME_APP';
CREATE USER IF NOT EXISTS 'ir4_backup'@'localhost' IDENTIFIED BY 'CHANGE_ME_BACKUP';
CREATE USER IF NOT EXISTS 'ir4_restore'@'localhost' IDENTIFIED BY 'CHANGE_ME_RESTORE';
CREATE USER IF NOT EXISTS 'ir4_wipe'@'localhost' IDENTIFIED BY 'CHANGE_ME_WIPE';

-- App: full DML on operational tables; INSERT/SELECT only on audit_logs.
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
  ON `ir4`.* TO 'ir4_app'@'localhost';
REVOKE UPDATE, DELETE ON `ir4`.`audit_logs` FROM 'ir4_app'@'localhost';
GRANT SELECT, INSERT ON `ir4`.`audit_logs` TO 'ir4_app'@'localhost';

-- Backup: read-only dump account.
GRANT SELECT, SHOW VIEW, TRIGGER, LOCK TABLES ON `ir4`.* TO 'ir4_backup'@'localhost';

-- Restore: full access to staging DB only (never the live DB).
GRANT ALL PRIVILEGES ON `ir4_restore`.* TO 'ir4_restore'@'localhost';

-- Wipe: privileged maintenance for ir4:secure-wipe (includes audit_logs DELETE).
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER, REFERENCES, CREATE TEMPORARY TABLES, LOCK TABLES
  ON `ir4`.* TO 'ir4_wipe'@'localhost';

FLUSH PRIVILEGES;
