-- Normalize Database: Create addresses and insurance_providers tables, update patients table

-- Step 1: Create addresses table
CREATE TABLE IF NOT EXISTS `addresses` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `city` VARCHAR(100),
  `province` VARCHAR(100),
  `zip_code` VARCHAR(20),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Create insurance_providers table
CREATE TABLE IF NOT EXISTS `insurance_providers` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `provider_name` VARCHAR(100),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Add foreign key columns to patients table (temporarily nullable)
ALTER TABLE `patients` 
  ADD COLUMN `address_id` INT NULL AFTER `email`,
  ADD COLUMN `insurance_provider_id` INT NULL AFTER `insurance_effective_date`,
  ADD COLUMN `street_address` TEXT NULL AFTER `address_id`;

-- Step 4: Add foreign key constraints
ALTER TABLE `patients` 
  ADD CONSTRAINT `fk_patients_address` FOREIGN KEY (`address_id`) REFERENCES `addresses`(`id`),
  ADD CONSTRAINT `fk_patients_insurance_provider` FOREIGN KEY (`insurance_provider_id`) REFERENCES `insurance_providers`(`id`);

-- Step 5: Migrate existing address data to addresses table
INSERT INTO `addresses` (`city`, `province`, `zip_code`)
SELECT DISTINCT `city`, `province`, `zip_code`
FROM `patients`
WHERE `city` IS NOT NULL OR `province` IS NOT NULL OR `zip_code` IS NOT NULL;

-- Step 6: Update patients with address_id (match based on city, province, zip_code)
UPDATE `patients` p
INNER JOIN `addresses` a ON COALESCE(p.city, '') = COALESCE(a.city, '')
  AND COALESCE(p.province, '') = COALESCE(a.province, '')
  AND COALESCE(p.zip_code, '') = COALESCE(a.zip_code, '')
SET p.address_id = a.id;

-- Step 7: Migrate existing insurance data to insurance_providers table
INSERT INTO `insurance_providers` (`provider_name`)
SELECT DISTINCT `dental_insurance`
FROM `patients`
WHERE `dental_insurance` IS NOT NULL AND `dental_insurance` != '';

-- Step 8: Update patients with insurance_provider_id
UPDATE `patients` p
INNER JOIN `insurance_providers` ip ON p.dental_insurance = ip.provider_name
SET p.insurance_provider_id = ip.id;

-- Step 9: Drop old columns from patients (after data migration)
ALTER TABLE `patients` 
  DROP COLUMN `address`,
  DROP COLUMN `city`,
  DROP COLUMN `province`,
  DROP COLUMN `zip_code`,
  DROP COLUMN `dental_insurance`,
  DROP COLUMN `insurance_effective_date`;
