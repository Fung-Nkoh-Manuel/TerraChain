-- ============================================================
--  TerraChain v2 — Hybrid Land Registry
--  MySQL Schema (Minimal Blockchain Interaction)
-- ============================================================

CREATE DATABASE IF NOT EXISTS terrachain_v2 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE terrachain_v2;

-- ── USERS (Traditional Auth + Internal Wallet Mapping) ──
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(100) NOT NULL UNIQUE,
    email           VARCHAR(200) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    full_name       VARCHAR(200),
    phone           VARCHAR(50),
    national_id     VARCHAR(100),
    role            ENUM('admin','validator','user') DEFAULT 'user',
    is_active       TINYINT(1) DEFAULT 1,
    wallet_address  VARCHAR(42) UNIQUE,                -- Internal blockchain address mapping
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ── KYC RECORDS ──────────────────────────────────────────────
CREATE TABLE kyc_records (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    user_id             INT NOT NULL,
    document_hash       VARCHAR(64) NOT NULL,          -- SHA-256 of uploaded doc
    ipfs_hash           VARCHAR(255),                  -- Pinata CID
    status              ENUM('pending','verified','rejected') DEFAULT 'pending',
    rejection_reason    TEXT,
    submitted_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    verified_at         DATETIME,
    verified_by         INT,                           -- Admin user ID
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (verified_by) REFERENCES users(id)
);

-- ── PARCELS ──────────────────────────────────────────────────
CREATE TABLE parcels (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    parcel_number       VARCHAR(100) UNIQUE,          -- Auto-generated TC-XXXXX-YYYY
    title               VARCHAR(300) NOT NULL,
    location_address    VARCHAR(500) NOT NULL,
    size_sqm            DECIMAL(15,4),
    property_type       ENUM('residential','commercial','agricultural','industrial','mixed','public','restricted') DEFAULT 'residential',
    description         TEXT,
    gps_lat             DECIMAL(10,8),
    gps_lng             DECIMAL(11,8),
    coordinates_json    LONGTEXT,                      -- GeoJSON polygon
    status              ENUM('pending','owned','transferred','disputed','restricted','public_use','rejected') DEFAULT 'pending',
    owner_id            INT,
    document_hash       VARCHAR(64),                   -- Combined document hash for blockchain
    ipfs_hash           VARCHAR(255),                  -- Combined IPFS CID
    blockchain_tx_hash  VARCHAR(100),                  -- Contract interaction tx
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id)
);

-- ── PARCEL DOCUMENTS ─────────────────────────────────────────
CREATE TABLE parcel_documents (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    parcel_id       INT NOT NULL,
    file_name       VARCHAR(300),
    file_type       VARCHAR(100),
    file_size       INT,
    sha256_hash     VARCHAR(64) NOT NULL,
    ipfs_hash       VARCHAR(255),
    uploaded_by     INT,
    uploaded_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id)
);

-- ── PENDING REGISTRATIONS ────────────────────────────────────
CREATE TABLE pending_registrations (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    applicant_id        INT NOT NULL,
    parcel_id           INT NOT NULL,
    status              ENUM('submitted','under_review','approved','rejected') DEFAULT 'submitted',
    admin_notes         TEXT,
    reviewed_by         INT,
    reviewed_at         DATETIME,
    submitted_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (applicant_id) REFERENCES users(id),
    FOREIGN KEY (parcel_id) REFERENCES parcels(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- ── TRANSFERS (No price - ownership transfer only) ───────────
CREATE TABLE transfers (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    parcel_id           INT NOT NULL,
    sender_id           INT NOT NULL,
    recipient_id        INT NOT NULL,
    transfer_type       ENUM('sale','gift','inheritance','court_order','admin_transfer') DEFAULT 'sale',
    supporting_doc_hash VARCHAR(64),
    supporting_ipfs     VARCHAR(255),
    status              ENUM('pending','approved','completed','rejected') DEFAULT 'pending',
    admin_notes         TEXT,
    reviewed_by         INT,
    reviewed_at         DATETIME,
    blockchain_tx_hash  VARCHAR(100),
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id),
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (recipient_id) REFERENCES users(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id)
);

-- ── DISPUTES (Off-chain resolution, on-chain if ownership change) ──
CREATE TABLE disputes (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    parcel_id           INT NOT NULL,
    complainant_id      INT NOT NULL,
    respondent_id       INT,
    dispute_type        ENUM('ownership','boundary','fraud','transfer','public_land','other') DEFAULT 'ownership',
    description         TEXT NOT NULL,
    evidence_ipfs_hash  VARCHAR(255),
    status              ENUM('open','under_review','resolved_complainant','resolved_respondent','dismissed') DEFAULT 'open',
    resolution_notes    TEXT,
    outcome             ENUM('ownership_changed','boundary_adjusted','status_changed','no_change') DEFAULT NULL,
    resolved_by         INT,
    resolved_at         DATETIME,
    blockchain_tx_hash  VARCHAR(100),                  -- If ownership change recorded on-chain
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (parcel_id) REFERENCES parcels(id),
    FOREIGN KEY (complainant_id) REFERENCES users(id),
    FOREIGN KEY (respondent_id) REFERENCES users(id),
    FOREIGN KEY (resolved_by) REFERENCES users(id)
);

-- ── DISPUTE VOTES (Validator review) ─────────────────────────
CREATE TABLE dispute_votes (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    dispute_id      INT NOT NULL,
    voter_id        INT NOT NULL,
    vote            ENUM('support','oppose') NOT NULL,
    notes           TEXT,
    voted_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_vote (dispute_id, voter_id),
    FOREIGN KEY (dispute_id) REFERENCES disputes(id),
    FOREIGN KEY (voter_id) REFERENCES users(id)
);

-- ── NOTIFICATIONS ────────────────────────────────────────────
CREATE TABLE notifications (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    type            VARCHAR(50) NOT NULL,
    title           VARCHAR(200) NOT NULL,
    message         TEXT,
    reference_id    INT,
    reference_type  VARCHAR(50),
    is_read         TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ── AUDIT LOG ────────────────────────────────────────────────
CREATE TABLE audit_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT,
    action          VARCHAR(100) NOT NULL,
    entity_type     VARCHAR(50),
    entity_id       INT,
    old_value       TEXT,
    new_value       TEXT,
    notes           TEXT,
    ip_address      VARCHAR(50),
    blockchain_tx   VARCHAR(100),
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ── SESSIONS (Traditional Auth) ──────────────────────────────
CREATE TABLE sessions (
    id              VARCHAR(128) PRIMARY KEY,
    user_id         INT NOT NULL,
    ip_address      VARCHAR(50),
    user_agent      TEXT,
    data            TEXT,
    expires_at      DATETIME NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- ── INDEXES ──────────────────────────────────────────────────
CREATE INDEX idx_parcels_status      ON parcels(status);
CREATE INDEX idx_parcels_owner       ON parcels(owner_id);
CREATE INDEX idx_transfers_parcel    ON transfers(parcel_id);
CREATE INDEX idx_transfers_status    ON transfers(status);
CREATE INDEX idx_disputes_parcel     ON disputes(parcel_id);
CREATE INDEX idx_disputes_status     ON disputes(status);
CREATE INDEX idx_notif_user          ON notifications(user_id, is_read);
CREATE INDEX idx_audit_entity        ON audit_log(entity_type, entity_id);
CREATE INDEX idx_sessions_user       ON sessions(user_id);
CREATE INDEX idx_sessions_expiry     ON sessions(expires_at);

-- ── SEED ADMIN ───────────────────────────────────────────────
-- Password: Admin@123 (bcrypt hash)
INSERT INTO users (username, email, password_hash, full_name, role, wallet_address) 
VALUES ('admin', 'terrachain16@gmail.com', '$2y$12$mUmCFN6AJHVnXt2NpEe6dejS.S8nVClsak42Lajkegk2BIFzwMH3q', 'System Administrator', 'admin', '0x31c27aA9E8b8a8b376de6CCd8133F0870F09740e');