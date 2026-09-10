-- =====================================================================
--  ParkMate - Smart Parking Reservation System
--  File   : 01_schema.sql
--  Course : ITC 2373 Database Design and Development
--  Group  : 13
--  Engine : MySQL 8.0 / MariaDB 10.4+ (XAMPP)
--  Run this file FIRST, then 02_seed.sql
-- =====================================================================

DROP DATABASE IF EXISTS parkmate;
CREATE DATABASE parkmate
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE parkmate;

-- ---------------------------------------------------------------------
-- 1. users
--    One table for all three roles. Role decides which dashboard the
--    person lands on after login.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    user_id       INT AUTO_INCREMENT PRIMARY KEY,
    full_name     VARCHAR(100)  NOT NULL,
    email         VARCHAR(120)  NOT NULL,
    phone         VARCHAR(20)   NOT NULL,
    password_hash VARCHAR(255)  NOT NULL,
    role          ENUM('customer','owner','admin') NOT NULL DEFAULT 'customer',
    status        ENUM('active','suspended')       NOT NULL DEFAULT 'active',
    created_at    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT chk_users_email CHECK (email LIKE '%_@_%._%')
) ENGINE=InnoDB;--MySQL engine that supports foreign keys and transactions

-- ---------------------------------------------------------------------
-- 2. vehicles
--    A customer may register several vehicles and pick one per booking.
-- ---------------------------------------------------------------------
CREATE TABLE vehicles (
    vehicle_id    INT AUTO_INCREMENT PRIMARY KEY,
    user_id       INT          NOT NULL,
    plate_number  VARCHAR(15)  NOT NULL,
    vehicle_type  ENUM('car','suv','van','motorcycle') NOT NULL DEFAULT 'car',
    make_model    VARCHAR(60)  DEFAULT NULL,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_vehicle_plate UNIQUE (plate_number),
    CONSTRAINT fk_vehicle_user  FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE--if that user is deleted, delete their vehicles too
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 3. cities
--    Lookup table so the search filter is driven by data, not hardcoded.
-- ---------------------------------------------------------------------
CREATE TABLE cities (
    city_id   INT AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(60) NOT NULL,
    district  VARCHAR(60) NOT NULL,

    CONSTRAINT uq_city_name UNIQUE (city_name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 4. parking_lots
--    Owned by a user whose role is 'owner'.
-- ---------------------------------------------------------------------
CREATE TABLE parking_lots (
    lot_id       INT AUTO_INCREMENT PRIMARY KEY,
    owner_id     INT          NOT NULL,
    city_id      INT          NOT NULL,
    lot_name     VARCHAR(120) NOT NULL,
    address      VARCHAR(200) NOT NULL,
    description  TEXT         DEFAULT NULL,
    opening_time TIME         NOT NULL DEFAULT '06:00:00',
    closing_time TIME         NOT NULL DEFAULT '22:00:00',
    status       ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_lot_owner FOREIGN KEY (owner_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_lot_city  FOREIGN KEY (city_id)
        REFERENCES cities(city_id)
) ENGINE=InnoDB;

CREATE INDEX idx_lot_city   ON parking_lots(city_id);
CREATE INDEX idx_lot_owner  ON parking_lots(owner_id);

-- ---------------------------------------------------------------------
-- 5. slot_types
--    Car / motorcycle / accessible bays are priced differently.
-- ---------------------------------------------------------------------
CREATE TABLE slot_types (
    type_id     INT AUTO_INCREMENT PRIMARY KEY,
    type_name   VARCHAR(40) NOT NULL,
    description VARCHAR(150) DEFAULT NULL,

    CONSTRAINT uq_slot_type UNIQUE (type_name)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 6. parking_slots
--    An individual bay. slot_code is unique within its lot only.
-- ---------------------------------------------------------------------
CREATE TABLE parking_slots (
    slot_id     INT AUTO_INCREMENT PRIMARY KEY,
    lot_id      INT           NOT NULL,
    type_id     INT           NOT NULL,
    slot_code   VARCHAR(10)   NOT NULL,
    floor_level VARCHAR(20)   NOT NULL DEFAULT 'Ground',
    hourly_rate DECIMAL(8,2)  NOT NULL,
    status      ENUM('available','maintenance') NOT NULL DEFAULT 'available',

    CONSTRAINT uq_slot_per_lot UNIQUE (lot_id, slot_code),
    CONSTRAINT chk_slot_rate   CHECK (hourly_rate >= 0),
    CONSTRAINT fk_slot_lot  FOREIGN KEY (lot_id)
        REFERENCES parking_lots(lot_id) ON DELETE CASCADE,
    CONSTRAINT fk_slot_type FOREIGN KEY (type_id)
        REFERENCES slot_types(type_id)
) ENGINE=InnoDB;

CREATE INDEX idx_slot_lot ON parking_slots(lot_id);

-- ---------------------------------------------------------------------
-- 7. reservations
--    The core booking record. A booking blocks one slot for a window.
-- ---------------------------------------------------------------------
CREATE TABLE reservations (
    reservation_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id        INT          NOT NULL,
    slot_id        INT          NOT NULL,
    vehicle_id     INT          NOT NULL,
    start_time     DATETIME     NOT NULL,
    end_time       DATETIME     NOT NULL,
    billed_hours   INT          NOT NULL,
    total_amount   DECIMAL(10,2) NOT NULL,
    status         ENUM('pending','confirmed','active','completed','cancelled')
                   NOT NULL DEFAULT 'pending',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT chk_res_window CHECK (end_time > start_time),
    CONSTRAINT chk_res_amount CHECK (total_amount >= 0),
    CONSTRAINT fk_res_user    FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_res_slot    FOREIGN KEY (slot_id)
        REFERENCES parking_slots(slot_id) ON DELETE CASCADE,
    CONSTRAINT fk_res_vehicle FOREIGN KEY (vehicle_id)
        REFERENCES vehicles(vehicle_id)
) ENGINE=InnoDB;

-- Composite index: every availability check filters on these three.
CREATE INDEX idx_res_slot_window ON reservations(slot_id, start_time, end_time);
CREATE INDEX idx_res_user        ON reservations(user_id);
CREATE INDEX idx_res_status      ON reservations(status);

-- ---------------------------------------------------------------------
-- 8. payments
--    One payment row per reservation, created as 'pending' with it.
-- ---------------------------------------------------------------------
CREATE TABLE payments (
    payment_id     INT AUTO_INCREMENT PRIMARY KEY,
    reservation_id INT           NOT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    method         ENUM('card','wallet','cash') NOT NULL DEFAULT 'card',
    status         ENUM('pending','paid','failed','refunded') NOT NULL DEFAULT 'pending',
    txn_reference  VARCHAR(40)   DEFAULT NULL,
    paid_at        DATETIME      DEFAULT NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_payment_res UNIQUE (reservation_id),
    CONSTRAINT fk_pay_res FOREIGN KEY (reservation_id)
        REFERENCES reservations(reservation_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 9. reviews
--    A customer rates a lot once.
-- ---------------------------------------------------------------------
CREATE TABLE reviews (
    review_id  INT AUTO_INCREMENT PRIMARY KEY,
    lot_id     INT      NOT NULL,
    user_id    INT      NOT NULL,
    rating     TINYINT  NOT NULL,
    comment    VARCHAR(400) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT uq_review_once  UNIQUE (lot_id, user_id),
    CONSTRAINT chk_review_stars CHECK (rating BETWEEN 1 AND 5),
    CONSTRAINT fk_review_lot  FOREIGN KEY (lot_id)
        REFERENCES parking_lots(lot_id) ON DELETE CASCADE,
    CONSTRAINT fk_review_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- 10. notifications
--     Written by triggers; read on the customer dashboard.
-- ---------------------------------------------------------------------
CREATE TABLE notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT          NOT NULL,
    title           VARCHAR(120) NOT NULL,
    message         VARCHAR(400) NOT NULL,
    is_read         TINYINT(1)   NOT NULL DEFAULT 0,
    created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_notif_user FOREIGN KEY (user_id)
        REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE INDEX idx_notif_user ON notifications(user_id, is_read);

-- ---------------------------------------------------------------------
-- 11. subscription_plans + owner_subscriptions
--     Supports the revenue model described in the proposal: operators
--     pay a monthly fee, and ParkMate takes a commission per booking.
-- ---------------------------------------------------------------------
CREATE TABLE subscription_plans (
    plan_id         INT AUTO_INCREMENT PRIMARY KEY,
    plan_name       VARCHAR(40)   NOT NULL,
    monthly_fee     DECIMAL(10,2) NOT NULL,
    commission_rate DECIMAL(5,2)  NOT NULL,
    max_lots        INT           NOT NULL,

    CONSTRAINT uq_plan_name UNIQUE (plan_name),
    CONSTRAINT chk_plan_rate CHECK (commission_rate BETWEEN 0 AND 100)
) ENGINE=InnoDB;

CREATE TABLE owner_subscriptions (
    subscription_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id        INT  NOT NULL,
    plan_id         INT  NOT NULL,
    started_on      DATE NOT NULL,
    expires_on      DATE NOT NULL,
    status          ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',

    CONSTRAINT chk_sub_dates CHECK (expires_on > started_on),
    CONSTRAINT fk_sub_owner FOREIGN KEY (owner_id)
        REFERENCES users(user_id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_plan  FOREIGN KEY (plan_id)
        REFERENCES subscription_plans(plan_id)
) ENGINE=InnoDB;


-- =====================================================================
--  VIEWS
-- =====================================================================

-- Lot listing with live free-bay counts. "Free right now" means the bay
-- is not under maintenance and has no booking covering this instant.
CREATE OR REPLACE VIEW vw_lot_availability AS
SELECT
    pl.lot_id,
    pl.lot_name,
    pl.address,
    pl.opening_time,
    pl.closing_time,
    pl.status                AS lot_status,
    c.city_name,
    u.full_name              AS owner_name,
    COUNT(ps.slot_id)        AS total_slots,
    SUM(
        CASE WHEN ps.status = 'available'
                  AND NOT EXISTS (
                      SELECT 1 FROM reservations r
                      WHERE r.slot_id = ps.slot_id
                        AND r.status IN ('pending','confirmed','active')
                        AND NOW() >= r.start_time
                        AND NOW() <  r.end_time
                  )
             THEN 1 ELSE 0 END
    )                        AS free_slots,
    MIN(ps.hourly_rate)      AS min_rate,
    MAX(ps.hourly_rate)      AS max_rate
FROM parking_lots pl
JOIN cities c        ON c.city_id  = pl.city_id
JOIN users  u        ON u.user_id  = pl.owner_id
LEFT JOIN parking_slots ps ON ps.lot_id = pl.lot_id
GROUP BY pl.lot_id, pl.lot_name, pl.address, pl.opening_time,
         pl.closing_time, pl.status, c.city_name, u.full_name;

-- Flattened booking record used by every "my bookings" style screen.
CREATE OR REPLACE VIEW vw_reservation_details AS
SELECT
    r.reservation_id,
    r.user_id,
    cu.full_name    AS customer_name,
    cu.phone        AS customer_phone,
    v.plate_number,
    v.vehicle_type,
    ps.slot_id,
    ps.slot_code,
    ps.floor_level,
    st.type_name    AS slot_type,
    pl.lot_id,
    pl.lot_name,
    pl.owner_id,
    ci.city_name,
    r.start_time,
    r.end_time,
    r.billed_hours,
    r.total_amount,
    r.status        AS reservation_status,
    r.created_at,
    p.payment_id,
    p.status        AS payment_status,
    p.method        AS payment_method,
    p.txn_reference
FROM reservations r
JOIN users          cu ON cu.user_id  = r.user_id
JOIN vehicles       v  ON v.vehicle_id = r.vehicle_id
JOIN parking_slots  ps ON ps.slot_id  = r.slot_id
JOIN slot_types     st ON st.type_id  = ps.type_id
JOIN parking_lots   pl ON pl.lot_id   = ps.lot_id
JOIN cities         ci ON ci.city_id  = pl.city_id
LEFT JOIN payments  p  ON p.reservation_id = r.reservation_id;

-- Per-lot earnings, used on the owner dashboard.
CREATE OR REPLACE VIEW vw_owner_earnings AS
SELECT
    pl.owner_id,
    pl.lot_id,
    pl.lot_name,
    COUNT(r.reservation_id)                                   AS total_bookings,
    COALESCE(SUM(CASE WHEN p.status = 'paid'
                      THEN r.total_amount ELSE 0 END), 0)     AS gross_revenue,
    COALESCE(SUM(CASE WHEN r.status = 'cancelled'
                      THEN 1 ELSE 0 END), 0)                  AS cancelled_count
FROM parking_lots pl
LEFT JOIN parking_slots ps ON ps.lot_id = pl.lot_id
LEFT JOIN reservations  r  ON r.slot_id = ps.slot_id
LEFT JOIN payments      p  ON p.reservation_id = r.reservation_id
GROUP BY pl.owner_id, pl.lot_id, pl.lot_name;


-- =====================================================================
--  STORED FUNCTION - price a booking window
--  Part hours are rounded up, which is how car parks actually bill.
-- =====================================================================
DELIMITER $$

DROP FUNCTION IF EXISTS fn_booking_amount$$
CREATE FUNCTION fn_booking_amount(
    p_slot_id INT,
    p_start   DATETIME,
    p_end     DATETIME
) RETURNS DECIMAL(10,2)
DETERMINISTIC
READS SQL DATA
BEGIN
    DECLARE v_rate  DECIMAL(8,2) DEFAULT 0;
    DECLARE v_hours INT DEFAULT 0;

    SELECT hourly_rate INTO v_rate
    FROM parking_slots WHERE slot_id = p_slot_id;

    SET v_hours = CEIL(TIMESTAMPDIFF(MINUTE, p_start, p_end) / 60);
    IF v_hours < 1 THEN SET v_hours = 1; END IF;

    RETURN ROUND(v_rate * v_hours, 2);
END$$


-- =====================================================================
--  STORED PROCEDURE - check whether a bay is free for a window
--  Two windows clash when start_a < end_b AND end_a > start_b.
-- =====================================================================
DROP PROCEDURE IF EXISTS sp_check_availability$$
CREATE PROCEDURE sp_check_availability(
    IN  p_slot_id   INT,
    IN  p_start     DATETIME,
    IN  p_end       DATETIME,
    OUT p_is_free   TINYINT
)
BEGIN
    DECLARE v_clashes INT DEFAULT 0;
    DECLARE v_state   VARCHAR(20);

    SELECT status INTO v_state
    FROM parking_slots WHERE slot_id = p_slot_id;

    SELECT COUNT(*) INTO v_clashes
    FROM reservations
    WHERE slot_id = p_slot_id
      AND status IN ('pending','confirmed','active')
      AND p_start < end_time
      AND p_end   > start_time;

    SET p_is_free = IF(v_state = 'available' AND v_clashes = 0, 1, 0);
END$$


-- =====================================================================
--  STORED PROCEDURE - create a booking
--  Validates the window, re-checks availability inside a transaction so
--  two people clicking "Confirm" at once cannot take the same bay, then
--  writes the reservation and its pending payment together.
-- =====================================================================
DROP PROCEDURE IF EXISTS sp_create_reservation$$
CREATE PROCEDURE sp_create_reservation(
    IN  p_user_id    INT,
    IN  p_slot_id    INT,
    IN  p_vehicle_id INT,
    IN  p_start      DATETIME,
    IN  p_end        DATETIME,
    OUT p_reservation_id INT,
    OUT p_message    VARCHAR(150)
)
BEGIN
    DECLARE v_clashes INT DEFAULT 0;
    DECLARE v_state   VARCHAR(20) DEFAULT '';
    DECLARE v_hours   INT DEFAULT 0;
    DECLARE v_amount  DECIMAL(10,2) DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_reservation_id = 0;
        SET p_message = 'Could not save the booking. Please try again.';
    END;

    SET p_reservation_id = 0;

    IF p_end <= p_start THEN
        SET p_message = 'The end time must be after the start time.';
    ELSEIF p_start < NOW() THEN
        SET p_message = 'Pick a start time in the future.';
    ELSE
        START TRANSACTION;

        -- Lock the bay row so a second booking waits for this one.
        SELECT status INTO v_state
        FROM parking_slots
        WHERE slot_id = p_slot_id
        FOR UPDATE;

        SELECT COUNT(*) INTO v_clashes
        FROM reservations
        WHERE slot_id = p_slot_id
          AND status IN ('pending','confirmed','active')
          AND p_start < end_time
          AND p_end   > start_time;

        IF v_state IS NULL THEN
            ROLLBACK;
            SET p_message = 'That bay no longer exists.';
        ELSEIF v_state <> 'available' THEN
            ROLLBACK;
            SET p_message = 'That bay is closed for maintenance.';
        ELSEIF v_clashes > 0 THEN
            ROLLBACK;
            SET p_message = 'That bay was just taken for part of your window.';
        ELSE
            SET v_hours = CEIL(TIMESTAMPDIFF(MINUTE, p_start, p_end) / 60);
            IF v_hours < 1 THEN SET v_hours = 1; END IF;
            SET v_amount = fn_booking_amount(p_slot_id, p_start, p_end);

            INSERT INTO reservations
                (user_id, slot_id, vehicle_id, start_time, end_time,
                 billed_hours, total_amount, status)
            VALUES
                (p_user_id, p_slot_id, p_vehicle_id, p_start, p_end,
                 v_hours, v_amount, 'pending');

            SET p_reservation_id = LAST_INSERT_ID();

            INSERT INTO payments (reservation_id, amount, status)
            VALUES (p_reservation_id, v_amount, 'pending');

            COMMIT;
            SET p_message = 'Booking held. Pay within 15 minutes to confirm it.';
        END IF;
    END IF;
END$$


-- =====================================================================
--  TRIGGERS
-- =====================================================================

-- Tell the customer their booking is being held.
DROP TRIGGER IF EXISTS trg_reservation_after_insert$$
CREATE TRIGGER trg_reservation_after_insert
AFTER INSERT ON reservations
FOR EACH ROW
BEGIN
    INSERT INTO notifications (user_id, title, message)
    VALUES (
        NEW.user_id,
        'Booking held',
        CONCAT('Bay reserved from ',
               DATE_FORMAT(NEW.start_time, '%d %b %Y %h:%i %p'),
               '. Pay LKR ', FORMAT(NEW.total_amount, 2), ' to confirm it.')
    );
END$$

-- When the payment lands, confirm the booking and stamp the time.
DROP TRIGGER IF EXISTS trg_payment_after_update$$
CREATE TRIGGER trg_payment_after_update
AFTER UPDATE ON payments
FOR EACH ROW
BEGIN
    IF NEW.status = 'paid' AND OLD.status <> 'paid' THEN
        UPDATE reservations
        SET status = 'confirmed'
        WHERE reservation_id = NEW.reservation_id
          AND status = 'pending';

        INSERT INTO notifications (user_id, title, message)
        SELECT r.user_id,
               'Payment received',
               CONCAT('Booking #', r.reservation_id,
                      ' is confirmed. LKR ', FORMAT(NEW.amount, 2), ' paid.')
        FROM reservations r
        WHERE r.reservation_id = NEW.reservation_id;
    END IF;
END$$

-- A cancelled booking refunds its payment and notifies the customer.
DROP TRIGGER IF EXISTS trg_reservation_after_update$$
CREATE TRIGGER trg_reservation_after_update
AFTER UPDATE ON reservations
FOR EACH ROW
BEGIN
    IF NEW.status = 'cancelled' AND OLD.status <> 'cancelled' THEN
        UPDATE payments
        SET status = 'refunded'
        WHERE reservation_id = NEW.reservation_id
          AND status = 'paid';

        INSERT INTO notifications (user_id, title, message)
        VALUES (NEW.user_id, 'Booking cancelled',
                CONCAT('Booking #', NEW.reservation_id,
                       ' was cancelled. Any payment is being refunded.'));
    END IF;
END$$

DELIMITER ;
