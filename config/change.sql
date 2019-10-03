ALTER TABLE `sale_items` ADD `return_status` ENUM('RETURN','CHANGE','OK') NULL DEFAULT 'OK' AFTER `server_item_id`;
ALTER TABLE `sale_items` ADD `change_log` TEXT NULL AFTER `return_status`;
ALTER TABLE `sale_items` ADD `updated_by` INT NULL AFTER `change_log`;
ALTER TABLE `sales` ADD `status` ENUM('PENDING','COMPLETE','DUE','CANCEL') NULL DEFAULT 'COMPLETE' AFTER `server_sale_id`;
ALTER TABLE `sales` ADD `due_log` TEXT NULL AFTER `status`;
ALTER TABLE `sales` ADD `updated_by` INT NULL AFTER `created_by`;

ALTER TABLE `sale_items` ADD `product_type` INT NULL AFTER `updated_by`;
ALTER TABLE `cart_items` ADD `product_type` INT NULL AFTER `updated_at`;

ALTER TABLE `products` ADD `is_sync` BOOLEAN NULL DEFAULT FALSE AFTER `pharmacy_id`;
