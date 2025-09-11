-- Migration: Additional Metadata Extraction Fields
-- Add fields for Discord, star ratings, registration dates, and end date
-- Created: 2025-09-11

-- Add new columns for additional tournament metadata
ALTER TABLE tournaments ADD COLUMN discord_link VARCHAR(100);
ALTER TABLE tournaments ADD COLUMN star_rating_min DECIMAL(3,1);
ALTER TABLE tournaments ADD COLUMN star_rating_max DECIMAL(3,1);
ALTER TABLE tournaments ADD COLUMN star_rating_qualifier DECIMAL(3,1);
ALTER TABLE tournaments ADD COLUMN end_date DATETIME;

-- Add indexes for new searchable fields
CREATE INDEX idx_tournaments_discord_link ON tournaments(discord_link);
CREATE INDEX idx_tournaments_star_rating ON tournaments(star_rating_min, star_rating_max);
CREATE INDEX idx_tournaments_end_date ON tournaments(end_date);

-- Migration complete
-- New fields support additional metadata extraction:
-- - discord_link: Discord server invite code (without full URL)
-- - star_rating_min: Minimum star rating for tournament maps
-- - star_rating_max: Maximum star rating for tournament maps
-- - star_rating_qualifier: Qualifier star rating (if different from main range)
-- - end_date: Tournament end date (Grand Final date converted to Sunday)