-- =====================================================================
--  ParkMate - sample data
--  File : 02_seed.sql   (run AFTER 01_schema.sql)
--
--  Demo logins  ->  admin@parkmate.lk   / admin123
--                   nimal@parkmate.lk   / owner123
--                   fathima@parkmate.lk / owner123
--                   kaveen@gmail.com    / user123
--                   hasith@gmail.com    / user123
-- =====================================================================

USE parkmate;

-- ---------------------------------------------------------------------
-- Users
-- ---------------------------------------------------------------------
INSERT INTO users (full_name, email, phone, password_hash, role) VALUES
('ParkMate Admin',      'admin@parkmate.lk',   '0112345678',
 '$2y$10$XvRJg6uEn4WqL.dwnZh.F.UnXnmDTKK6A3QEQr/M4Yo8fpRF4dXEW', 'admin'),
('Nimal Perera',        'nimal@parkmate.lk',   '0771234567',
 '$2y$10$9K3f5JTcp356jPuMAQ/cmugkJxH1gL/yzXmsvGAgKdhVT.VMPJMs6', 'owner'),
('Fathima Rizwan',      'fathima@parkmate.lk', '0759876543',
 '$2y$10$9K3f5JTcp356jPuMAQ/cmugkJxH1gL/yzXmsvGAgKdhVT.VMPJMs6', 'owner'),
('Kaveen Jayaweera',    'kaveen@gmail.com',    '0712223334',
 '$2y$10$iPDrYigP30QapZm9K57xNuQ88f25YilmstGfxImSi98jkFeH0QzgW', 'customer'),
('Hasith Sajan',        'hasith@gmail.com',    '0763334445',
 '$2y$10$iPDrYigP30QapZm9K57xNuQ88f25YilmstGfxImSi98jkFeH0QzgW', 'customer'),
('Umasha Senarathna',   'umasha@gmail.com',    '0704445556',
 '$2y$10$iPDrYigP30QapZm9K57xNuQ88f25YilmstGfxImSi98jkFeH0QzgW', 'customer');

-- ---------------------------------------------------------------------
-- Cities
-- ---------------------------------------------------------------------
INSERT INTO cities (city_name, district) VALUES
('Colombo 03',  'Colombo'),
('Colombo 07',  'Colombo'),
('Dehiwala',    'Colombo'),
('Negombo',     'Gampaha'),
('Kandy',       'Kandy'),
('Galle',       'Galle');

-- ---------------------------------------------------------------------
-- Slot types
-- ---------------------------------------------------------------------
INSERT INTO slot_types (type_name, description) VALUES
('Standard car',  'Fits a saloon, hatchback or small SUV'),
('Large vehicle', 'Extra-wide bay for vans and full-size SUVs'),
('Motorcycle',    'Narrow bay for two-wheelers'),
('Accessible',    'Wide bay beside the lift, for blue badge holders');

-- ---------------------------------------------------------------------
-- Subscription plans (operator revenue model)
-- ---------------------------------------------------------------------
INSERT INTO subscription_plans (plan_name, monthly_fee, commission_rate, max_lots) VALUES
('Starter',    2500.00,  8.00,  1),
('Business',   7500.00,  5.00,  5),
('Enterprise', 20000.00, 3.00, 50);

INSERT INTO owner_subscriptions (owner_id, plan_id, started_on, expires_on, status) VALUES
(2, 2, '2026-01-01', '2027-01-01', 'active'),
(3, 1, '2026-03-01', '2027-03-01', 'active');

-- ---------------------------------------------------------------------
-- Parking lots
-- ---------------------------------------------------------------------
INSERT INTO parking_lots (owner_id, city_id, lot_name, address, description, opening_time, closing_time) VALUES
(2, 1, 'Liberty Plaza Car Park',
    'Liberty Plaza, R A De Mel Mawatha, Colombo 03',
    'Covered multi-storey park with lift access to the mall. Security on duty all day.',
    '06:00:00', '23:00:00'),
(2, 2, 'Independence Arcade Parking',
    'Independence Avenue, Colombo 07',
    'Open-air bays under shade trees, two minutes from the arcade entrance.',
    '07:00:00', '22:00:00'),
(2, 5, 'Kandy City Centre Deck',
    'Dalada Veediya, Kandy',
    'Basement deck below the shopping complex. Low clearance of 2.1 m.',
    '08:00:00', '21:00:00'),
(3, 3, 'Dehiwala Station Parking',
    'Galle Road, Dehiwala',
    'Long-stay bays for rail commuters. Bring your ticket for the daily rate.',
    '05:00:00', '23:30:00'),
(3, 4, 'Negombo Beach Park',
    'Lewis Place, Negombo',
    'Sandy surface lot a short walk from the beach strip.',
    '06:00:00', '22:00:00');

-- ---------------------------------------------------------------------
-- Parking slots
-- ---------------------------------------------------------------------
-- Lot 1: Liberty Plaza (12 bays over two levels)
INSERT INTO parking_slots (lot_id, type_id, slot_code, floor_level, hourly_rate) VALUES
(1, 1, 'A-01', 'Level 1', 150.00),
(1, 1, 'A-02', 'Level 1', 150.00),
(1, 1, 'A-03', 'Level 1', 150.00),
(1, 1, 'A-04', 'Level 1', 150.00),
(1, 4, 'A-05', 'Level 1', 120.00),
(1, 3, 'A-06', 'Level 1',  60.00),
(1, 1, 'B-01', 'Level 2', 130.00),
(1, 1, 'B-02', 'Level 2', 130.00),
(1, 2, 'B-03', 'Level 2', 200.00),
(1, 2, 'B-04', 'Level 2', 200.00),
(1, 1, 'B-05', 'Level 2', 130.00),
(1, 3, 'B-06', 'Level 2',  60.00);

-- Lot 2: Independence Arcade (8 bays)
INSERT INTO parking_slots (lot_id, type_id, slot_code, floor_level, hourly_rate) VALUES
(2, 1, 'P-01', 'Ground', 120.00),
(2, 1, 'P-02', 'Ground', 120.00),
(2, 1, 'P-03', 'Ground', 120.00),
(2, 1, 'P-04', 'Ground', 120.00),
(2, 2, 'P-05', 'Ground', 180.00),
(2, 4, 'P-06', 'Ground', 100.00),
(2, 3, 'P-07', 'Ground',  50.00),
(2, 3, 'P-08', 'Ground',  50.00);

-- Lot 3: Kandy City Centre (8 bays)
INSERT INTO parking_slots (lot_id, type_id, slot_code, floor_level, hourly_rate) VALUES
(3, 1, 'K-01', 'Basement 1', 100.00),
(3, 1, 'K-02', 'Basement 1', 100.00),
(3, 1, 'K-03', 'Basement 1', 100.00),
(3, 4, 'K-04', 'Basement 1',  80.00),
(3, 1, 'K-05', 'Basement 2',  90.00),
(3, 1, 'K-06', 'Basement 2',  90.00),
(3, 3, 'K-07', 'Basement 2',  45.00),
(3, 3, 'K-08', 'Basement 2',  45.00);

-- Lot 4: Dehiwala Station (10 bays, one under maintenance)
INSERT INTO parking_slots (lot_id, type_id, slot_code, floor_level, hourly_rate, status) VALUES
(4, 1, 'D-01', 'Ground',  80.00, 'available'),
(4, 1, 'D-02', 'Ground',  80.00, 'available'),
(4, 1, 'D-03', 'Ground',  80.00, 'available'),
(4, 1, 'D-04', 'Ground',  80.00, 'maintenance'),
(4, 1, 'D-05', 'Ground',  80.00, 'available'),
(4, 2, 'D-06', 'Ground', 140.00, 'available'),
(4, 3, 'D-07', 'Ground',  40.00, 'available'),
(4, 3, 'D-08', 'Ground',  40.00, 'available'),
(4, 3, 'D-09', 'Ground',  40.00, 'available'),
(4, 4, 'D-10', 'Ground',  70.00, 'available');

-- Lot 5: Negombo Beach (6 bays)
INSERT INTO parking_slots (lot_id, type_id, slot_code, floor_level, hourly_rate) VALUES
(5, 1, 'N-01', 'Ground', 90.00),
(5, 1, 'N-02', 'Ground', 90.00),
(5, 1, 'N-03', 'Ground', 90.00),
(5, 2, 'N-04', 'Ground', 150.00),
(5, 3, 'N-05', 'Ground', 45.00),
(5, 3, 'N-06', 'Ground', 45.00);

-- ---------------------------------------------------------------------
-- Vehicles
-- ---------------------------------------------------------------------
INSERT INTO vehicles (user_id, plate_number, vehicle_type, make_model) VALUES
(4, 'CAB-4521', 'car',        'Toyota Aqua'),
(4, 'CBH-7788', 'suv',        'Nissan X-Trail'),
(5, 'KY-3092',  'motorcycle', 'Honda CB125'),
(5, 'CAR-1150', 'car',        'Suzuki Wagon R'),
(6, 'CBA-9087', 'van',        'Toyota KDH');

-- ---------------------------------------------------------------------
-- Sample bookings
-- Dates are relative to today so the demo data never goes stale.
-- ---------------------------------------------------------------------
INSERT INTO reservations
    (user_id, slot_id, vehicle_id, start_time, end_time, billed_hours, total_amount, status)
VALUES
-- Kaveen, confirmed and starting in two hours
(4, 1, 1,
 DATE_ADD(NOW(), INTERVAL 2 HOUR),
 DATE_ADD(NOW(), INTERVAL 5 HOUR), 3, 450.00, 'confirmed'),
-- Hasith, motorcycle bay tomorrow
(5, 6, 3,
 DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL 9 HOUR),
 DATE_ADD(DATE_ADD(CURDATE(), INTERVAL 1 DAY), INTERVAL 13 HOUR), 4, 240.00, 'confirmed'),
-- Umasha, van bay, still waiting on payment
(6, 9, 5,
 DATE_ADD(NOW(), INTERVAL 26 HOUR),
 DATE_ADD(NOW(), INTERVAL 28 HOUR), 2, 400.00, 'pending'),
-- Kaveen, finished last week
(4, 13, 2,
 DATE_SUB(NOW(), INTERVAL 7 DAY),
 DATE_SUB(NOW(), INTERVAL 163 HOUR), 5, 600.00, 'completed'),
-- Hasith, cancelled
(5, 21, 4,
 DATE_SUB(NOW(), INTERVAL 3 DAY),
 DATE_SUB(NOW(), INTERVAL 70 HOUR), 2, 200.00, 'cancelled');

-- Matching payments. The AFTER UPDATE trigger on payments is what would
-- normally flip a reservation to 'confirmed', so these rows are inserted
-- in their final state to match the reservation statuses above.
INSERT INTO payments (reservation_id, amount, method, status, txn_reference, paid_at) VALUES
(1, 450.00, 'card',   'paid',     'PM-8842019', DATE_SUB(NOW(), INTERVAL 1 HOUR)),
(2, 240.00, 'wallet', 'paid',     'PM-8842104', DATE_SUB(NOW(), INTERVAL 3 HOUR)),
(3, 400.00, 'card',   'pending',  NULL,         NULL),
(4, 600.00, 'card',   'paid',     'PM-8830517', DATE_SUB(NOW(), INTERVAL 7 DAY)),
(5, 200.00, 'card',   'refunded', 'PM-8836620', DATE_SUB(NOW(), INTERVAL 4 DAY));

-- ---------------------------------------------------------------------
-- Reviews
-- ---------------------------------------------------------------------
INSERT INTO reviews (lot_id, user_id, rating, comment) VALUES
(1, 4, 5, 'Bay was exactly where the booking said. Lift right beside it.'),
(1, 5, 4, 'Good park, though the ramp to level 2 is tight for a bigger car.'),
(2, 4, 4, 'Shaded and quiet. Wish it opened earlier on weekends.'),
(4, 5, 3, 'Does the job for the morning train, but the surface floods in rain.'),
(3, 6, 5, 'Booked from the car five minutes out and it was still there.');
