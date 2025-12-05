ALTER TABLE restaurant
ADD COLUMN restaurant_image VARCHAR(500) NULL AFTER address;

ALTER TABLE restaurant
ADD COLUMN discount VARCHAR(100) NULL AFTER restaurant_image;

ALTER TABLE restaurant
ADD COLUMN rating DECIMAL(2,1) DEFAULT 4.0 AFTER discount;