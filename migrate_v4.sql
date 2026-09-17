-- v4: tiered room pricing (3-hour package + extra hour rate), hourly price becomes optional display
USE suite_booking_platform;

ALTER TABLE rooms
  ADD COLUMN show_price_per_hour TINYINT(1) NOT NULL DEFAULT 1 AFTER price_per_hour,
  ADD COLUMN price_3h DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_per_day,
  ADD COLUMN price_extra_hour DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER price_3h;
