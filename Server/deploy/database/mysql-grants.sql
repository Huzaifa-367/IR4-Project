-- DOC-20 §6 — MySQL grants for the on-prem app user.
-- Replace ir4_app / ir4_platform with the live names from .env.

CREATE USER IF NOT EXISTS 'ir4_app'@'localhost' IDENTIFIED BY 'CHANGE_ME';

GRANT SELECT, INSERT, UPDATE, DELETE, EXECUTE, CREATE TEMPORARY TABLES
  ON ir4_platform.* TO 'ir4_app'@'localhost';

-- Append-only audit: app may insert and read, never update/delete.
REVOKE UPDATE, DELETE ON ir4_platform.audit_logs FROM 'ir4_app'@'localhost';

-- Spatie backups need dump privileges.
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES
  ON ir4_platform.* TO 'ir4_app'@'localhost';

FLUSH PRIVILEGES;
