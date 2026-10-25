-- XEVION FaceMatch — database schema
-- MySQL 5.7.8+ / MariaDB 10.3+  ·  utf8mb4
-- Applied automatically by the installer (install/index.php or cli/install.php).

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS admins (
  id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email                VARCHAR(190) NOT NULL,
  name                 VARCHAR(80)  NOT NULL,
  password_hash        VARCHAR(255) NOT NULL,
  role                 ENUM('super_admin','admin','moderator','ad_manager') NOT NULL DEFAULT 'moderator',
  is_active            TINYINT(1)   NOT NULL DEFAULT 1,
  must_change_password TINYINT(1)   NOT NULL DEFAULT 0,
  last_login_at        DATETIME     NULL,
  password_changed_at  DATETIME     NULL,
  created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_admin_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
  skey       VARCHAR(64) NOT NULL,
  svalue     TEXT        NULL,
  updated_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (skey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS competitions (
  id                 INT UNSIGNED NOT NULL AUTO_INCREMENT,
  slug               CHAR(12)     NOT NULL,
  title              VARCHAR(120) NOT NULL,
  category           VARCHAR(32)  NOT NULL,
  custom_category    VARCHAR(40)  NULL,
  description        VARCHAR(600) NULL,
  instructions       VARCHAR(300) NULL,
  visibility         ENUM('public','private') NOT NULL DEFAULT 'public',
  status             ENUM('active','closed','suspended','deleted') NOT NULL DEFAULT 'active',
  moderation_status  ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  results_visibility ENUM('live','after_close','restricted') NOT NULL DEFAULT 'live',
  is_featured        TINYINT(1)   NOT NULL DEFAULT 0,
  sponsor_name       VARCHAR(80)  NULL,
  sponsor_url        VARCHAR(500) NULL,
  closes_at          DATETIME     NOT NULL,
  closed_at          DATETIME     NULL,
  manage_token_hash  CHAR(64)     NOT NULL,
  invite_token_hash  CHAR(64)     NULL,
  invite_token_enc   VARCHAR(255) NULL,
  invite_expires_at  DATETIME     NULL,
  access_code_hash   VARCHAR(255) NULL,
  creator_ip_hash    CHAR(64)     NULL,
  votes_total        INT UNSIGNED NOT NULL DEFAULT 0,
  last_vote_at       DATETIME     NULL,
  created_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_comp_slug (slug),
  UNIQUE KEY uq_comp_manage (manage_token_hash),
  UNIQUE KEY uq_comp_invite (invite_token_hash),
  KEY idx_comp_listing (visibility, moderation_status, status, closes_at),
  KEY idx_comp_category (category),
  KEY idx_comp_created (created_at),
  KEY idx_comp_creator (creator_ip_hash, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS participants (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  competition_id INT UNSIGNED NOT NULL,
  public_id      CHAR(16)     NOT NULL,
  display_name   VARCHAR(40)  NOT NULL,
  position       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  image_file     VARCHAR(80)  NULL,
  thumb_file     VARCHAR(80)  NULL,
  image_status   ENUM('pending','approved','flagged','escalated','rejected','removed') NOT NULL DEFAULT 'pending',
  is_removed     TINYINT(1)   NOT NULL DEFAULT 0,
  removed_reason VARCHAR(255) NULL,
  moderated_at   DATETIME     NULL,
  moderated_by   INT UNSIGNED NULL,
  uploaded_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_part_public (public_id),
  KEY idx_part_comp (competition_id, is_removed),
  KEY idx_part_status (image_status),
  CONSTRAINT fk_part_comp FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- One row per counted pairwise decision. pair_low < pair_high always.
-- uq_vote_voter_pair makes a retried or replayed vote for the same pair by the
-- same browser impossible to insert twice; uq_vote_idem makes client retries idempotent.
CREATE TABLE IF NOT EXISTS votes (
  id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  competition_id  INT UNSIGNED NOT NULL,
  winner_id       INT UNSIGNED NOT NULL,
  loser_id        INT UNSIGNED NOT NULL,
  pair_low        INT UNSIGNED NOT NULL,
  pair_high       INT UNSIGNED NOT NULL,
  voter_hash      CHAR(64)     NOT NULL,
  ip_hash         CHAR(64)     NULL,
  idempotency_key CHAR(36)     NOT NULL,
  status          ENUM('counted','voided') NOT NULL DEFAULT 'counted',
  voided_reason   VARCHAR(255) NULL,
  voided_by       INT UNSIGNED NULL,
  voided_at       DATETIME     NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_vote_idem (idempotency_key),
  UNIQUE KEY uq_vote_voter_pair (competition_id, voter_hash, pair_low, pair_high),
  KEY idx_vote_comp (competition_id, status),
  KEY idx_vote_created (created_at),
  KEY idx_vote_ip (competition_id, ip_hash, pair_low, pair_high),
  CONSTRAINT fk_vote_comp FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
  CONSTRAINT fk_vote_winner FOREIGN KEY (winner_id) REFERENCES participants(id) ON DELETE CASCADE,
  CONSTRAINT fk_vote_loser FOREIGN KEY (loser_id) REFERENCES participants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  competition_id   INT UNSIGNED NULL,
  participant_id   INT UNSIGNED NULL,
  reason           ENUM('inappropriate','harassment','unauthorized','minor','abusive_category','impersonation','spam','other') NOT NULL,
  details          VARCHAR(1000) NULL,
  reporter_email   VARCHAR(190) NULL,
  reporter_ip_hash CHAR(64)     NULL,
  status           ENUM('open','investigating','resolved','dismissed') NOT NULL DEFAULT 'open',
  resolution_note  VARCHAR(500) NULL,
  handled_by       INT UNSIGNED NULL,
  handled_at       DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_rep_status (status, created_at),
  KEY idx_rep_comp (competition_id),
  KEY idx_rep_part (participant_id),
  CONSTRAINT fk_rep_comp FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE SET NULL,
  CONSTRAINT fk_rep_part FOREIGN KEY (participant_id) REFERENCES participants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_campaigns (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id         CHAR(12)     NOT NULL,
  name              VARCHAR(120) NOT NULL,
  advertiser        VARCHAR(120) NOT NULL,
  creative_type     ENUM('image','text') NOT NULL DEFAULT 'image',
  image_file        VARCHAR(80)  NULL,
  mobile_image_file VARCHAR(80)  NULL,
  headline          VARCHAR(90)  NULL,
  body_text         VARCHAR(160) NULL,
  cta_label         VARCHAR(30)  NULL,
  destination_url   VARCHAR(500) NOT NULL,
  placement         ENUM('top_banner','footer_banner') NOT NULL DEFAULT 'top_banner',
  priority          TINYINT UNSIGNED NOT NULL DEFAULT 5,
  status            ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
  starts_at         DATETIME     NOT NULL,
  ends_at           DATETIME     NOT NULL,
  budget_amount     DECIMAL(12,2) NULL,
  budget_currency   CHAR(3)      NULL,
  notes             TEXT         NULL,
  created_by        INT UNSIGNED NULL,
  created_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_ad_public (public_id),
  KEY idx_ad_serving (placement, status, starts_at, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS ad_stats_daily (
  campaign_id INT UNSIGNED NOT NULL,
  day         DATE         NOT NULL,
  impressions INT UNSIGNED NOT NULL DEFAULT 0,
  clicks      INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (campaign_id, day),
  CONSTRAINT fk_adstat_campaign FOREIGN KEY (campaign_id) REFERENCES ad_campaigns(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_log (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_id    INT UNSIGNED NULL,
  actor       VARCHAR(40)  NOT NULL DEFAULT 'admin',
  action      VARCHAR(64)  NOT NULL,
  target_type VARCHAR(32)  NULL,
  target_id   BIGINT UNSIGNED NULL,
  details     TEXT         NULL,
  ip_hash     CHAR(64)     NULL,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_audit_target (target_type, target_id),
  KEY idx_audit_admin (admin_id, created_at),
  KEY idx_audit_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
  rkey         CHAR(64)     NOT NULL,
  hits         INT UNSIGNED NOT NULL DEFAULT 0,
  window_start DATETIME     NOT NULL,
  PRIMARY KEY (rkey),
  KEY idx_rl_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS abuse_blocks (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  ip_hash    CHAR(64)     NOT NULL,
  reason     VARCHAR(255) NOT NULL,
  expires_at DATETIME     NOT NULL,
  created_by INT UNSIGNED NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_block_lookup (ip_hash, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name       VARCHAR(80)   NOT NULL,
  email      VARCHAR(190)  NOT NULL,
  topic      ENUM('general','advertising','privacy','takedown','other') NOT NULL DEFAULT 'general',
  message    VARCHAR(3000) NOT NULL,
  ip_hash    CHAR(64)      NULL,
  status     ENUM('new','read','archived') NOT NULL DEFAULT 'new',
  created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_msg_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Used when session_driver = 'db' (serverless hosts such as Vercel). Ids are SHA-256 hashed.
CREATE TABLE IF NOT EXISTS sessions (
  id         CHAR(64)       NOT NULL,
  data       MEDIUMBLOB     NOT NULL,
  expires_at INT UNSIGNED   NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sess_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Used when media_driver = 'db' (serverless hosts have no persistent disk).
CREATE TABLE IF NOT EXISTS media_files (
  mkey       VARCHAR(80)    NOT NULL,
  data       MEDIUMBLOB     NOT NULL,
  created_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (mkey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Internal values for environment-configured hosts: schema version and, when the
-- APP_SECRET variable is not set, the generated application secret. Never shown in the admin.
CREATE TABLE IF NOT EXISTS app_meta (
  mkey   VARCHAR(40)  NOT NULL,
  mvalue VARCHAR(255) NOT NULL,
  PRIMARY KEY (mkey)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
