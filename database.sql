-- Andoy Cakes & Pastries Corner - ordering system database
-- Option for localhost / phpMyAdmin: Import this file. (On a live website, use install.php instead and delete this file.)

CREATE DATABASE IF NOT EXISTS kakanin_ordering_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE kakanin_ordering_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS login_attempts, reviews, messages, notifications, remember_tokens, payments, order_items, orders, cart_items, carts, products, categories, settings, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL DEFAULT '',
  address TEXT NULL,
  password_hash VARCHAR(255) NOT NULL,
  password_enc VARCHAR(255) NULL,          -- encrypted copy so the super admin can view customer and admin passwords
  role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  is_super TINYINT(1) NOT NULL DEFAULT 0,
  banned TINYINT(1) NOT NULL DEFAULT 0,
  fresh TINYINT(1) NOT NULL DEFAULT 0,     -- new customer the admin has not seen yet
  avatar VARCHAR(100) NULL,
  theme ENUM('auto','light','dark') NOT NULL DEFAULT 'auto',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS remember_tokens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  INDEX (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT NULL,
  name VARCHAR(120) NOT NULL,
  description TEXT NULL,
  price DECIMAL(10,2) NOT NULL,
  image VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS carts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL UNIQUE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  cart_id INT NOT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cart_product (cart_id, product_id),
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(30) NOT NULL UNIQUE,
  user_id INT NULL,                         -- kept NULL if the customer account is deleted, so sales history stays
  customer_name VARCHAR(120) NOT NULL,
  fulfillment ENUM('pickup','delivery') NOT NULL,
  delivery_address TEXT NULL,
  contact_phone VARCHAR(30) NOT NULL,
  notes VARCHAR(500) NOT NULL DEFAULT '',
  due_date DATE NOT NULL,                   -- day the customer wants delivery or pickup
  due_slot VARCHAR(40) NOT NULL DEFAULT '',
  subtotal DECIMAL(10,2) NOT NULL,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  status ENUM('pending','confirmed','preparing','ready','out_for_delivery','completed','cancelled') NOT NULL DEFAULT 'pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (status), INDEX (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NULL,
  product_name VARCHAR(120) NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL UNIQUE,
  method ENUM('gcash','cash') NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reference_no VARCHAR(60) NOT NULL DEFAULT '',
  proof_image VARCHAR(100) NULL,
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending',
  admin_note VARCHAR(255) NOT NULL DEFAULT '',
  seen TINYINT(1) NOT NULL DEFAULT 0,       -- admin has looked at the latest screenshot
  verified_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  for_admin TINYINT(1) NOT NULL DEFAULT 0,  -- 1 = shown to every admin
  user_id INT NULL,                         -- customer who receives it when for_admin = 0
  order_id INT NULL,
  message VARCHAR(500) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  seen TINYINT(1) NOT NULL DEFAULT 0,       -- drives the red dot on the Orders tab
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (for_admin, is_read), INDEX (user_id, is_read)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  customer_id INT NOT NULL,                 -- every chat belongs to one customer
  sender_id INT NULL,                       -- the customer or admin who wrote it
  from_role ENUM('customer','admin') NOT NULL,
  body VARCHAR(1000) NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (customer_id),
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT NULL,
  order_id INT NOT NULL,
  reviewer_name VARCHAR(120) NOT NULL,
  stars TINYINT NOT NULL,
  comment VARCHAR(300) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_review (order_id, product_id, user_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value TEXT NOT NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  email VARCHAR(160) NOT NULL DEFAULT '',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (ip, created_at)
) ENGINE=InnoDB;

INSERT INTO users (full_name, email, phone, password_hash, role, is_super) VALUES
('Shop Administrator', 'admin@kakanin.local', '09000000000', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 0),
('Super Administrator', 'superadmin@kakanin.local', '09000000001', '$2y$12$di1YV2kq7TkIdZxUw296W.5maaow7A5v4Z0vFwDwYdNCKI0Cg3wlO', 'admin', 1);

INSERT INTO categories (name, description) VALUES
('Steamed', 'Soft rice cakes cooked in a steamer'),
('Sticky & Glutinous', 'Chewy cakes made with malagkit'),
('Baked & Grilled', 'Cakes finished over heat'),
('Jams & Spreads', 'Sweet halaya and toppings');

INSERT INTO products (category_id, name, description, price) VALUES
(1, 'Puto (Box of 12)', 'Fluffy steamed rice cakes topped with cheese.', 120.00),
(1, 'Kutsinta (Box of 12)', 'Chewy brown rice cakes with fresh grated coconut.', 110.00),
(2, 'Biko (Medium Tray)', 'Sticky rice in coconut milk and brown sugar, topped with latik.', 280.00),
(2, 'Suman sa Lihiya (6 pcs)', 'Glutinous rice wrapped in banana leaf.', 150.00),
(2, 'Sapin-sapin (Round Tray)', 'Layered rice cake in ube, langka and coconut.', 320.00),
(3, 'Bibingka (Whole)', 'Baked rice cake with salted egg and cheese.', 180.00),
(1, 'Puto Bumbong (6 pcs)', 'Purple steamed rice cakes with butter and muscovado.', 130.00),
(4, 'Ube Halaya (Jar)', 'Smooth purple yam jam.', 200.00);

INSERT INTO settings (setting_key, setting_value) VALUES
('shop_name', 'Andoy Cakes & Pastries Corner'),
('shop_address', 'Davao City, Philippines'),
('shop_phone', '09000000000'),
('gcash_number', '09XX XXX XXXX'),
('gcash_name', 'Your Shop Name'),
('delivery_fee', '50.00'),
('delivery_note', 'Delivery within Davao City only.');
