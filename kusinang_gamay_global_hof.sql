CREATE TABLE IF NOT EXISTS kg_global_scores (
  player_key VARCHAR(96) NOT NULL,
  nickname VARCHAR(24) NOT NULL,
  best_score INT UNSIGNED NOT NULL DEFAULT 0,
  best_combo INT UNSIGNED NOT NULL DEFAULT 0,
  best_level VARCHAR(24) NOT NULL DEFAULT '1',
  mode VARCHAR(24) NOT NULL DEFAULT 'STORY',
  total_runs INT UNSIGNED NOT NULL DEFAULT 1,
  last_session_id VARCHAR(96) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (player_key),
  INDEX idx_global_rank (best_score, best_combo),
  INDEX idx_updated_at (updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
