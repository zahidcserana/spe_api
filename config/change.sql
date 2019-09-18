ALTER TABLE `order_items` ADD `server_item_id` INT NULL AFTER `is_status_updated`;
ALTER TABLE `order_items` ADD `is_status_sync` BOOLEAN NULL DEFAULT FALSE AFTER `is_status_updated`;
ALTER TABLE `pharmacies` CHANGE `pharmacy_shop_branch_name` `pharmacy_shop_name` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;
ALTER TABLE `pharmacies` CHANGE `pharmacy_shop_branch_licence_no` `pharmacy_shop_licence_no` VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NULL DEFAULT NULL;


ALTER TABLE `orders` ADD `server_order_id` INT NULL AFTER `is_sync`;

ALTER TABLE `dgdasp`.`orders`
CHANGE COLUMN `total_amount` `total_amount` FLOAT(15,2) NULL DEFAULT '0.00' ,
CHANGE COLUMN `total_payble_amount` `total_payble_amount` FLOAT(15,2) NULL DEFAULT '0.00' ,
CHANGE COLUMN `total_advance_amount` `total_advance_amount` FLOAT(15,2) NULL DEFAULT '0.00' ;

ALTER TABLE `dgdasp`.`order_items`
CHANGE COLUMN `total` `total` FLOAT(15,2) NULL DEFAULT '0.00' ,
CHANGE COLUMN `tax` `tax` FLOAT(15,2) NULL DEFAULT '0.00' ;


SET SQL_SAFE_UPDATES = 0;
update dgdasp.orders set orders.tax=0 where orders.tax is null;
update dgdasp.orders set orders.quantity=0 where orders.quantity is null;
update dgdasp.orders set orders.discount=0 where orders.discount is null;
update dgdasp.orders set orders.total_advance_amount=0 where orders.total_advance_amount is null;
update dgdasp.orders set orders.total_amount=0 where orders.total_amount is null;
update dgdasp.orders set orders.total_due_amount=0 where orders.total_due_amount is null;
update dgdasp.orders set orders.total_payble_amount=0 where orders.total_payble_amount is null;
SET SQL_SAFE_UPDATES = 1;


ALTER TABLE `dgdasp`.`mrs`
ADD COLUMN `pharmacy_branch_id` INT NULL AFTER `firebase_id`;


-- From HM Rubai
-- Date: 2019-08-05
ALTER TABLE `order_items`
ADD `pieces_per_strip` INT(11) NOT NULL DEFAULT '0' AFTER `discount`,
ADD `strip_per_box` INT(11) NOT NULL DEFAULT '0' AFTER `pieces_per_strip`,
ADD `free_qty` INT(11) NOT NULL DEFAULT '0' AFTER `strip_per_box`,
ADD `receive_qty` INT(11) NOT NULL DEFAULT '0' AFTER `free_qty`,
ADD `mrp` INT(11) NOT NULL DEFAULT '0' AFTER `receive_qty`,
ADD `trade_price` INT(11) NOT NULL DEFAULT '0' AFTER `mrp`;
ALTER TABLE `dgdasp`.`orders`
ADD COLUMN `order_type` ENUM('A', 'M') NULL DEFAULT 'M' AFTER `updated_at`;

ALTER TABLE `order_items` ADD `is_received` TINYINT NOT NULL AFTER `status`;

-- 18-08-2019
ALTER TABLE `dgdasp`.`sales_medicine_details`
RENAME TO  `dgdasp`.`sale_items` ;

ALTER TABLE `dgdasp`.`medicines`
ADD COLUMN `is_antibiotic` TINYINT NULL DEFAULT 0;


-- Inventory Details
CREATE TABLE `inventory_details` (
  `id` int(11) NOT NULL,
  `medicine_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT '0',
  `batch_no` varchar(255) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `company_id` int(11) DEFAULT NULL,
  `pharmacy_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

ALTER TABLE `inventory_details`
  ADD PRIMARY KEY (`id`);

CREATE TABLE `sales` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pharmacy_branch_id` int(11) DEFAULT NULL,
  `sale_date` date DEFAULT NULL,
  `customer_name` varchar(45) DEFAULT NULL,
  `customer_mobile` varchar(20) DEFAULT NULL,
  `invoice` varchar(255) DEFAULT NULL,
  `sub_total` float(15,2) DEFAULT NULL,
  `discount` float(15,2) DEFAULT NULL,
  `vat_amount` float(15,2) DEFAULT NULL,
  `total_payble_amount` float(15,2) DEFAULT NULL,
  `payment_status` tinyint(4) DEFAULT '0',
  `payment_type` varchar(45) DEFAULT NULL,
  `total_advance_amount` float(15,2) DEFAULT NULL,
  `total_due_amount` float(15,2) DEFAULT NULL,
  `refund_status` tinyint(4) DEFAULT '0',
  `refund_date` date DEFAULT NULL,
  `prescription_image` varchar(255) DEFAULT NULL,
  `remarks` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `token` varchar(255) DEFAULT NULL,
  `pharmacy_id` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_no_UNIQUE` (`invoice`)
) ENGINE=InnoDB AUTO_INCREMENT=35 DEFAULT CHARSET=utf8;

CREATE TABLE `sale_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) DEFAULT NULL,
  `medicine_id` int(11) DEFAULT NULL,
  `quantity` int(11) DEFAULT NULL,
  `refund_quantity` int(11) DEFAULT NULL,
  `exp_date` date DEFAULT NULL,
  `mfg_date` date DEFAULT NULL,
  `batch_no` varchar(255) DEFAULT NULL,
  `dar_no` varchar(255) DEFAULT NULL,
  `free_quantity` int(11) DEFAULT NULL,
  `unit_price` float(15,2) DEFAULT '0.00',
  `sub_total` float(15,2) DEFAULT '0.00',
  `discount` float(15,2) DEFAULT '0.00',
  `total_payble_amount` float(15,2) DEFAULT '0.00',
  `refund_amount` float(15,2) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `unit_type` enum('PCS','STRIP','BOX') DEFAULT 'PCS',
  `company_id` int(11) DEFAULT NULL,
  `power` varchar(100) DEFAULT NULL,
  `mrp` float(15,2) DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=56 DEFAULT CHARSET=utf8;

ALTER TABLE `inventory_details` ADD `pieces_per_strip` INT(11) NULL DEFAULT '0' AFTER `exp_date`, ADD `strip_per_box` INT(11) NULL DEFAULT '0' AFTER `pieces_per_strip`;

-- Date: 22-08-2019
ALTER TABLE `inventory_details` ADD `mrp` INT(11) NULL DEFAULT '0' AFTER `quantity`;

ALTER TABLE `inventories` CHANGE `pharmacy_shop_branch_id` `pharmacy_branch_id` INT(11) NULL DEFAULT NULL;

ALTER TABLE `inventories` ADD `company_id` INT(11) NULL DEFAULT '0' AFTER `medicine_id`;

-- after new instalation
TRUNCATE `carts`;
TRUNCATE `cart_items`;
TRUNCATE `damage_items`;
TRUNCATE `inventories`;
TRUNCATE `inventory_details`;
TRUNCATE `notifications`;
TRUNCATE `orders`;
TRUNCATE `order_items`;
TRUNCATE `purchases`;
TRUNCATE `purchase_medicine_details`;
TRUNCATE `sales`;
TRUNCATE `sale_items`;

-- 05-09-2019
ALTER TABLE `sales` ADD `is_sync` BOOLEAN NULL DEFAULT FALSE AFTER `file_name`;
ALTER TABLE `sales` ADD `server_sale_id` INT NULL AFTER `is_sync`;
ALTER TABLE `sale_items` ADD `server_item_id` INT NULL AFTER `mrp`;
ALTER TABLE `dgdasp_new_db`.`sales`
DROP INDEX `invoice_no_UNIQUE` ;
CREATE TRIGGER cart_token BEFORE INSERT ON carts FOR EACH ROW SET NEW.token = UUID();
