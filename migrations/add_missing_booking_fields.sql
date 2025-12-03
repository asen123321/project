-- Migration: Add missing fields to booking and stylist tables
-- Run this SQL script to add the required columns for phone, calendar event ID, and stylist-user relationship

-- Add missing columns to booking table
ALTER TABLE booking
ADD COLUMN IF NOT EXISTS client_name VARCHAR(255) DEFAULT NULL COMMENT 'Client full name',
ADD COLUMN IF NOT EXISTS client_email VARCHAR(255) DEFAULT NULL COMMENT 'Client email address',
ADD COLUMN IF NOT EXISTS client_phone VARCHAR(50) DEFAULT NULL COMMENT 'Client phone number',
ADD COLUMN IF NOT EXISTS google_calendar_event_id VARCHAR(255) DEFAULT NULL COMMENT 'Google Calendar event ID for sync';

-- Add index on google_calendar_event_id for faster lookups
CREATE INDEX IF NOT EXISTS idx_booking_calendar_event ON booking(google_calendar_event_id);

-- Add user relationship to stylist table
ALTER TABLE stylist
ADD COLUMN IF NOT EXISTS user_id INT DEFAULT NULL COMMENT 'Link to user account',
ADD CONSTRAINT IF NOT EXISTS fk_stylist_user FOREIGN KEY (user_id) REFERENCES user(id) ON DELETE SET NULL;

-- Create index on stylist user_id
CREATE INDEX IF NOT EXISTS idx_stylist_user ON stylist(user_id);

-- Verify the changes
SELECT 'Booking table columns:' as info;
DESCRIBE booking;

SELECT '' as separator;
SELECT 'Stylist table columns:' as info;
DESCRIBE stylist;

SELECT '' as separator;
SELECT 'Migration completed successfully!' as status;
