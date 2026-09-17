-- v5: hero image focal-point control (fixes odd cropping on wide hero bands)
USE suite_booking_platform;

ALTER TABLE sites
  ADD COLUMN hero_position VARCHAR(10) NOT NULL DEFAULT 'center' AFTER hero_image;
