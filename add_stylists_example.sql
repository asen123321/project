-- Example SQL to add stylists to your database
-- Run this in your database to add sample stylists

-- Add Stylist 1
INSERT INTO stylist (name, bio, photo_url, specialization, is_active, user_id)
VALUES (
    'Sarah Johnson',
    '15+ years of experience in cutting-edge hair design and color transformation',
    'https://images.unsplash.com/photo-1560066984-138dadb4c035?w=400&h=400&fit=crop',
    'Master Stylist',
    1,
    NULL
);

-- Add Stylist 2
INSERT INTO stylist (name, bio, photo_url, specialization, is_active, user_id)
VALUES (
    'Michael Chen',
    'Award-winning colorist with a passion for creativity and modern techniques',
    'https://images.unsplash.com/photo-1562322140-8baeececf3df?w=400&h=400&fit=crop',
    'Color Specialist',
    1,
    NULL
);

-- Add Stylist 3
INSERT INTO stylist (name, bio, photo_url, specialization, is_active, user_id)
VALUES (
    'Emily Rodriguez',
    'Specialist in contemporary cuts, bridal styling, and special occasions',
    'https://images.unsplash.com/photo-1580618672591-eb180b1a973f?w=400&h=400&fit=crop',
    'Senior Stylist',
    1,
    NULL
);

-- Add Stylist 4
INSERT INTO stylist (name, bio, photo_url, specialization, is_active, user_id)
VALUES (
    'David Martinez',
    'Innovative stylist with expertise in modern techniques and trend-setting styles',
    'https://images.unsplash.com/photo-1595475884562-073c962f970f?w=400&h=400&fit=crop',
    'Hair Artist',
    1,
    NULL
);

-- Verify inserted data
SELECT id, name, specialization, is_active FROM stylist;
