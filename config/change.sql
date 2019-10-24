ALTER TABLE `sale_items` ADD `return_status` ENUM('RETURN','CHANGE','OK') NULL DEFAULT 'OK' AFTER `server_item_id`;
ALTER TABLE `sale_items` ADD `change_log` TEXT NULL AFTER `return_status`;
ALTER TABLE `sale_items` ADD `updated_by` INT NULL AFTER `change_log`;
ALTER TABLE `sales` ADD `status` ENUM('PENDING','COMPLETE','DUE','CANCEL') NULL DEFAULT 'COMPLETE' AFTER `server_sale_id`;
ALTER TABLE `sales` ADD `due_log` TEXT NULL AFTER `status`;
ALTER TABLE `sales` ADD `updated_by` INT NULL AFTER `created_by`;
ALTER TABLE `sale_items` ADD `product_type` INT NULL AFTER `updated_by`;
ALTER TABLE `cart_items` ADD `product_type` INT NULL AFTER `updated_at`;
ALTER TABLE `products` ADD `is_sync` BOOLEAN NULL DEFAULT FALSE AFTER `pharmacy_id`;
ALTER TABLE `products` ADD `sale_quantity` INT NULL DEFAULT '0' AFTER `quantity`;

CREATE TABLE `spe`.`subscriptions` ( `id` INT NOT NULL AUTO_INCREMENT , `package` JSON NULL , `status` ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE' , `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP , PRIMARY KEY (`id`)) ENGINE = InnoDB;

ALTER TABLE `cart_items` ADD `tp` FLOAT(15,2) NOT NULL DEFAULT '0' AFTER `unit_price`;
ALTER TABLE `sale_items` ADD `tp` FLOAT(15,2) NOT NULL DEFAULT '0' AFTER `mrp`;
