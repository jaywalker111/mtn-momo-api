CREATE DATABASE IF NOT EXISTS mtn_momo
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE mtn_momo;

CREATE TABLE IF NOT EXISTS transactions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    external_id VARCHAR(64) NOT NULL,
    momo_reference_id VARCHAR(64) NULL,
    payer_msisdn VARCHAR(32) NOT NULL,
    amount DECIMAL(18,4) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'GHS',
    status ENUM('PENDING','SUCCESSFUL','FAILED','UNKNOWN') NOT NULL DEFAULT 'PENDING',
    financial_transaction_id VARCHAR(128) NULL,
    reason VARCHAR(255) NULL,
    request_payload JSON NULL,
    response_payload JSON NULL,
    callback_payload JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_external_id (external_id),
    KEY idx_status (status),
    KEY idx_reference (momo_reference_id),
    KEY idx_created (created_at)
) ENGINE=InnoDB;
