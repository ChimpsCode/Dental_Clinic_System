-- Print Activity log: who printed what, and when
CREATE TABLE IF NOT EXISTS print_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    user_role VARCHAR(50) NULL,
    document_type VARCHAR(50) NOT NULL,
    details VARCHAR(255) NULL,
    reference_table VARCHAR(50) NULL,
    reference_id INT NULL,
    page VARCHAR(100) NULL,
    ip_address VARCHAR(45) NULL,
    printed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_print_logs_printed_at (printed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
