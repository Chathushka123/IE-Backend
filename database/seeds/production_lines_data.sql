-- =====================================================
-- Production Lines Seed Data — Sewing Lines
-- Run: mysql -u app_user -p ie_module < database/seeds/production_lines_data.sql
-- =====================================================
-- Assumes:
--   line_categories.id = 3  → Sewing Lines
--   departments.id      = 3  → Sewing

SET FOREIGN_KEY_CHECKS = 0;

-- Update existing generic sewing lines (IDs 2-11) with real names/targets
UPDATE production_lines SET description = 'Bra Assembly Line A',          code = 'BRA-A', working_minutes_per_day = 480, target_efficiency_pct = 75.00 WHERE id = 2;
UPDATE production_lines SET description = 'Bra Assembly Line B',          code = 'BRA-B', working_minutes_per_day = 480, target_efficiency_pct = 78.00 WHERE id = 3;
UPDATE production_lines SET description = 'Bra Assembly Line C',          code = 'BRA-C', working_minutes_per_day = 480, target_efficiency_pct = 72.00 WHERE id = 4;
UPDATE production_lines SET description = 'Thong and Brief Line A',       code = 'THB-A', working_minutes_per_day = 480, target_efficiency_pct = 80.00 WHERE id = 5;
UPDATE production_lines SET description = 'Thong and Brief Line B',       code = 'THB-B', working_minutes_per_day = 480, target_efficiency_pct = 77.00 WHERE id = 6;
UPDATE production_lines SET description = 'Boyshort and Hipster Line A',  code = 'BYH-A', working_minutes_per_day = 480, target_efficiency_pct = 82.00 WHERE id = 7;
UPDATE production_lines SET description = 'Sports Bra Line A',            code = 'SPB-A', working_minutes_per_day = 480, target_efficiency_pct = 76.00 WHERE id = 8;
UPDATE production_lines SET description = 'Sports Bra and Bralette Line', code = 'SPB-B', working_minutes_per_day = 480, target_efficiency_pct = 74.00 WHERE id = 9;
UPDATE production_lines SET description = 'Legging and Active Bottoms A', code = 'LGA-A', working_minutes_per_day = 480, target_efficiency_pct = 83.00 WHERE id = 10;
UPDATE production_lines SET description = 'Legging and Active Bottoms B', code = 'LGA-B', working_minutes_per_day = 480, target_efficiency_pct = 80.00 WHERE id = 11;

-- New sewing lines
INSERT INTO production_lines (description, code, category_id, department_id, working_minutes_per_day, target_efficiency_pct, is_active) VALUES
('T-Shirt Knit Line A',       'TSH-A', 3, 3, 480, 85.00, 1),
('T-Shirt Knit Line B',       'TSH-B', 3, 3, 480, 82.00, 1),
('Tank and Cami Line',        'TCM-A', 3, 3, 480, 80.00, 1),
('Shorts and Jogger Line A',  'SJG-A', 3, 3, 480, 78.00, 1),
('Shorts and Jogger Line B',  'SJG-B', 3, 3, 480, 75.00, 1),
('Swimwear Line A',           'SWM-A', 3, 3, 480, 73.00, 1),
('Swimwear Line B',           'SWM-B', 3, 3, 480, 70.00, 1),
('Bikini and Underwear Line', 'BKU-A', 3, 3, 480, 81.00, 1),
('Boxer and Boyshort Line',   'BBX-A', 3, 3, 480, 83.00, 1),
('Dress and Romper Line',     'DRS-A', 3, 3, 480, 72.00, 1),
('Hoodie and Jacket Line',    'HOD-A', 3, 3, 480, 68.00, 1),
('Multi-Style Sewing Line',   'MLT-A', 3, 3, 480, 70.00, 1);

SET FOREIGN_KEY_CHECKS = 1;
