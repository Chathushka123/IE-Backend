-- =====================================================
-- IE Module: Real-World Seed Data
-- Apparel Manufacturing - Lingerie / Activewear Factory
-- Run: mysql -u app_user -p ie_module < database/seeds/ie_module_data.sql
-- =====================================================

SET FOREIGN_KEY_CHECKS = 0;

-- =====================================================
-- 1. MACHINE CATEGORIES
-- =====================================================
INSERT INTO machine_categories (id, description, code, is_active) VALUES
(1, 'Sewing',            'SEW', 1),
(2, 'Cutting',           'CUT', 1),
(3, 'Pressing & Fusing', 'PRS', 1),
(4, 'Finishing',         'FIN', 1),
(5, 'Embroidery',        'EMB', 1),
(6, 'Inspection',        'INS', 1),
(7, 'Linking',           'LNK', 1);

-- =====================================================
-- 2. MACHINE TYPES
-- =====================================================
INSERT INTO machine_types (id, description, code, machine_category_id, is_active) VALUES
-- Sewing (cat 1)
(1,  'Single Needle Lockstitch', 'SNLS', 1, 1),
(2,  '5-Thread Overlock',        'OVL5', 1, 1),
(3,  '3-Thread Overlock',        'OVL3', 1, 1),
(4,  'Flatlock Coverstitch',     'FLK',  1, 1),
(5,  'Chain Stitch',             'CHS',  1, 1),
(6,  'Blind Stitch',             'BLS',  1, 1),
(7,  'Buttonhole Machine',       'BTH',  1, 1),
(8,  'Button Attach Machine',    'BTA',  1, 1),
(9,  'Bartack Machine',          'BTK',  1, 1),
(10, 'Kansai Special',           'KNS',  1, 1),
(11, 'Feed-Off-The-Arm',         'FOA',  1, 1),
(12, 'Zigzag Machine',           'ZZG',  1, 1),
-- Cutting (cat 2)
(13, 'Straight Knife Cutter',    'SKC',  2, 1),
(14, 'Band Knife Machine',       'BNK',  2, 1),
(15, 'Rotary Cutter',            'RTC',  2, 1),
-- Pressing & Fusing (cat 3)
(16, 'Steam Iron',               'STI',  3, 1),
(17, 'Buck Press',               'BKP',  3, 1),
(18, 'Tunnel Finisher',          'TNF',  3, 1),
(19, 'Fusing Machine',           'FSM',  3, 1),
-- Finishing (cat 4)
(20, 'Thread Trimmer',           'TTR',  4, 1),
(21, 'Folding Machine',          'FLM',  4, 1),
(22, 'Manual Operation',         'MNL',  4, 1),
(23, 'Label Attach Machine',     'LAM',  4, 1),
-- Embroidery (cat 5)
(24, 'Multi-Head Embroidery',    'MHE',  5, 1),
(25, 'Single Head Embroidery',   'SHE',  5, 1),
-- Inspection (cat 6)
(26, 'Needle Detector',          'NDD',  6, 1),
(27, 'Measurement Table',        'MST',  6, 1),
-- Linking (cat 7)
(28, 'Linking Machine',          'LNKM', 7, 1);

-- =====================================================
-- 3. OPERATION CATEGORIES
-- =====================================================
INSERT INTO operation_categories (id, description, code, is_active) VALUES
(1, 'Cutting',              'CAT-CUT', 1),
(2, 'Preparation & Fusing', 'CAT-PRE', 1),
(3, 'Assembly',             'CAT-ASM', 1),
(4, 'Seaming',              'CAT-SEA', 1),
(5, 'Hemming & Finishing',  'CAT-HEM', 1),
(6, 'Attachment',           'CAT-ATT', 1),
(7, 'Pressing',             'CAT-PRS', 1),
(8, 'Quality & Inspection', 'CAT-QCI', 1);

-- =====================================================
-- 4. SKILLS
-- =====================================================
INSERT INTO skills (id, description, code, is_active) VALUES
(1,  'Single Needle Lockstitch',   'SK-SNLS', 1),
(2,  'Overlock Operation',         'SK-OVL',  1),
(3,  'Flatlock Coverstitch',       'SK-FLK',  1),
(4,  'Blind Stitch Operation',     'SK-BLS',  1),
(5,  'Bartack Operation',          'SK-BTK',  1),
(6,  'Chain Stitch Operation',     'SK-CHS',  1),
(7,  'Kansai Special Operation',   'SK-KNS',  1),
(8,  'Buttonhole Making',          'SK-BTH',  1),
(9,  'Button Attachment',          'SK-BTA',  1),
(10, 'Feed-Off-The-Arm Operation', 'SK-FOA',  1),
(11, 'Fabric Cutting',             'SK-CUT',  1),
(12, 'Pattern Matching',           'SK-PTN',  1),
(13, 'Steam Pressing',             'SK-PRS',  1),
(14, 'Fusing Operation',           'SK-FUS',  1),
(15, 'Elastic Attachment',         'SK-ELS',  1),
(16, 'Lace Attachment',            'SK-LAC',  1),
(17, 'Label Attachment',           'SK-LBL',  1),
(18, 'Needle Detection',           'SK-NDD',  1),
(19, 'Quality Inspection',         'SK-QCI',  1),
(20, 'Embroidery Operation',       'SK-EMB',  1);

-- =====================================================
-- 5. OPERATION GRADES
-- =====================================================
INSERT INTO operation_grades (id, description, code, level, is_active) VALUES
(1, 'Trainee',  'GR-TRN', 1, 1),
(2, 'Junior',   'GR-JNR', 2, 1),
(3, 'Standard', 'GR-STD', 3, 1),
(4, 'Senior',   'GR-SNR', 4, 1),
(5, 'Expert',   'GR-EXP', 5, 1);

-- =====================================================
-- 6. OPERATIONS
-- =====================================================
INSERT INTO operations (id, description, code, operation_category_id, is_active) VALUES
-- Cutting (cat 1)
(1,  'Fabric Laying',             'CUT-001', 1, 1),
(2,  'Marker Spreading',          'CUT-002', 1, 1),
(3,  'Straight Knife Cutting',    'CUT-003', 1, 1),
(4,  'Band Knife Cutting',        'CUT-004', 1, 1),
(5,  'Notching',                  'CUT-005', 1, 1),
(6,  'Numbering and Bundling',    'CUT-006', 1, 1),
-- Preparation & Fusing (cat 2)
(7,  'Fusing Front Panel',        'PRE-001', 2, 1),
(8,  'Fusing Waistband',          'PRE-002', 2, 1),
(9,  'Interlining Application',   'PRE-003', 2, 1),
(10, 'Dart Marking',              'PRE-004', 2, 1),
(11, 'Label Preparation',         'PRE-005', 2, 1),
-- Assembly (cat 3)
(12, 'Join Front to Back Panel',  'ASM-001', 3, 1),
(13, 'Join Side Seams',           'ASM-002', 3, 1),
(14, 'Join Shoulder Seams',       'ASM-003', 3, 1),
(15, 'Cup Assembly',              'ASM-004', 3, 1),
(16, 'Gusset Attach',             'ASM-005', 3, 1),
(17, 'Liner Attach',              'ASM-006', 3, 1),
(18, 'Band Attach',               'ASM-007', 3, 1),
(19, 'Panel Join',                'ASM-008', 3, 1),
-- Seaming (cat 4)
(20, 'Inseam Sewing',             'SEA-001', 4, 1),
(21, 'Outseam Sewing',            'SEA-002', 4, 1),
(22, 'Crotch Seam',               'SEA-003', 4, 1),
(23, 'Back Seam',                 'SEA-004', 4, 1),
(24, 'Front Rise Seam',           'SEA-005', 4, 1),
(25, 'Armhole Seam',              'SEA-006', 4, 1),
(26, 'Neckline Seam',             'SEA-007', 4, 1),
(27, 'Underwire Casing Seam',     'SEA-008', 4, 1),
(28, 'Bridge Seam',               'SEA-009', 4, 1),
(29, 'Wing Seam',                 'SEA-010', 4, 1),
-- Hemming & Finishing (cat 5)
(30, 'Leg Hem',                   'HEM-001', 5, 1),
(31, 'Bottom Hem',                'HEM-002', 5, 1),
(32, 'Waist Hem',                 'HEM-003', 5, 1),
(33, 'Sleeve Hem',                'HEM-004', 5, 1),
(34, 'Neck Hem',                  'HEM-005', 5, 1),
(35, 'Flatlock Coverstitch Hem',  'HEM-006', 5, 1),
(36, 'Blind Hem',                 'HEM-007', 5, 1),
-- Attachment (cat 6)
(37, 'Elastic Attach Waist',      'ATT-001', 6, 1),
(38, 'Elastic Attach Leg',        'ATT-002', 6, 1),
(39, 'Label Attach',              'ATT-003', 6, 1),
(40, 'Lace Trim Attach',          'ATT-004', 6, 1),
(41, 'Hook and Eye Attach',       'ATT-005', 6, 1),
(42, 'Underwire Insert',          'ATT-006', 6, 1),
(43, 'Strap Attach',              'ATT-007', 6, 1),
(44, 'Clasp Attach',              'ATT-008', 6, 1),
-- Pressing (cat 7)
(45, 'Final Pressing',            'PRS-001', 7, 1),
(46, 'Cup Pressing',              'PRS-002', 7, 1),
(47, 'Seam Pressing',             'PRS-003', 7, 1),
-- Quality & Inspection (cat 8)
(48, 'In-line Inspection',        'QCI-001', 8, 1),
(49, 'Final Inspection',          'QCI-002', 8, 1),
(50, 'Needle Detection',          'QCI-003', 8, 1),
(51, 'Measurement Check',         'QCI-004', 8, 1);

-- =====================================================
-- 7. OPERATION GRADINGS
-- Columns: id, operation_id, product_category_id, machine_type_id,
--          description, code, grade_id, sequence_no, smv, is_active
-- Unique: (operation_id, product_category_id, machine_type_id)
-- Unique: (product_category_id, sequence_no)
-- =====================================================
INSERT INTO operation_gradings
  (id, operation_id, product_category_id, machine_type_id, description, code, grade_id, sequence_no, smv, is_active)
VALUES
-- BRA (product_category_id = 2) — IDs 1-17
(1,  1,  2, 27, 'Bra - Fabric Laying',          'OG-BRA-001', 3,  1, 0.5000, 1),
(2,  3,  2, 13, 'Bra - Cutting',                'OG-BRA-002', 3,  2, 1.2000, 1),
(3,  7,  2, 19, 'Bra - Front Panel Fusing',     'OG-BRA-003', 3,  3, 0.8000, 1),
(4,  15, 2, 1,  'Bra - Cup Assembly',           'OG-BRA-004', 4,  4, 3.5000, 1),
(5,  27, 2, 1,  'Bra - Underwire Casing Seam',  'OG-BRA-005', 4,  5, 2.8000, 1),
(6,  28, 2, 1,  'Bra - Bridge Seam',            'OG-BRA-006', 3,  6, 1.5000, 1),
(7,  29, 2, 1,  'Bra - Wing Seam',              'OG-BRA-007', 3,  7, 2.2000, 1),
(8,  23, 2, 2,  'Bra - Back Seam',              'OG-BRA-008', 3,  8, 1.8000, 1),
(9,  43, 2, 1,  'Bra - Strap Attach',           'OG-BRA-009', 3,  9, 2.5000, 1),
(10, 44, 2, 1,  'Bra - Clasp Attach',           'OG-BRA-010', 4, 10, 3.0000, 1),
(11, 41, 2, 9,  'Bra - Hook and Eye Attach',    'OG-BRA-011', 3, 11, 1.5000, 1),
(12, 38, 2, 1,  'Bra - Elastic Leg Attach',     'OG-BRA-012', 3, 12, 1.8000, 1),
(13, 39, 2, 23, 'Bra - Label Attach',           'OG-BRA-013', 1, 13, 0.4000, 1),
(14, 42, 2, 22, 'Bra - Underwire Insert',       'OG-BRA-014', 2, 14, 1.8000, 1),
(15, 45, 2, 16, 'Bra - Final Pressing',         'OG-BRA-015', 2, 15, 1.2000, 1),
(16, 49, 2, 27, 'Bra - Final Inspection',       'OG-BRA-016', 3, 16, 1.5000, 1),
(17, 50, 2, 26, 'Bra - Needle Detection',       'OG-BRA-017', 1, 17, 0.3000, 1),

-- THONG (product_category_id = 8) — IDs 18-28
(18, 1,  8, 27, 'Thong - Fabric Laying',        'OG-THG-001', 3,  1, 0.4000, 1),
(19, 3,  8, 13, 'Thong - Cutting',              'OG-THG-002', 3,  2, 0.8000, 1),
(20, 24, 8, 1,  'Thong - Front Rise Seam',      'OG-THG-003', 3,  3, 1.2000, 1),
(21, 22, 8, 2,  'Thong - Crotch Seam',          'OG-THG-004', 3,  4, 1.5000, 1),
(22, 23, 8, 1,  'Thong - Back Seam',            'OG-THG-005', 3,  5, 1.0000, 1),
(23, 37, 8, 1,  'Thong - Waist Elastic Attach', 'OG-THG-006', 3,  6, 2.0000, 1),
(24, 38, 8, 1,  'Thong - Leg Elastic Attach',   'OG-THG-007', 3,  7, 2.5000, 1),
(25, 40, 8, 1,  'Thong - Lace Trim Attach',     'OG-THG-008', 4,  8, 3.0000, 1),
(26, 39, 8, 23, 'Thong - Label Attach',         'OG-THG-009', 1,  9, 0.3500, 1),
(27, 49, 8, 27, 'Thong - Final Inspection',     'OG-THG-010', 3, 10, 1.0000, 1),
(28, 50, 8, 26, 'Thong - Needle Detection',     'OG-THG-011', 1, 11, 0.2500, 1),

-- BRIEF (product_category_id = 10) — IDs 29-41
(29, 1,  10, 27, 'Brief - Fabric Laying',        'OG-BRF-001', 3,  1, 0.4500, 1),
(30, 3,  10, 13, 'Brief - Cutting',              'OG-BRF-002', 3,  2, 0.9000, 1),
(31, 24, 10, 1,  'Brief - Front Rise Seam',      'OG-BRF-003', 3,  3, 1.3000, 1),
(32, 22, 10, 2,  'Brief - Crotch Seam',          'OG-BRF-004', 3,  4, 1.6000, 1),
(33, 23, 10, 2,  'Brief - Back Seam',            'OG-BRF-005', 3,  5, 1.2000, 1),
(34, 13, 10, 3,  'Brief - Side Seam',            'OG-BRF-006', 3,  6, 1.5000, 1),
(35, 30, 10, 1,  'Brief - Leg Hem',              'OG-BRF-007', 3,  7, 2.2000, 1),
(36, 37, 10, 1,  'Brief - Waist Elastic Attach', 'OG-BRF-008', 3,  8, 2.0000, 1),
(37, 38, 10, 1,  'Brief - Leg Elastic Attach',   'OG-BRF-009', 3,  9, 2.8000, 1),
(38, 39, 10, 23, 'Brief - Label Attach',         'OG-BRF-010', 1, 10, 0.3500, 1),
(39, 45, 10, 16, 'Brief - Final Pressing',       'OG-BRF-011', 2, 11, 0.8000, 1),
(40, 49, 10, 27, 'Brief - Final Inspection',     'OG-BRF-012', 3, 12, 1.0000, 1),
(41, 50, 10, 26, 'Brief - Needle Detection',     'OG-BRF-013', 1, 13, 0.2500, 1),

-- LEGGING (product_category_id = 41) — IDs 42-53
(42, 1,  41, 27, 'Legging - Fabric Laying',         'OG-LEG-001', 3,  1, 0.6000, 1),
(43, 3,  41, 13, 'Legging - Cutting',               'OG-LEG-002', 3,  2, 1.1000, 1),
(44, 20, 41, 4,  'Legging - Inseam Flatlock',       'OG-LEG-003', 3,  3, 2.5000, 1),
(45, 21, 41, 4,  'Legging - Outseam Flatlock',      'OG-LEG-004', 3,  4, 2.5000, 1),
(46, 22, 41, 2,  'Legging - Crotch Seam',           'OG-LEG-005', 3,  5, 1.8000, 1),
(47, 32, 41, 1,  'Legging - Waist Hem',             'OG-LEG-006', 3,  6, 2.0000, 1),
(48, 37, 41, 1,  'Legging - Waist Elastic Attach',  'OG-LEG-007', 3,  7, 2.2000, 1),
(49, 30, 41, 4,  'Legging - Leg Hem Flatlock',      'OG-LEG-008', 3,  8, 2.8000, 1),
(50, 39, 41, 23, 'Legging - Label Attach',          'OG-LEG-009', 1,  9, 0.3500, 1),
(51, 45, 41, 16, 'Legging - Final Pressing',        'OG-LEG-010', 2, 10, 1.0000, 1),
(52, 49, 41, 27, 'Legging - Final Inspection',      'OG-LEG-011', 3, 11, 1.2000, 1),
(53, 50, 41, 26, 'Legging - Needle Detection',      'OG-LEG-012', 1, 12, 0.2500, 1),

-- T-SHIRT (product_category_id = 24) — IDs 54-65
(54, 1,  24, 27, 'T-Shirt - Fabric Laying',     'OG-TSH-001', 3,  1, 0.5500, 1),
(55, 3,  24, 13, 'T-Shirt - Cutting',           'OG-TSH-002', 3,  2, 1.0000, 1),
(56, 14, 24, 2,  'T-Shirt - Shoulder Seam',     'OG-TSH-003', 3,  3, 1.5000, 1),
(57, 25, 24, 2,  'T-Shirt - Armhole Seam',      'OG-TSH-004', 3,  4, 2.0000, 1),
(58, 13, 24, 2,  'T-Shirt - Side Seam',         'OG-TSH-005', 3,  5, 1.8000, 1),
(59, 34, 24, 1,  'T-Shirt - Neck Hem',          'OG-TSH-006', 3,  6, 2.2000, 1),
(60, 33, 24, 4,  'T-Shirt - Sleeve Hem',        'OG-TSH-007', 3,  7, 1.8000, 1),
(61, 31, 24, 4,  'T-Shirt - Bottom Hem',        'OG-TSH-008', 3,  8, 1.5000, 1),
(62, 39, 24, 23, 'T-Shirt - Label Attach',      'OG-TSH-009', 1,  9, 0.4000, 1),
(63, 45, 24, 16, 'T-Shirt - Final Pressing',    'OG-TSH-010', 2, 10, 1.0000, 1),
(64, 49, 24, 27, 'T-Shirt - Final Inspection',  'OG-TSH-011', 3, 11, 1.2000, 1),
(65, 50, 24, 26, 'T-Shirt - Needle Detection',  'OG-TSH-012', 1, 12, 0.2500, 1),

-- SHORT (product_category_id = 1) — IDs 66-77
(66, 1,  1, 27, 'Short - Fabric Laying',        'OG-SHT-001', 3,  1, 0.5000, 1),
(67, 3,  1, 13, 'Short - Cutting',              'OG-SHT-002', 3,  2, 0.9000, 1),
(68, 20, 1, 2,  'Short - Inseam Seam',          'OG-SHT-003', 3,  3, 1.8000, 1),
(69, 21, 1, 2,  'Short - Outseam Seam',         'OG-SHT-004', 3,  4, 1.8000, 1),
(70, 22, 1, 2,  'Short - Crotch Seam',          'OG-SHT-005', 3,  5, 1.5000, 1),
(71, 32, 1, 1,  'Short - Waist Hem',            'OG-SHT-006', 3,  6, 1.8000, 1),
(72, 37, 1, 1,  'Short - Waist Elastic Attach', 'OG-SHT-007', 3,  7, 2.0000, 1),
(73, 30, 1, 4,  'Short - Leg Hem Flatlock',     'OG-SHT-008', 3,  8, 2.5000, 1),
(74, 39, 1, 23, 'Short - Label Attach',         'OG-SHT-009', 1,  9, 0.3500, 1),
(75, 45, 1, 16, 'Short - Final Pressing',       'OG-SHT-010', 2, 10, 0.8000, 1),
(76, 49, 1, 27, 'Short - Final Inspection',     'OG-SHT-011', 3, 11, 1.0000, 1),
(77, 50, 1, 26, 'Short - Needle Detection',     'OG-SHT-012', 1, 12, 0.2500, 1),

-- SPORTS BRA (product_category_id = 5) — IDs 78-89
(78, 1,  5, 27, 'Sports Bra - Fabric Laying',         'OG-SPB-001', 3,  1, 0.5500, 1),
(79, 3,  5, 13, 'Sports Bra - Cutting',               'OG-SPB-002', 3,  2, 1.0000, 1),
(80, 12, 5, 4,  'Sports Bra - Panel Join Flatlock',   'OG-SPB-003', 3,  3, 2.5000, 1),
(81, 25, 5, 4,  'Sports Bra - Armhole Flatlock',      'OG-SPB-004', 3,  4, 2.2000, 1),
(82, 26, 5, 4,  'Sports Bra - Neckline Flatlock',     'OG-SPB-005', 3,  5, 1.8000, 1),
(83, 18, 5, 1,  'Sports Bra - Band Attach',           'OG-SPB-006', 3,  6, 2.5000, 1),
(84, 43, 5, 1,  'Sports Bra - Strap Attach',          'OG-SPB-007', 3,  7, 2.0000, 1),
(85, 31, 5, 4,  'Sports Bra - Bottom Hem Flatlock',   'OG-SPB-008', 3,  8, 1.5000, 1),
(86, 39, 5, 23, 'Sports Bra - Label Attach',          'OG-SPB-009', 1,  9, 0.4000, 1),
(87, 45, 5, 16, 'Sports Bra - Final Pressing',        'OG-SPB-010', 2, 10, 1.0000, 1),
(88, 49, 5, 27, 'Sports Bra - Final Inspection',      'OG-SPB-011', 3, 11, 1.2000, 1),
(89, 50, 5, 26, 'Sports Bra - Needle Detection',      'OG-SPB-012', 1, 12, 0.2500, 1),

-- HIPSTER (product_category_id = 9) — IDs 90-99
(90, 1,  9, 27, 'Hipster - Fabric Laying',        'OG-HIP-001', 3,  1, 0.4000, 1),
(91, 3,  9, 13, 'Hipster - Cutting',              'OG-HIP-002', 3,  2, 0.8500, 1),
(92, 24, 9, 1,  'Hipster - Front Rise Seam',      'OG-HIP-003', 3,  3, 1.2000, 1),
(93, 22, 9, 2,  'Hipster - Crotch Seam',          'OG-HIP-004', 3,  4, 1.5000, 1),
(94, 13, 9, 3,  'Hipster - Side Seam',            'OG-HIP-005', 3,  5, 1.4000, 1),
(95, 30, 9, 1,  'Hipster - Leg Hem',              'OG-HIP-006', 3,  6, 2.0000, 1),
(96, 37, 9, 1,  'Hipster - Waist Elastic Attach', 'OG-HIP-007', 3,  7, 1.8000, 1),
(97, 39, 9, 23, 'Hipster - Label Attach',         'OG-HIP-008', 1,  8, 0.3500, 1),
(98, 49, 9, 27, 'Hipster - Final Inspection',     'OG-HIP-009', 3,  9, 0.9000, 1),
(99, 50, 9, 26, 'Hipster - Needle Detection',     'OG-HIP-010', 1, 10, 0.2500, 1),

-- BOYSHORT (product_category_id = 11) — IDs 100-110
(100, 1,  11, 27, 'Boyshort - Fabric Laying',        'OG-BYS-001', 3,  1, 0.4500, 1),
(101, 3,  11, 13, 'Boyshort - Cutting',              'OG-BYS-002', 3,  2, 0.8500, 1),
(102, 20, 11, 2,  'Boyshort - Inseam Seam',          'OG-BYS-003', 3,  3, 1.5000, 1),
(103, 22, 11, 2,  'Boyshort - Crotch Seam',          'OG-BYS-004', 3,  4, 1.5000, 1),
(104, 21, 11, 2,  'Boyshort - Outseam Seam',         'OG-BYS-005', 3,  5, 1.5000, 1),
(105, 30, 11, 4,  'Boyshort - Leg Hem Flatlock',     'OG-BYS-006', 3,  6, 2.2000, 1),
(106, 37, 11, 1,  'Boyshort - Waist Elastic Attach', 'OG-BYS-007', 3,  7, 1.8000, 1),
(107, 39, 11, 23, 'Boyshort - Label Attach',         'OG-BYS-008', 1,  8, 0.3500, 1),
(108, 45, 11, 16, 'Boyshort - Final Pressing',       'OG-BYS-009', 2,  9, 0.8000, 1),
(109, 49, 11, 27, 'Boyshort - Final Inspection',     'OG-BYS-010', 3, 10, 0.9000, 1),
(110, 50, 11, 26, 'Boyshort - Needle Detection',     'OG-BYS-011', 1, 11, 0.2500, 1),

-- BRALETTE (product_category_id = 4) — IDs 111-122
(111, 1,  4, 27, 'Bralette - Fabric Laying',       'OG-BRL-001', 3,  1, 0.4500, 1),
(112, 3,  4, 13, 'Bralette - Cutting',             'OG-BRL-002', 3,  2, 0.9000, 1),
(113, 15, 4, 1,  'Bralette - Cup Assembly',        'OG-BRL-003', 3,  3, 2.5000, 1),
(114, 13, 4, 2,  'Bralette - Side Seam',           'OG-BRL-004', 3,  4, 1.5000, 1),
(115, 23, 4, 2,  'Bralette - Back Seam',           'OG-BRL-005', 3,  5, 1.2000, 1),
(116, 43, 4, 1,  'Bralette - Strap Attach',        'OG-BRL-006', 3,  6, 2.0000, 1),
(117, 38, 4, 1,  'Bralette - Elastic Leg Attach',  'OG-BRL-007', 3,  7, 1.8000, 1),
(118, 40, 4, 1,  'Bralette - Lace Trim Attach',    'OG-BRL-008', 4,  8, 2.8000, 1),
(119, 39, 4, 23, 'Bralette - Label Attach',        'OG-BRL-009', 1,  9, 0.4000, 1),
(120, 45, 4, 16, 'Bralette - Final Pressing',      'OG-BRL-010', 2, 10, 0.9000, 1),
(121, 49, 4, 27, 'Bralette - Final Inspection',    'OG-BRL-011', 3, 11, 1.1000, 1),
(122, 50, 4, 26, 'Bralette - Needle Detection',    'OG-BRL-012', 1, 12, 0.2500, 1),

-- TANK (product_category_id = 26) — IDs 123-133
(123, 1,  26, 27, 'Tank - Fabric Laying',       'OG-TNK-001', 3,  1, 0.5000, 1),
(124, 3,  26, 13, 'Tank - Cutting',             'OG-TNK-002', 3,  2, 0.9000, 1),
(125, 14, 26, 2,  'Tank - Shoulder Seam',       'OG-TNK-003', 3,  3, 1.4000, 1),
(126, 13, 26, 2,  'Tank - Side Seam',           'OG-TNK-004', 3,  4, 1.8000, 1),
(127, 25, 26, 1,  'Tank - Armhole Hem',         'OG-TNK-005', 3,  5, 2.0000, 1),
(128, 34, 26, 1,  'Tank - Neck Hem',            'OG-TNK-006', 3,  6, 2.0000, 1),
(129, 31, 26, 1,  'Tank - Bottom Hem',          'OG-TNK-007', 3,  7, 1.5000, 1),
(130, 39, 26, 23, 'Tank - Label Attach',        'OG-TNK-008', 1,  8, 0.3500, 1),
(131, 45, 26, 16, 'Tank - Final Pressing',      'OG-TNK-009', 2,  9, 0.8000, 1),
(132, 49, 26, 27, 'Tank - Final Inspection',    'OG-TNK-010', 3, 10, 1.0000, 1),
(133, 50, 26, 26, 'Tank - Needle Detection',    'OG-TNK-011', 1, 11, 0.2500, 1),

-- JOGGER (product_category_id = 42) — IDs 134-145
(134, 1,  42, 27, 'Jogger - Fabric Laying',        'OG-JOG-001', 3,  1, 0.6000, 1),
(135, 3,  42, 13, 'Jogger - Cutting',              'OG-JOG-002', 3,  2, 1.1000, 1),
(136, 20, 42, 2,  'Jogger - Inseam Seam',          'OG-JOG-003', 3,  3, 2.0000, 1),
(137, 21, 42, 2,  'Jogger - Outseam Seam',         'OG-JOG-004', 3,  4, 2.0000, 1),
(138, 22, 42, 2,  'Jogger - Crotch Seam',          'OG-JOG-005', 3,  5, 1.8000, 1),
(139, 32, 42, 1,  'Jogger - Waist Hem',            'OG-JOG-006', 3,  6, 2.0000, 1),
(140, 37, 42, 1,  'Jogger - Waist Elastic Attach', 'OG-JOG-007', 3,  7, 2.5000, 1),
(141, 30, 42, 1,  'Jogger - Leg Hem',              'OG-JOG-008', 3,  8, 2.5000, 1),
(142, 39, 42, 23, 'Jogger - Label Attach',         'OG-JOG-009', 1,  9, 0.3500, 1),
(143, 45, 42, 16, 'Jogger - Final Pressing',       'OG-JOG-010', 2, 10, 1.0000, 1),
(144, 49, 42, 27, 'Jogger - Final Inspection',     'OG-JOG-011', 3, 11, 1.2000, 1),
(145, 50, 42, 26, 'Jogger - Needle Detection',     'OG-JOG-012', 1, 12, 0.2500, 1),

-- SWIMWEAR (product_category_id = 52) — IDs 146-157
(146, 1,  52, 27, 'Swimwear - Fabric Laying',          'OG-SWM-001', 3,  1, 0.5500, 1),
(147, 3,  52, 13, 'Swimwear - Cutting',                'OG-SWM-002', 3,  2, 1.0000, 1),
(148, 7,  52, 19, 'Swimwear - Front Panel Fusing',     'OG-SWM-003', 3,  3, 0.7000, 1),
(149, 17, 52, 1,  'Swimwear - Liner Attach',           'OG-SWM-004', 3,  4, 2.5000, 1),
(150, 22, 52, 2,  'Swimwear - Crotch Seam',            'OG-SWM-005', 3,  5, 1.8000, 1),
(151, 23, 52, 2,  'Swimwear - Back Seam',              'OG-SWM-006', 3,  6, 1.5000, 1),
(152, 37, 52, 1,  'Swimwear - Waist Elastic Attach',   'OG-SWM-007', 3,  7, 2.0000, 1),
(153, 38, 52, 1,  'Swimwear - Leg Elastic Attach',     'OG-SWM-008', 3,  8, 2.5000, 1),
(154, 39, 52, 23, 'Swimwear - Label Attach',           'OG-SWM-009', 1,  9, 0.4000, 1),
(155, 45, 52, 16, 'Swimwear - Final Pressing',         'OG-SWM-010', 2, 10, 1.0000, 1),
(156, 49, 52, 27, 'Swimwear - Final Inspection',       'OG-SWM-011', 3, 11, 1.3000, 1),
(157, 50, 52, 26, 'Swimwear - Needle Detection',       'OG-SWM-012', 1, 12, 0.2500, 1);

-- =====================================================
-- 8. OPERATION GRADING SKILLS
-- Maps skills required per operation grading
-- =====================================================
INSERT INTO operation_grading_skill (operation_grading_id, skill_id, is_active) VALUES
-- BRA gradings (1-17)
(1,  19, 1), -- Fabric Laying → Quality Inspection
(2,  11, 1), (2,  12, 1), -- Cutting → Fabric Cutting, Pattern Matching
(3,  14, 1), -- Fusing → Fusing Operation
(4,   1, 1), (4,  12, 1), -- Cup Assembly → SNLS, Pattern Matching
(5,   1, 1), -- Underwire Casing → SNLS
(6,   1, 1), -- Bridge Seam → SNLS
(7,   1, 1), -- Wing Seam → SNLS
(8,   2, 1), -- Back Seam → Overlock
(9,   1, 1), -- Strap Attach → SNLS
(10,  1, 1), -- Clasp Attach → SNLS
(11,  5, 1), -- Hook & Eye → Bartack
(12,  1, 1), (12, 15, 1), -- Elastic Leg → SNLS, Elastic Attachment
(13, 17, 1), -- Label Attach → Label Attachment
(14, 19, 1), -- Underwire Insert → Quality Inspection
(15, 13, 1), -- Final Pressing → Steam Pressing
(16, 19, 1), -- Final Inspection → Quality Inspection
(17, 18, 1), -- Needle Detection → Needle Detection

-- THONG gradings (18-28)
(18, 19, 1),
(19, 11, 1), (19, 12, 1),
(20,  1, 1),
(21,  2, 1),
(22,  1, 1),
(23,  1, 1), (23, 15, 1),
(24,  1, 1), (24, 15, 1),
(25,  1, 1), (25, 16, 1),
(26, 17, 1),
(27, 19, 1),
(28, 18, 1),

-- BRIEF gradings (29-41)
(29, 19, 1),
(30, 11, 1), (30, 12, 1),
(31,  1, 1),
(32,  2, 1),
(33,  2, 1),
(34,  2, 1),
(35,  1, 1),
(36,  1, 1), (36, 15, 1),
(37,  1, 1), (37, 15, 1),
(38, 17, 1),
(39, 13, 1),
(40, 19, 1),
(41, 18, 1),

-- LEGGING gradings (42-53)
(42, 19, 1),
(43, 11, 1), (43, 12, 1),
(44,  3, 1),
(45,  3, 1),
(46,  2, 1),
(47,  1, 1),
(48,  1, 1), (48, 15, 1),
(49,  3, 1),
(50, 17, 1),
(51, 13, 1),
(52, 19, 1),
(53, 18, 1),

-- T-SHIRT gradings (54-65)
(54, 19, 1),
(55, 11, 1), (55, 12, 1),
(56,  2, 1),
(57,  2, 1),
(58,  2, 1),
(59,  1, 1),
(60,  3, 1),
(61,  3, 1),
(62, 17, 1),
(63, 13, 1),
(64, 19, 1),
(65, 18, 1),

-- SHORT gradings (66-77)
(66, 19, 1),
(67, 11, 1), (67, 12, 1),
(68,  2, 1),
(69,  2, 1),
(70,  2, 1),
(71,  1, 1),
(72,  1, 1), (72, 15, 1),
(73,  3, 1),
(74, 17, 1),
(75, 13, 1),
(76, 19, 1),
(77, 18, 1),

-- SPORTS BRA gradings (78-89)
(78, 19, 1),
(79, 11, 1), (79, 12, 1),
(80,  3, 1),
(81,  3, 1),
(82,  3, 1),
(83,  1, 1),
(84,  1, 1),
(85,  3, 1),
(86, 17, 1),
(87, 13, 1),
(88, 19, 1),
(89, 18, 1),

-- HIPSTER gradings (90-99)
(90, 19, 1),
(91, 11, 1), (91, 12, 1),
(92,  1, 1),
(93,  2, 1),
(94,  2, 1),
(95,  1, 1),
(96,  1, 1), (96, 15, 1),
(97, 17, 1),
(98, 19, 1),
(99, 18, 1),

-- BOYSHORT gradings (100-110)
(100, 19, 1),
(101, 11, 1), (101, 12, 1),
(102,  2, 1),
(103,  2, 1),
(104,  2, 1),
(105,  3, 1),
(106,  1, 1), (106, 15, 1),
(107, 17, 1),
(108, 13, 1),
(109, 19, 1),
(110, 18, 1),

-- BRALETTE gradings (111-122)
(111, 19, 1),
(112, 11, 1), (112, 12, 1),
(113,  1, 1), (113, 12, 1),
(114,  2, 1),
(115,  2, 1),
(116,  1, 1),
(117,  1, 1), (117, 15, 1),
(118,  1, 1), (118, 16, 1),
(119, 17, 1),
(120, 13, 1),
(121, 19, 1),
(122, 18, 1),

-- TANK gradings (123-133)
(123, 19, 1),
(124, 11, 1), (124, 12, 1),
(125,  2, 1),
(126,  2, 1),
(127,  1, 1),
(128,  1, 1),
(129,  1, 1),
(130, 17, 1),
(131, 13, 1),
(132, 19, 1),
(133, 18, 1),

-- JOGGER gradings (134-145)
(134, 19, 1),
(135, 11, 1), (135, 12, 1),
(136,  2, 1),
(137,  2, 1),
(138,  2, 1),
(139,  1, 1),
(140,  1, 1), (140, 15, 1),
(141,  1, 1),
(142, 17, 1),
(143, 13, 1),
(144, 19, 1),
(145, 18, 1),

-- SWIMWEAR gradings (146-157)
(146, 19, 1),
(147, 11, 1), (147, 12, 1),
(148, 14, 1),
(149,  1, 1),
(150,  2, 1),
(151,  2, 1),
(152,  1, 1), (152, 15, 1),
(153,  1, 1), (153, 15, 1),
(154, 17, 1),
(155, 13, 1),
(156, 19, 1),
(157, 18, 1);

-- =====================================================
-- 9. PRODUCTS (62 products)
-- product_category_id references existing categories
-- =====================================================
INSERT INTO products (id, description, style_code, product_category_id, is_active) VALUES
-- BRA (cat 2)
(1,  'Ladies Underwire Bra Classic',    'P-BRA-001', 2,  1),
(2,  'Ladies Push-Up Bra Deluxe',       'P-BRA-002', 2,  1),
(3,  'Seamless T-Shirt Bra',            'P-BRA-003', 2,  1),
(4,  'Strapless Multi-Way Bra',         'P-BRA-004', 2,  1),
(5,  'Nursing Bra Comfort Plus',        'P-BRA-005', 2,  1),
(6,  'Minimizer Bra Full Coverage',     'P-BRA-006', 2,  1),
-- BRALETTE (cat 4)
(7,  'Wire-Free Bralette Essential',    'P-BRL-001', 4,  1),
(8,  'Lace Bralette Trim Style',        'P-BRL-002', 4,  1),
(9,  'Crop Bralette Active',            'P-BRL-003', 4,  1),
-- SPORTS BRA (cat 5)
(10, 'High Impact Sports Bra Pro',      'P-SPB-001', 5,  1),
(11, 'Medium Support Sports Bra',       'P-SPB-002', 5,  1),
(12, 'Low Impact Yoga Bra',             'P-SPB-003', 5,  1),
(13, 'Racerback Training Bra',          'P-SPB-004', 5,  1),
-- THONG (cat 8)
(14, 'Classic Cotton Thong',            'P-THG-001', 8,  1),
(15, 'Lace Trim Thong',                 'P-THG-002', 8,  1),
(16, 'High Waist Thong Shaper',         'P-THG-003', 8,  1),
-- HIPSTER (cat 9)
(17, 'Cotton Hipster Brief',            'P-HIP-001', 9,  1),
(18, 'Seamless Hipster Style',          'P-HIP-002', 9,  1),
(19, 'Microfiber Hipster',              'P-HIP-003', 9,  1),
-- BRIEF (cat 10)
(20, 'Classic Full Brief',              'P-BRF-001', 10, 1),
(21, 'High Waist Brief Control',        'P-BRF-002', 10, 1),
(22, 'Cotton Brief Comfort',            'P-BRF-003', 10, 1),
(23, 'Lace Trim Brief',                 'P-BRF-004', 10, 1),
-- BOYSHORT (cat 11)
(24, 'Cotton Boyshort Classic',         'P-BYS-001', 11, 1),
(25, 'Lace Boyshort Trim',              'P-BYS-002', 11, 1),
(26, 'Modal Boyshort Comfort',          'P-BYS-003', 11, 1),
-- BOXER (cat 12)
(27, 'Mens Cotton Boxer Standard',      'P-BOX-001', 12, 1),
(28, 'Mens Trunk Boxer Style',          'P-BOX-002', 12, 1),
-- BIKINI (cat 14)
(29, 'Classic Bikini Bottom',           'P-BKN-001', 14, 1),
(30, 'High Waist Bikini Bottom',        'P-BKN-002', 14, 1),
(31, 'Tie-Side Bikini Bottom',          'P-BKN-003', 14, 1),
-- BODY SUIT (cat 16)
(32, 'Bodysuit Sleeveless Classic',     'P-BDS-001', 16, 1),
(33, 'Lace Bodysuit Long Sleeve',       'P-BDS-002', 16, 1),
-- T-SHIRT (cat 24)
(34, 'Womens Basic Crew Tee',           'P-TSH-001', 24, 1),
(35, 'Mens Classic T-Shirt',            'P-TSH-002', 24, 1),
(36, 'V-Neck T-Shirt Slim Fit',         'P-TSH-003', 24, 1),
(37, 'Oversized Graphic T-Shirt',       'P-TSH-004', 24, 1),
(38, 'Longline T-Shirt',                'P-TSH-005', 24, 1),
-- TOP (cat 25)
(39, 'Woven Blouse Top Style A',        'P-TOP-001', 25, 1),
(40, 'Jersey Knit Top Style B',         'P-TOP-002', 25, 1),
-- TANK (cat 26)
(41, 'Basic Tank Top Classic',          'P-TNK-001', 26, 1),
(42, 'Ribbed Tank Style',               'P-TNK-002', 26, 1),
(43, 'Longline Tank Active',            'P-TNK-003', 26, 1),
-- CAMI (cat 28)
(44, 'Satin Cami Deluxe',               'P-CAM-001', 28, 1),
(45, 'Lace Trim Cami',                  'P-CAM-002', 28, 1),
-- HOODIE (cat 32)
(46, 'Pullover Hoodie Fleece',          'P-HOD-001', 32, 1),
(47, 'Zip-Up Hoodie Style',             'P-HOD-002', 32, 1),
-- LEGGING (cat 41)
(48, 'Full Length Legging Classic',     'P-LEG-001', 41, 1),
(49, 'Cropped Legging Active',          'P-LEG-002', 41, 1),
(50, 'High Waist Legging Pro',          'P-LEG-003', 41, 1),
(51, 'Compression Legging',             'P-LEG-004', 41, 1),
(52, 'Seamless Legging Minimal',        'P-LEG-005', 41, 1),
-- JOGGER (cat 42)
(53, 'Fleece Jogger Classic',           'P-JOG-001', 42, 1),
(54, 'Slim Fit Jogger',                 'P-JOG-002', 42, 1),
(55, 'Cotton Jogger Comfort',           'P-JOG-003', 42, 1),
-- SHORT (cat 1)
(56, 'Athletic Training Short',         'P-SHT-001', 1,  1),
(57, 'Casual Woven Short',              'P-SHT-002', 1,  1),
(58, 'Compression Short',               'P-SHT-003', 1,  1),
(59, 'Running Short',                   'P-SHT-004', 1,  1),
-- SWIMWEAR (cat 52)
(60, 'One Piece Swimsuit Classic',      'P-SWM-001', 52, 1),
(61, 'Bikini Set Full Style',           'P-SWM-002', 52, 1),
(62, 'Rash Guard Top',                  'P-SWM-003', 52, 1);

-- =====================================================
-- 10. PRODUCT OPERATION GRADINGS
-- Links each product to its operation grading sequence
-- SMV values vary slightly per product style (±5-10%)
-- =====================================================

-- BRA products (1-6) → OG IDs 1-17
-- Product 1: P-BRA-001 Classic (base SMV)
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(1,1,1,0.5000,1),(1,2,2,1.2000,1),(1,3,3,0.8000,1),(1,4,4,3.5000,1),(1,5,5,2.8000,1),
(1,6,6,1.5000,1),(1,7,7,2.2000,1),(1,8,8,1.8000,1),(1,9,9,2.5000,1),(1,10,10,3.0000,1),
(1,11,11,1.5000,1),(1,12,12,1.8000,1),(1,13,13,0.4000,1),(1,14,14,1.8000,1),(1,15,15,1.2000,1),
(1,16,16,1.5000,1),(1,17,17,0.3000,1);

-- Product 2: P-BRA-002 Push-Up (slightly higher SMV - more complex)
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(2,1,1,0.5000,1),(2,2,2,1.2000,1),(2,3,3,0.9000,1),(2,4,4,3.8000,1),(2,5,5,3.0000,1),
(2,6,6,1.6000,1),(2,7,7,2.4000,1),(2,8,8,1.9000,1),(2,9,9,2.6000,1),(2,10,10,3.2000,1),
(2,11,11,1.6000,1),(2,12,12,1.9000,1),(2,13,13,0.4000,1),(2,14,14,2.0000,1),(2,15,15,1.2000,1),
(2,16,16,1.5000,1),(2,17,17,0.3000,1);

-- Product 3: P-BRA-003 Seamless (lower SMV - simpler construction)
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(3,1,1,0.5000,1),(3,2,2,1.1000,1),(3,3,3,0.7000,1),(3,4,4,3.2000,1),(3,5,5,2.5000,1),
(3,6,6,1.3000,1),(3,7,7,2.0000,1),(3,8,8,1.6000,1),(3,9,9,2.3000,1),(3,10,10,2.8000,1),
(3,11,11,1.4000,1),(3,12,12,1.7000,1),(3,13,13,0.4000,1),(3,14,14,1.6000,1),(3,15,15,1.1000,1),
(3,16,16,1.5000,1),(3,17,17,0.3000,1);

-- Product 4: P-BRA-004 Strapless
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(4,1,1,0.5000,1),(4,2,2,1.2000,1),(4,3,3,0.8000,1),(4,4,4,3.6000,1),(4,5,5,2.9000,1),
(4,6,6,1.5000,1),(4,7,7,2.3000,1),(4,8,8,1.8000,1),(4,9,9,NULL,1),(4,10,10,3.1000,1),
(4,11,11,1.5000,1),(4,12,12,2.0000,1),(4,13,13,0.4000,1),(4,14,14,1.8000,1),(4,15,15,1.2000,1),
(4,16,16,1.5000,1),(4,17,17,0.3000,1);

-- Product 5: P-BRA-005 Nursing Bra
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(5,1,1,0.5000,1),(5,2,2,1.3000,1),(5,3,3,0.9000,1),(5,4,4,3.7000,1),(5,5,5,2.9000,1),
(5,6,6,1.5000,1),(5,7,7,2.2000,1),(5,8,8,1.8000,1),(5,9,9,2.5000,1),(5,10,10,3.0000,1),
(5,11,11,1.5000,1),(5,12,12,1.8000,1),(5,13,13,0.4000,1),(5,14,14,1.9000,1),(5,15,15,1.2000,1),
(5,16,16,1.5000,1),(5,17,17,0.3000,1);

-- Product 6: P-BRA-006 Minimizer
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(6,1,1,0.5000,1),(6,2,2,1.2000,1),(6,3,3,0.8000,1),(6,4,4,3.5000,1),(6,5,5,2.8000,1),
(6,6,6,1.5000,1),(6,7,7,2.2000,1),(6,8,8,1.8000,1),(6,9,9,2.5000,1),(6,10,10,3.0000,1),
(6,11,11,1.5000,1),(6,12,12,1.8000,1),(6,13,13,0.4000,1),(6,14,14,1.8000,1),(6,15,15,1.3000,1),
(6,16,16,1.5000,1),(6,17,17,0.3000,1);

-- BRALETTE products (7-9) → OG IDs 111-122
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(7,111,1,0.4500,1),(7,112,2,0.9000,1),(7,113,3,2.5000,1),(7,114,4,1.5000,1),(7,115,5,1.2000,1),
(7,116,6,2.0000,1),(7,117,7,1.8000,1),(7,118,8,2.8000,1),(7,119,9,0.4000,1),(7,120,10,0.9000,1),
(7,121,11,1.1000,1),(7,122,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(8,111,1,0.4500,1),(8,112,2,0.9000,1),(8,113,3,2.6000,1),(8,114,4,1.5000,1),(8,115,5,1.2000,1),
(8,116,6,2.0000,1),(8,117,7,1.9000,1),(8,118,8,3.1000,1),(8,119,9,0.4000,1),(8,120,10,0.9000,1),
(8,121,11,1.1000,1),(8,122,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(9,111,1,0.4500,1),(9,112,2,0.8500,1),(9,113,3,2.3000,1),(9,114,4,1.4000,1),(9,115,5,1.1000,1),
(9,116,6,1.9000,1),(9,117,7,1.7000,1),(9,118,8,2.6000,1),(9,119,9,0.4000,1),(9,120,10,0.8000,1),
(9,121,11,1.1000,1),(9,122,12,0.2500,1);

-- SPORTS BRA products (10-13) → OG IDs 78-89
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(10,78,1,0.5500,1),(10,79,2,1.0000,1),(10,80,3,2.5000,1),(10,81,4,2.2000,1),(10,82,5,1.8000,1),
(10,83,6,2.5000,1),(10,84,7,2.0000,1),(10,85,8,1.5000,1),(10,86,9,0.4000,1),(10,87,10,1.0000,1),
(10,88,11,1.2000,1),(10,89,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(11,78,1,0.5500,1),(11,79,2,1.0000,1),(11,80,3,2.3000,1),(11,81,4,2.0000,1),(11,82,5,1.6000,1),
(11,83,6,2.3000,1),(11,84,7,1.8000,1),(11,85,8,1.4000,1),(11,86,9,0.4000,1),(11,87,10,0.9000,1),
(11,88,11,1.2000,1),(11,89,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(12,78,1,0.5000,1),(12,79,2,0.9500,1),(12,80,3,2.1000,1),(12,81,4,1.8000,1),(12,82,5,1.5000,1),
(12,83,6,2.1000,1),(12,84,7,1.7000,1),(12,85,8,1.3000,1),(12,86,9,0.4000,1),(12,87,10,0.9000,1),
(12,88,11,1.2000,1),(12,89,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(13,78,1,0.5500,1),(13,79,2,1.0000,1),(13,80,3,2.4000,1),(13,81,4,2.1000,1),(13,82,5,1.7000,1),
(13,83,6,2.4000,1),(13,84,7,1.9000,1),(13,85,8,1.4000,1),(13,86,9,0.4000,1),(13,87,10,1.0000,1),
(13,88,11,1.2000,1),(13,89,12,0.2500,1);

-- THONG products (14-16) → OG IDs 18-28
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(14,18,1,0.4000,1),(14,19,2,0.8000,1),(14,20,3,1.2000,1),(14,21,4,1.5000,1),(14,22,5,1.0000,1),
(14,23,6,2.0000,1),(14,24,7,2.5000,1),(14,25,8,3.0000,1),(14,26,9,0.3500,1),(14,27,10,1.0000,1),
(14,28,11,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(15,18,1,0.4000,1),(15,19,2,0.8000,1),(15,20,3,1.2000,1),(15,21,4,1.5000,1),(15,22,5,1.0000,1),
(15,23,6,2.0000,1),(15,24,7,2.5000,1),(15,25,8,3.3000,1),(15,26,9,0.3500,1),(15,27,10,1.0000,1),
(15,28,11,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(16,18,1,0.4000,1),(16,19,2,0.8000,1),(16,20,3,1.3000,1),(16,21,4,1.6000,1),(16,22,5,1.1000,1),
(16,23,6,2.1000,1),(16,24,7,2.6000,1),(16,25,8,3.0000,1),(16,26,9,0.3500,1),(16,27,10,1.0000,1),
(16,28,11,0.2500,1);

-- HIPSTER products (17-19) → OG IDs 90-99
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(17,90,1,0.4000,1),(17,91,2,0.8500,1),(17,92,3,1.2000,1),(17,93,4,1.5000,1),(17,94,5,1.4000,1),
(17,95,6,2.0000,1),(17,96,7,1.8000,1),(17,97,8,0.3500,1),(17,98,9,0.9000,1),(17,99,10,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(18,90,1,0.4000,1),(18,91,2,0.8000,1),(18,92,3,1.1000,1),(18,93,4,1.4000,1),(18,94,5,1.3000,1),
(18,95,6,1.9000,1),(18,96,7,1.7000,1),(18,97,8,0.3500,1),(18,98,9,0.9000,1),(18,99,10,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(19,90,1,0.4000,1),(19,91,2,0.8500,1),(19,92,3,1.2000,1),(19,93,4,1.5000,1),(19,94,5,1.4000,1),
(19,95,6,2.0000,1),(19,96,7,1.8000,1),(19,97,8,0.3500,1),(19,98,9,0.9000,1),(19,99,10,0.2500,1);

-- BRIEF products (20-23) → OG IDs 29-41
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(20,29,1,0.4500,1),(20,30,2,0.9000,1),(20,31,3,1.3000,1),(20,32,4,1.6000,1),(20,33,5,1.2000,1),
(20,34,6,1.5000,1),(20,35,7,2.2000,1),(20,36,8,2.0000,1),(20,37,9,2.8000,1),(20,38,10,0.3500,1),
(20,39,11,0.8000,1),(20,40,12,1.0000,1),(20,41,13,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(21,29,1,0.4500,1),(21,30,2,0.9000,1),(21,31,3,1.4000,1),(21,32,4,1.7000,1),(21,33,5,1.3000,1),
(21,34,6,1.6000,1),(21,35,7,2.3000,1),(21,36,8,2.1000,1),(21,37,9,2.9000,1),(21,38,10,0.3500,1),
(21,39,11,0.8000,1),(21,40,12,1.0000,1),(21,41,13,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(22,29,1,0.4500,1),(22,30,2,0.8500,1),(22,31,3,1.2000,1),(22,32,4,1.5000,1),(22,33,5,1.1000,1),
(22,34,6,1.4000,1),(22,35,7,2.1000,1),(22,36,8,1.9000,1),(22,37,9,2.7000,1),(22,38,10,0.3500,1),
(22,39,11,0.8000,1),(22,40,12,1.0000,1),(22,41,13,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(23,29,1,0.4500,1),(23,30,2,0.9000,1),(23,31,3,1.3000,1),(23,32,4,1.6000,1),(23,33,5,1.2000,1),
(23,34,6,1.5000,1),(23,35,7,2.2000,1),(23,36,8,2.0000,1),(23,37,9,2.8000,1),(23,38,10,0.3500,1),
(23,39,11,0.8000,1),(23,40,12,1.0000,1),(23,41,13,0.2500,1);

-- BOYSHORT products (24-26) → OG IDs 100-110
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(24,100,1,0.4500,1),(24,101,2,0.8500,1),(24,102,3,1.5000,1),(24,103,4,1.5000,1),(24,104,5,1.5000,1),
(24,105,6,2.2000,1),(24,106,7,1.8000,1),(24,107,8,0.3500,1),(24,108,9,0.8000,1),(24,109,10,0.9000,1),
(24,110,11,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(25,100,1,0.4500,1),(25,101,2,0.8500,1),(25,102,3,1.5000,1),(25,103,4,1.5000,1),(25,104,5,1.5000,1),
(25,105,6,2.4000,1),(25,106,7,1.8000,1),(25,107,8,0.3500,1),(25,108,9,0.8000,1),(25,109,10,0.9000,1),
(25,110,11,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(26,100,1,0.4500,1),(26,101,2,0.8000,1),(26,102,3,1.4000,1),(26,103,4,1.4000,1),(26,104,5,1.4000,1),
(26,105,6,2.1000,1),(26,106,7,1.7000,1),(26,107,8,0.3500,1),(26,108,9,0.8000,1),(26,109,10,0.9000,1),
(26,110,11,0.2500,1);

-- T-SHIRT products (34-38) → OG IDs 54-65
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(34,54,1,0.5500,1),(34,55,2,1.0000,1),(34,56,3,1.5000,1),(34,57,4,2.0000,1),(34,58,5,1.8000,1),
(34,59,6,2.2000,1),(34,60,7,1.8000,1),(34,61,8,1.5000,1),(34,62,9,0.4000,1),(34,63,10,1.0000,1),
(34,64,11,1.2000,1),(34,65,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(35,54,1,0.5500,1),(35,55,2,1.0500,1),(35,56,3,1.6000,1),(35,57,4,2.1000,1),(35,58,5,1.9000,1),
(35,59,6,2.2000,1),(35,60,7,1.9000,1),(35,61,8,1.5000,1),(35,62,9,0.4000,1),(35,63,10,1.0000,1),
(35,64,11,1.2000,1),(35,65,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(36,54,1,0.5500,1),(36,55,2,1.0000,1),(36,56,3,1.5000,1),(36,57,4,2.0000,1),(36,58,5,1.8000,1),
(36,59,6,2.0000,1),(36,60,7,1.8000,1),(36,61,8,1.5000,1),(36,62,9,0.4000,1),(36,63,10,1.0000,1),
(36,64,11,1.2000,1),(36,65,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(37,54,1,0.5500,1),(37,55,2,1.1000,1),(37,56,3,1.6000,1),(37,57,4,2.1000,1),(37,58,5,1.9000,1),
(37,59,6,2.3000,1),(37,60,7,1.9000,1),(37,61,8,1.6000,1),(37,62,9,0.4000,1),(37,63,10,1.0000,1),
(37,64,11,1.2000,1),(37,65,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(38,54,1,0.5500,1),(38,55,2,1.0000,1),(38,56,3,1.5000,1),(38,57,4,2.0000,1),(38,58,5,1.9000,1),
(38,59,6,2.2000,1),(38,60,7,1.9000,1),(38,61,8,1.7000,1),(38,62,9,0.4000,1),(38,63,10,1.0000,1),
(38,64,11,1.2000,1),(38,65,12,0.2500,1);

-- TANK products (41-43) → OG IDs 123-133
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(41,123,1,0.5000,1),(41,124,2,0.9000,1),(41,125,3,1.4000,1),(41,126,4,1.8000,1),(41,127,5,2.0000,1),
(41,128,6,2.0000,1),(41,129,7,1.5000,1),(41,130,8,0.3500,1),(41,131,9,0.8000,1),(41,132,10,1.0000,1),
(41,133,11,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(42,123,1,0.5000,1),(42,124,2,0.9000,1),(42,125,3,1.4000,1),(42,126,4,1.8000,1),(42,127,5,2.0000,1),
(42,128,6,2.0000,1),(42,129,7,1.5000,1),(42,130,8,0.3500,1),(42,131,9,0.8000,1),(42,132,10,1.0000,1),
(42,133,11,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(43,123,1,0.5000,1),(43,124,2,0.9000,1),(43,125,3,1.4000,1),(43,126,4,1.9000,1),(43,127,5,2.1000,1),
(43,128,6,2.1000,1),(43,129,7,1.7000,1),(43,130,8,0.3500,1),(43,131,9,0.8000,1),(43,132,10,1.0000,1),
(43,133,11,0.2500,1);

-- LEGGING products (48-52) → OG IDs 42-53
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(48,42,1,0.6000,1),(48,43,2,1.1000,1),(48,44,3,2.5000,1),(48,45,4,2.5000,1),(48,46,5,1.8000,1),
(48,47,6,2.0000,1),(48,48,7,2.2000,1),(48,49,8,2.8000,1),(48,50,9,0.3500,1),(48,51,10,1.0000,1),
(48,52,11,1.2000,1),(48,53,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(49,42,1,0.5500,1),(49,43,2,1.0000,1),(49,44,3,2.2000,1),(49,45,4,2.2000,1),(49,46,5,1.7000,1),
(49,47,6,1.9000,1),(49,48,7,2.1000,1),(49,49,8,2.5000,1),(49,50,9,0.3500,1),(49,51,10,1.0000,1),
(49,52,11,1.2000,1),(49,53,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(50,42,1,0.6000,1),(50,43,2,1.1000,1),(50,44,3,2.6000,1),(50,45,4,2.6000,1),(50,46,5,1.9000,1),
(50,47,6,2.1000,1),(50,48,7,2.3000,1),(50,49,8,2.9000,1),(50,50,9,0.3500,1),(50,51,10,1.0000,1),
(50,52,11,1.2000,1),(50,53,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(51,42,1,0.6000,1),(51,43,2,1.1000,1),(51,44,3,2.7000,1),(51,45,4,2.7000,1),(51,46,5,2.0000,1),
(51,47,6,2.2000,1),(51,48,7,2.4000,1),(51,49,8,3.0000,1),(51,50,9,0.3500,1),(51,51,10,1.0000,1),
(51,52,11,1.2000,1),(51,53,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(52,42,1,0.5500,1),(52,43,2,1.0000,1),(52,44,3,2.3000,1),(52,45,4,2.3000,1),(52,46,5,1.7000,1),
(52,47,6,1.9000,1),(52,48,7,2.0000,1),(52,49,8,2.6000,1),(52,50,9,0.3500,1),(52,51,10,1.0000,1),
(52,52,11,1.2000,1),(52,53,12,0.2500,1);

-- JOGGER products (53-55) → OG IDs 134-145
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(53,134,1,0.6000,1),(53,135,2,1.1000,1),(53,136,3,2.0000,1),(53,137,4,2.0000,1),(53,138,5,1.8000,1),
(53,139,6,2.0000,1),(53,140,7,2.5000,1),(53,141,8,2.5000,1),(53,142,9,0.3500,1),(53,143,10,1.0000,1),
(53,144,11,1.2000,1),(53,145,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(54,134,1,0.6000,1),(54,135,2,1.1000,1),(54,136,3,2.0000,1),(54,137,4,2.0000,1),(54,138,5,1.8000,1),
(54,139,6,2.0000,1),(54,140,7,2.5000,1),(54,141,8,2.5000,1),(54,142,9,0.3500,1),(54,143,10,1.0000,1),
(54,144,11,1.2000,1),(54,145,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(55,134,1,0.6000,1),(55,135,2,1.0500,1),(55,136,3,1.9000,1),(55,137,4,1.9000,1),(55,138,5,1.7000,1),
(55,139,6,1.9000,1),(55,140,7,2.4000,1),(55,141,8,2.4000,1),(55,142,9,0.3500,1),(55,143,10,1.0000,1),
(55,144,11,1.2000,1),(55,145,12,0.2500,1);

-- SHORT products (56-59) → OG IDs 66-77
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(56,66,1,0.5000,1),(56,67,2,0.9000,1),(56,68,3,1.8000,1),(56,69,4,1.8000,1),(56,70,5,1.5000,1),
(56,71,6,1.8000,1),(56,72,7,2.0000,1),(56,73,8,2.5000,1),(56,74,9,0.3500,1),(56,75,10,0.8000,1),
(56,76,11,1.0000,1),(56,77,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(57,66,1,0.5000,1),(57,67,2,0.9500,1),(57,68,3,1.9000,1),(57,69,4,1.9000,1),(57,70,5,1.6000,1),
(57,71,6,1.9000,1),(57,72,7,2.1000,1),(57,73,8,2.6000,1),(57,74,9,0.3500,1),(57,75,10,0.8000,1),
(57,76,11,1.0000,1),(57,77,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(58,66,1,0.5000,1),(58,67,2,0.9000,1),(58,68,3,1.8000,1),(58,69,4,1.8000,1),(58,70,5,1.5000,1),
(58,71,6,1.8000,1),(58,72,7,2.0000,1),(58,73,8,2.5000,1),(58,74,9,0.3500,1),(58,75,10,0.8000,1),
(58,76,11,1.0000,1),(58,77,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(59,66,1,0.5000,1),(59,67,2,0.9000,1),(59,68,3,1.7000,1),(59,69,4,1.7000,1),(59,70,5,1.5000,1),
(59,71,6,1.8000,1),(59,72,7,2.0000,1),(59,73,8,2.4000,1),(59,74,9,0.3500,1),(59,75,10,0.8000,1),
(59,76,11,1.0000,1),(59,77,12,0.2500,1);

-- SWIMWEAR products (60-62) → OG IDs 146-157
INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(60,146,1,0.5500,1),(60,147,2,1.0000,1),(60,148,3,0.7000,1),(60,149,4,2.5000,1),(60,150,5,1.8000,1),
(60,151,6,1.5000,1),(60,152,7,2.0000,1),(60,153,8,2.5000,1),(60,154,9,0.4000,1),(60,155,10,1.0000,1),
(60,156,11,1.3000,1),(60,157,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(61,146,1,0.5500,1),(61,147,2,1.0000,1),(61,148,3,0.6500,1),(61,149,4,2.3000,1),(61,150,5,1.7000,1),
(61,151,6,1.4000,1),(61,152,7,1.9000,1),(61,153,8,2.3000,1),(61,154,9,0.4000,1),(61,155,10,1.0000,1),
(61,156,11,1.3000,1),(61,157,12,0.2500,1);

INSERT INTO product_operation_gradings (product_id, operation_grading_id, sequence_no, smv, is_active) VALUES
(62,146,1,0.5000,1),(62,147,2,0.9500,1),(62,148,3,0.6000,1),(62,149,4,2.2000,1),(62,150,5,1.6000,1),
(62,151,6,1.3000,1),(62,152,7,1.8000,1),(62,153,8,2.2000,1),(62,154,9,0.4000,1),(62,155,10,0.9000,1),
(62,156,11,1.3000,1),(62,157,12,0.2500,1);

SET FOREIGN_KEY_CHECKS = 1;
