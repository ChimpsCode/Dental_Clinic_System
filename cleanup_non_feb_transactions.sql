-- Remove non-February transactions for the current year
-- Preview what will be removed
SELECT b.id AS billing_id, b.billing_date, b.paid_amount, b.payment_status,
       p.id AS payment_id, p.payment_date, p.amount
FROM billing b
LEFT JOIN payments p ON p.billing_id = b.id
WHERE YEAR(b.billing_date) = YEAR(CURDATE())
  AND MONTH(b.billing_date) <> 2;

-- If the preview looks correct, run the cleanup inside a transaction
START TRANSACTION;

-- Delete payments linked to billing rows outside February (current year)
DELETE p
FROM payments p
JOIN billing b ON p.billing_id = b.id
WHERE YEAR(b.billing_date) = YEAR(CURDATE())
  AND MONTH(b.billing_date) <> 2;

-- Delete the billing rows themselves
DELETE FROM billing
WHERE YEAR(billing_date) = YEAR(CURDATE())
  AND MONTH(billing_date) <> 2;

COMMIT;

-- Optional: verify nothing remains outside February
SELECT COUNT(*) AS remaining_non_feb_billing
FROM billing
WHERE YEAR(billing_date) = YEAR(CURDATE())
  AND MONTH(billing_date) <> 2;
