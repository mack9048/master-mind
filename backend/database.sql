-- Master Mind – Meet the Expert
-- Import this file in phpMyAdmin (or: mysql -u USER -p DBNAME < database.sql)

CREATE TABLE IF NOT EXISTS registrations (
    id                   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reg_code             VARCHAR(20)  NOT NULL,
    event_slug           VARCHAR(80)  NOT NULL,
    name                 VARCHAR(120) NOT NULL,
    email                VARCHAR(160) NOT NULL,
    phone                VARCHAR(20)  NOT NULL,
    city                 VARCHAR(80)  DEFAULT NULL,
    profession           VARCHAR(60)  DEFAULT NULL,
    organization         VARCHAR(160) DEFAULT NULL,
    expectation          TEXT         DEFAULT NULL,
    amount               DECIMAL(10,2) NOT NULL DEFAULT 0,
    payment_mode         VARCHAR(20)  NOT NULL DEFAULT 'upi',
    payment_status       ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending',
    payment_ref          VARCHAR(100) DEFAULT NULL,   -- UTR / transaction id entered by the student
    paid_at              DATETIME     DEFAULT NULL,
    razorpay_order_id    VARCHAR(60)  DEFAULT NULL,
    razorpay_payment_id  VARCHAR(60)  DEFAULT NULL,
    attended             TINYINT(1)   NOT NULL DEFAULT 0,
    checked_in_at        DATETIME     DEFAULT NULL,
    admin_note           TEXT         DEFAULT NULL,
    ticket_sent_at       DATETIME     DEFAULT NULL,
    ip_address           VARCHAR(45)  DEFAULT NULL,
    user_agent           VARCHAR(255) DEFAULT NULL,
    created_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_reg_code (reg_code),
    KEY idx_event_phone (event_slug, phone),
    KEY idx_event_status (event_slug, payment_status),
    KEY idx_rzp_order (razorpay_order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Already created the table earlier? The admin dashboard adds the two new
-- columns (paid_at, admin_note) automatically on first login.
