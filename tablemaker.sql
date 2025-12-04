CREATE TABLE category (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    category_image VARCHAR(255),
    FOREIGN KEY (restaurant_id) REFERENCES restaurant(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);
