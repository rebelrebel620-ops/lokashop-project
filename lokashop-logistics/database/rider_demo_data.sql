-- OPTIONAL demo data for testing the Flutter rider app.
-- Run AFTER importing "lokashop_db - Copy.sql". Uses the seeded rider (users.id = 5).
-- Safe to re-run: it removes its own rows first (order ids 9001-9003).
USE lokashop_erp_demo;

DELETE FROM status_history WHERE parcel_id IN (9001,9002,9003);
DELETE FROM assignments    WHERE parcel_id IN (9001,9002,9003);
DELETE FROM parcels        WHERE id IN (9001,9002,9003);
DELETE FROM payments       WHERE order_id IN (9001,9002,9003);
DELETE FROM order_items    WHERE order_id IN (9001,9002,9003);
DELETE FROM orders         WHERE id IN (9001,9002,9003);

INSERT INTO orders (id,buyer_id,seller_id,address_id,status,total,discount,commission,created_at,updated_at) VALUES
(9001,1,2,1,'READY_FOR_PICKUP',199.00,0,0,NOW(),NOW()),
(9002,1,2,1,'ASSIGNED_TO_RIDER',398.00,0,0,NOW(),NOW()),
(9003,1,2,1,'ASSIGNED_TO_RIDER',199.00,0,0,NOW(),NOW());

INSERT INTO order_items (order_id,product_id,variation_id,name,quantity,unit_price,created_at,updated_at) VALUES
(9001,1,1,'Reusable Bag - Forest green',1,199.00,NOW(),NOW()),
(9002,1,2,'Reusable Bag - Bright green',2,199.00,NOW(),NOW()),
(9003,1,1,'Reusable Bag - Forest green',1,199.00,NOW(),NOW());

INSERT INTO payments (order_id,method,status,amount,created_at,updated_at) VALUES
(9001,'cod','pending',199.00,NOW(),NOW()),
(9002,'cod','pending',398.00,NOW(),NOW()),
(9003,'cod','pending',199.00,NOW(),NOW());

-- 9001: seller has it ready, rider already accepted the PICKUP  -> scan to confirm pickup
-- 9002: at the center, assigned to the rider, DELIVERY accepted -> scan: out for delivery / delivered
-- 9003: delivery only OFFERED -> scan or tap: accept first
INSERT INTO parcels (id,order_id,tracking_code,status,center_id,delivery_area_id,created_at,updated_at) VALUES
(9001,9001,'LKS-DEMO00000001','READY_FOR_PICKUP',NULL,NULL,NOW(),NOW()),
(9002,9002,'LKS-DEMO00000002','ASSIGNED_TO_RIDER',1,1,NOW(),NOW()),
(9003,9003,'LKS-DEMO00000003','ASSIGNED_TO_RIDER',1,1,NOW(),NOW());

INSERT INTO assignments (parcel_id,rider_id,kind,status,earnings,created_at,updated_at) VALUES
(9001,5,'pickup','accepted',30,NOW(),NOW()),
(9002,5,'delivery','accepted',50,NOW(),NOW()),
(9003,5,'delivery','offered',50,NOW(),NOW());

INSERT INTO status_history (parcel_id,from_status,to_status,actor_id,reason,created_at,updated_at) VALUES
(9001,'PREPARING','READY_FOR_PICKUP',2,NULL,NOW(),NOW()),
(9002,'SORTED','ASSIGNED_TO_RIDER',4,NULL,NOW(),NOW()),
(9003,'SORTED','ASSIGNED_TO_RIDER',4,NULL,NOW(),NOW());
