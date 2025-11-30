CREATE TABLE restaurant (
    restaurant_id INT AUTO_INCREMENT PRIMARY KEY,

    business_name VARCHAR(100) NOT NULL,
    name_per_cnic VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,

    business_type VARCHAR(100) NOT NULL,
    business_category VARCHAR(100) NOT NULL,
    business_update VARCHAR(100) NOT NULL,

    email VARCHAR(120) NOT NULL UNIQUE,
    phone VARCHAR(20) NOT NULL UNIQUE,

    password_hash VARCHAR(255) NOT NULL,

    restaurant_location INT NULL,
    otp INT NULL,   

    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
