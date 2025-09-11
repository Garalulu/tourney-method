-- Migration: Metadata Extraction Fields
-- Add normalized fields for tournament metadata extraction from titles
-- Created: 2025-09-11

-- Add new columns for normalized tournament metadata
ALTER TABLE tournaments ADD COLUMN team_vs INTEGER;
ALTER TABLE tournaments ADD COLUMN game_mode VARCHAR(10);
ALTER TABLE tournaments ADD COLUMN is_bws BOOLEAN DEFAULT 0;

-- Add indexes for new searchable fields
CREATE INDEX idx_tournaments_team_vs ON tournaments(team_vs);
CREATE INDEX idx_tournaments_game_mode ON tournaments(game_mode);
CREATE INDEX idx_tournaments_is_bws ON tournaments(is_bws) WHERE is_bws = 1;

-- Add constraints for field validation
-- team_vs should be 0-4 (0=special, 1=1v1, 2=2v2, 3=3v3, 4=4v4)
-- game_mode should be one of the standardized values

-- Migration complete
-- New fields support normalized metadata extraction:
-- - team_vs: Integer representation (1=1v1, 2=2v2, 0=special/variable)
-- - game_mode: Standardized game modes (STD, TAIKO, CATCH, MANIA4, MANIA7, MANIA0, ETC)
-- - is_bws: Boolean indicator for BWS tournaments