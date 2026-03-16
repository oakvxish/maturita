-- ============================================================
--  MIGRAZIONE MULTI-TENANT — eseguire una volta sola
-- ============================================================

-- 1. Tabella saloni (un record per ogni tenant)
CREATE TABLE IF NOT EXISTS saloni (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    nome       VARCHAR(255) NOT NULL,
    slug       VARCHAR(100) NOT NULL UNIQUE,   -- usato nell'URL pubblico prenota
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Aggiungi salone_id a tutte le tabelle esistenti
--    Se la colonna esiste già, MySQL restituisce un errore ignorabile.

ALTER TABLE userdata      ADD COLUMN salone_id INT NOT NULL DEFAULT 0;
ALTER TABLE clienti       ADD COLUMN salone_id INT NOT NULL DEFAULT 0;
ALTER TABLE servizi       ADD COLUMN salone_id INT NOT NULL DEFAULT 0;
ALTER TABLE appuntamenti  ADD COLUMN salone_id INT NOT NULL DEFAULT 0;
ALTER TABLE magazzino     ADD COLUMN salone_id INT NOT NULL DEFAULT 0;
ALTER TABLE whatsapp_log  ADD COLUMN salone_id INT NOT NULL DEFAULT 0;

-- impostazioni: chiave deve essere unica per salone, non globale
ALTER TABLE impostazioni  ADD COLUMN salone_id INT NOT NULL DEFAULT 0;
ALTER TABLE impostazioni  DROP INDEX chiave;
ALTER TABLE impostazioni  ADD UNIQUE KEY uq_salone_chiave (salone_id, chiave);

-- 3. Indici di ricerca
CREATE INDEX idx_clienti_sal      ON clienti      (salone_id);
CREATE INDEX idx_appuntamenti_sal ON appuntamenti  (salone_id);
CREATE INDEX idx_servizi_sal      ON servizi       (salone_id);
CREATE INDEX idx_magazzino_sal    ON magazzino     (salone_id);
CREATE INDEX idx_wlog_sal         ON whatsapp_log  (salone_id);

-- ============================================================
--  MIGRAZIONE DATI ESISTENTI
--  Se avevi già un salone, crea il record e aggiorna le righe.
-- ============================================================

-- INSERT INTO saloni (nome, slug) VALUES ('Il Mio Salone', 'mio-salone');
-- SET @sid = LAST_INSERT_ID();
-- UPDATE userdata      SET salone_id = @sid WHERE salone_id = 0;
-- UPDATE clienti       SET salone_id = @sid WHERE salone_id = 0;
-- UPDATE servizi       SET salone_id = @sid WHERE salone_id = 0;
-- UPDATE appuntamenti  SET salone_id = @sid WHERE salone_id = 0;
-- UPDATE magazzino     SET salone_id = @sid WHERE salone_id = 0;
-- UPDATE impostazioni  SET salone_id = @sid WHERE salone_id = 0;
-- UPDATE whatsapp_log  SET salone_id = @sid WHERE salone_id = 0;
