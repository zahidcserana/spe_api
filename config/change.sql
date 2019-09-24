ALTER TABLE `sale_items` ADD `return_status` ENUM('RETURN','CHANGE','OK') NULL DEFAULT 'OK' AFTER `server_item_id`;
ALTER TABLE `sale_items` ADD `change_log` TEXT NULL AFTER `return_status`;
ALTER TABLE `sale_items` ADD `updated_by` INT NULL AFTER `change_log`;
