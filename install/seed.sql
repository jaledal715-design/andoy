-- Sample categories, products and shop settings (used by install.php)
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
