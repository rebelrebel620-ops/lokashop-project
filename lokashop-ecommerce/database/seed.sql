INSERT INTO users (id,name,email,phone,password,role,approval_status,active,created_at,updated_at) VALUES
(1,'Demo Buyer','buyer@lokashop.test','09000000001','$2b$12$bDTUmgl3bCnOHt3zRsM.suUoqgQXslTMAJgcp/Lb75MSoe7RUN2ZK','buyer','approved',1,NOW(),NOW()),
(2,'Demo Seller','seller@lokashop.test','09000000002','$2b$12$bDTUmgl3bCnOHt3zRsM.suUoqgQXslTMAJgcp/Lb75MSoe7RUN2ZK','seller','approved',1,NOW(),NOW()),
(3,'Demo Admin','admin@lokashop.test','09000000003','$2b$12$bDTUmgl3bCnOHt3zRsM.suUoqgQXslTMAJgcp/Lb75MSoe7RUN2ZK','admin','approved',1,NOW(),NOW()),
(4,'Demo Sorting Center','sorting_center@lokashop.test','09000000004','$2b$12$bDTUmgl3bCnOHt3zRsM.suUoqgQXslTMAJgcp/Lb75MSoe7RUN2ZK','sorting_center','approved',1,NOW(),NOW()),
(5,'Demo Rider','rider@lokashop.test','09000000005','$2b$12$bDTUmgl3bCnOHt3zRsM.suUoqgQXslTMAJgcp/Lb75MSoe7RUN2ZK','rider','approved',1,NOW(),NOW());
INSERT INTO seller_businesses (user_id,name,category,created_at,updated_at) VALUES (2,'Green Goods','Home',NOW(),NOW());
INSERT INTO sorting_centers (id,user_id,name,created_at,updated_at) VALUES (1,4,'Laguna Center',NOW(),NOW());
INSERT INTO delivery_areas (id,center_id,name,city,province,rider_id,created_at,updated_at) VALUES (1,1,'Area A','Santa Cruz','Laguna',5,NOW(),NOW());
INSERT INTO addresses (id,user_id,recipient,phone,street,barangay,city,province,postal_code,is_default,created_at,updated_at) VALUES (1,1,'Demo Buyer','09000000001','12 Main Street','Poblacion','Santa Cruz','Laguna','4009',1,NOW(),NOW());
INSERT INTO categories (id,name,created_at,updated_at) VALUES (1,'Home',NOW(),NOW());
INSERT INTO products (id,seller_id,category_id,name,description,price,stock,created_at,updated_at) VALUES (1,2,1,'Reusable Bag','Reusable green shopping bag',199.00,30,NOW(),NOW());
INSERT INTO product_variations (id,product_id,name,price_adjustment,stock,created_at,updated_at) VALUES (1,1,'Forest green',0,15,NOW(),NOW()),(2,1,'Bright green',0,15,NOW(),NOW());
INSERT INTO vouchers (seller_id,code,discount_percent,active,created_at,updated_at) VALUES (2,'GREEN10',10,1,NOW(),NOW());
INSERT INTO announcements (title,body,created_at,updated_at) VALUES ('Welcome','Welcome to LokaShop.',NOW(),NOW());
