ALTER TABLE restaurant
DROP COLUMN restaurant_location,
ADD latitude DECIMAL(10,7),
ADD longitude DECIMAL(10,7),
ADD address VARCHAR(255);
