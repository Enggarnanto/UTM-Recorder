-- MySQL/MariaDB, InnoDB required. Add the application's configured table prefix
-- to BOTH names below before importing if DBPrefix/dbprefix is not empty.
CREATE TABLE IF NOT EXISTS utr_integration_sites (
  site_id varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  home_url varchar(2048) NOT NULL,
  token_hash char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  enabled tinyint unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (site_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS utr_wa_clicks (
  site_id varchar(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  event_id bigint unsigned NOT NULL,
  occurred_at datetime NOT NULL COMMENT 'UTC',
  visitor_id varchar(36) NOT NULL DEFAULT '',
  session_id varchar(36) NOT NULL DEFAULT '',
  account_id bigint unsigned NOT NULL DEFAULT 0,
  account_name varchar(191) NOT NULL DEFAULT '',
  phone varchar(20) NOT NULL DEFAULT '',
  link_type varchar(20) NOT NULL DEFAULT '',
  page_path varchar(2048) NOT NULL DEFAULT '',
  utm_source varchar(191) NOT NULL DEFAULT '',
  utm_medium varchar(191) NOT NULL DEFAULT '',
  utm_campaign varchar(191) NOT NULL DEFAULT '',
  utm_term varchar(191) NOT NULL DEFAULT '',
  utm_content varchar(191) NOT NULL DEFAULT '',
  utm_match_type varchar(191) NOT NULL DEFAULT '',
  message_source varchar(2048) NOT NULL DEFAULT '',
  message_source_recorded tinyint unsigned NOT NULL DEFAULT 0,
  received_at_utc datetime NOT NULL COMMENT 'UTC',
  PRIMARY KEY (site_id, event_id),
  KEY occurred_at (occurred_at),
  KEY source_time (utm_source, occurred_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
