-- Default settings for Heartbeat Heaven.
-- Run this once after importing moonlight_db.sql:
--   mysql -u root -p moonlight_db < database/seed_settings.sql
--
-- Fill in the empty values (smtp_username, smtp_password, groq_api_key) with your
-- own details, either here or later from Admin -> System Settings.
-- Re-running this file will not overwrite values you have already changed.

INSERT INTO system_settings (setting_key, setting_val) VALUES
  -- General
  ('site_name',                 'Heartbeat Heaven'),
  ('site_tagline',              'Animal Rescue & Sanctuary'),
  ('site_description',          'Dedicated to providing a safe haven for abandoned animals. Every life finds its light.'),
  ('sanctuary_address',         'Dhaka, Bangladesh'),
  ('contact_email',             ''),
  ('facebook_url',              ''),
  ('instagram_url',             ''),
  ('maintenance_mode',          '0'),
  -- Security
  ('session_timeout_minutes',   '30'),
  ('password_min_length',       '8'),
  ('pw_require_upper',          '1'),
  ('pw_require_number',         '1'),
  ('pw_require_special',        '0'),
  -- Email (SMTP) - needed for registration OTP and notifications
  ('smtp_host',                 'smtp.gmail.com'),
  ('smtp_port',                 '587'),
  ('smtp_username',             ''),
  ('smtp_password',             ''),
  ('smtp_sender_name',          'Heartbeat Heaven'),
  -- AI & chatbot
  ('groq_api_key',              ''),
  ('chatbot_enabled',           '1'),
  ('chatbot_fallback_message',  'Sorry, I am unavailable right now.'),
  ('triage_enabled',            '1'),
  ('triage_low_threshold',      '2'),
  ('triage_medium_threshold',   '3'),
  -- Email notifications
  ('notify_sos',                '1'),
  ('notify_adoption',           '1'),
  ('notify_jobs',               '1')
ON DUPLICATE KEY UPDATE setting_val = setting_val;
