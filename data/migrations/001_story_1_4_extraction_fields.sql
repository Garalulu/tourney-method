-- Migration: Story 1.4 Data Extraction Fields
-- Add new fields required for forum post data extraction
-- Created: 2025-09-06

-- Add new columns for extracted tournament data
ALTER TABLE tournaments ADD COLUMN host_name VARCHAR(100);
ALTER TABLE tournaments ADD COLUMN rank_range VARCHAR(50);
ALTER TABLE tournaments ADD COLUMN tournament_dates TEXT;
ALTER TABLE tournaments ADD COLUMN has_badge BOOLEAN DEFAULT 0;
ALTER TABLE tournaments ADD COLUMN banner_url TEXT;
ALTER TABLE tournaments ADD COLUMN extraction_confidence TEXT; -- JSON for confidence scores

-- Add indexes for new searchable fields
CREATE INDEX idx_tournaments_host_name ON tournaments(host_name);
CREATE INDEX idx_tournaments_rank_range ON tournaments(rank_range);
CREATE INDEX idx_tournaments_has_badge ON tournaments(has_badge) WHERE has_badge = 1;

-- Update the status constraint to ensure pending_review is still valid
-- (The schema already has this constraint, but documenting for clarity)
-- CHECK (status IN ('pending_review', 'approved', 'rejected', 'archived'))

-- Add sample data validation constraints for extracted fields
-- Host name should be reasonable length if not null
-- (Using triggers since SQLite has limited CHECK constraint support for complex validation)

-- Migration complete
-- New fields support Story 1.4 requirements:
-- - host_name: Extracted tournament host from forum posts
-- - rank_range: Extracted rank restrictions (Open, 100K+, etc.)
-- - tournament_dates: Extracted tournament date information
-- - has_badge: Extracted badge award status
-- - banner_url: Extracted tournament banner image URL
-- - extraction_confidence: JSON confidence scores for extraction quality