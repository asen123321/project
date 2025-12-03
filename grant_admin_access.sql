-- Grant ROLE_ADMIN to asem4o@gmail.com
-- Run this SQL command to give admin access

UPDATE user
SET roles = '["ROLE_ADMIN", "ROLE_USER"]'
WHERE email = 'asem4o@gmail.com';

-- Verify the update
SELECT id, email, username, roles FROM user WHERE email = 'asem4o@gmail.com';
