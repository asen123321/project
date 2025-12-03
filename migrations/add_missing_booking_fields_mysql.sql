-- Migration: Add missing fields to booking and stylist tables (MySQL Compatible)
-- This version doesn't use IF NOT EXISTS for maximum compatibility
-- Run this ONLY if the columns don't already exist

-- ============================================
-- PART 1: Add columns to booking table
-- ============================================

-- Add client_name column
ALTER TABLE booking
ADD COLUMN client_name VARCHAR(255) DEFAULT NULL COMMENT 'Client full name';

-- Add client_email column
ALTER TABLE booking
ADD COLUMN client_email VARCHAR(255) DEFAULT NULL COMMENT 'Client email address';

-- Add client_phone column
ALTER TABLE booking
ADD COLUMN client_phone VARCHAR(50) DEFAULT NULL COMMENT 'Client phone number';

-- Add google_calendar_event_id column
ALTER TABLE booking
ADD COLUMN google_calendar_event_id VARCHAR(255) DEFAULT NULL COMMENT 'Google Calendar event ID for sync';

-- Add index on google_calendar_event_id for faster lookups
CREATE INDEX idx_booking_calendar_event ON booking(google_calendar_event_id);

-- ============================================
-- PART 2: Add user relationship to stylist table
-- ============================================

-- Add user_id column to link stylist to user account
ALTER TABLE stylist
ADD COLUMN user_id INT DEFAULT NULL COMMENT 'Link to user account';

-- Add foreign key constraint
ALTER TABLE stylist
ADD CONSTRAINT fk_stylist_user
FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE SET NULL;

-- Create index on stylist user_id
CREATE INDEX idx_stylist_user ON stylist(user_id);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

-- Check booking table structure
DESCRIBE booking;

-- Check stylist table structure
DESCRIBE stylist;

-- Count bookings with NULL fields (should decrease after new bookings)
SELECT
    COUNT(*) as total_bookings,
    SUM(CASE WHEN client_phone IS NULL THEN 1 ELSE 0 END) as null_phone,
    SUM(CASE WHEN google_calendar_event_id IS NULL THEN 1 ELSE 0 END) as null_event_id
FROM booking;
