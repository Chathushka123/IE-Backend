-- =====================================================
-- Line Plans Seed Data
-- Schedule from 2026-07-03 08:00 onwards (Mon-Fri)
-- Today = Thursday 2026-07-03
--
-- Working day slots:
--   D0  Thu 03-Jul  08:00–17:00   D1  Fri 04-Jul
--   D2  Mon 07-Jul               D3  Tue 08-Jul
--   D4  Wed 09-Jul               D5  Thu 10-Jul
--   D6  Fri 11-Jul               D7  Mon 14-Jul
--   D8  Tue 15-Jul               D9  Wed 16-Jul
-- =====================================================
-- Run: mysql -u app_user -p ie_module < database/seeds/line_plans_data.sql

SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE line_plans;

INSERT INTO line_plans
  (team_id, product_id, sequence_no,
   planned_quantity, planned_start_date, planned_end_date,
   actual_start_date, status, notes, is_changeover, is_active)
SELECT pl.id, p.id, lp.seq, lp.qty, lp.ps, lp.pe, lp.act, lp.st, lp.ref, 0, 1
FROM (

  -- ── BRA-A  Underwire bra specialist ──────────────────────────
  SELECT 'BRA-A' ln,'P-BRA-001' pc,1 seq, 600 qty,'2026-07-03 08:00:00' ps,'2026-07-04 17:00:00' pe,'2026-07-03 08:00:00' act,'in_progress' st,'SO-2026-0701' ref
  UNION ALL SELECT 'BRA-A','P-BRA-002',2, 800,'2026-07-07 08:00:00','2026-07-09 17:00:00',NULL,'planned','SO-2026-0712'
  UNION ALL SELECT 'BRA-A','P-BRA-006',3, 700,'2026-07-10 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0715'
  UNION ALL SELECT 'BRA-A','P-BRA-001',4,1000,'2026-07-14 08:00:00','2026-07-16 17:00:00',NULL,'planned','SO-2026-0720'

  -- ── BRA-B ────────────────────────────────────────────────────
  UNION ALL SELECT 'BRA-B','P-BRA-003',1, 500,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0702'
  UNION ALL SELECT 'BRA-B','P-BRA-004',2, 650,'2026-07-07 08:00:00','2026-07-09 17:00:00',NULL,'planned','SO-2026-0713'
  UNION ALL SELECT 'BRA-B','P-BRA-005',3, 550,'2026-07-10 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0716'
  UNION ALL SELECT 'BRA-B','P-BRA-003',4, 800,'2026-07-14 08:00:00','2026-07-16 17:00:00',NULL,'planned','SO-2026-0721'

  -- ── BRA-C ────────────────────────────────────────────────────
  UNION ALL SELECT 'BRA-C','P-BRA-005',1, 450,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0703'
  UNION ALL SELECT 'BRA-C','P-BRA-004',2, 600,'2026-07-07 08:00:00','2026-07-09 17:00:00',NULL,'planned','SO-2026-0714'
  UNION ALL SELECT 'BRA-C','P-BRA-002',3, 750,'2026-07-10 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0717'

  -- ── THB-A  Thong & Brief ──────────────────────────────────────
  UNION ALL SELECT 'THB-A','P-THG-001',1,2000,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0704'
  UNION ALL SELECT 'THB-A','P-BRF-001',2,1500,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0705'
  UNION ALL SELECT 'THB-A','P-BRF-003',3,1800,'2026-07-09 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0706'
  UNION ALL SELECT 'THB-A','P-THG-003',4,2200,'2026-07-14 08:00:00','2026-07-15 17:00:00',NULL,'planned','SO-2026-0722'

  -- ── THB-B ────────────────────────────────────────────────────
  UNION ALL SELECT 'THB-B','P-THG-002',1,1800,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0707'
  UNION ALL SELECT 'THB-B','P-BRF-002',2,1200,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0708'
  UNION ALL SELECT 'THB-B','P-BRF-004',3,1500,'2026-07-09 08:00:00','2026-07-10 17:00:00',NULL,'planned','SO-2026-0709'
  UNION ALL SELECT 'THB-B','P-THG-001',4,2000,'2026-07-14 08:00:00','2026-07-15 17:00:00',NULL,'planned','SO-2026-0723'

  -- ── BYH-A  Boyshort & Hipster ─────────────────────────────────
  UNION ALL SELECT 'BYH-A','P-BYS-001',1,2500,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0710'
  UNION ALL SELECT 'BYH-A','P-HIP-001',2,2000,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0711'
  UNION ALL SELECT 'BYH-A','P-HIP-002',3,2200,'2026-07-09 08:00:00','2026-07-10 17:00:00',NULL,'planned','SO-2026-0718'
  UNION ALL SELECT 'BYH-A','P-BYS-003',4,1800,'2026-07-14 08:00:00','2026-07-15 17:00:00',NULL,'planned','SO-2026-0724'

  -- ── SPB-A  Sports Bra ─────────────────────────────────────────
  UNION ALL SELECT 'SPB-A','P-SPB-001',1, 800,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0725'
  UNION ALL SELECT 'SPB-A','P-SPB-003',2, 700,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0726'
  UNION ALL SELECT 'SPB-A','P-SPB-004',3, 650,'2026-07-09 08:00:00','2026-07-10 17:00:00',NULL,'planned','SO-2026-0727'
  UNION ALL SELECT 'SPB-A','P-SPB-001',4, 900,'2026-07-14 08:00:00','2026-07-16 17:00:00',NULL,'planned','SO-2026-0728'

  -- ── SPB-B  Sports Bra & Bralette ──────────────────────────────
  UNION ALL SELECT 'SPB-B','P-BRL-001',1, 600,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0729'
  UNION ALL SELECT 'SPB-B','P-BRL-002',2, 550,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0730'
  UNION ALL SELECT 'SPB-B','P-SPB-002',3, 750,'2026-07-09 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0731'
  UNION ALL SELECT 'SPB-B','P-BRL-003',4, 500,'2026-07-14 08:00:00','2026-07-15 17:00:00',NULL,'planned','SO-2026-0732'

  -- ── LGA-A  Legging & Active Bottoms ───────────────────────────
  UNION ALL SELECT 'LGA-A','P-LEG-001',1,1200,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0733'
  UNION ALL SELECT 'LGA-A','P-LEG-003',2,1000,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0734'
  UNION ALL SELECT 'LGA-A','P-JOG-001',3, 900,'2026-07-09 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0735'
  UNION ALL SELECT 'LGA-A','P-LEG-004',4,1100,'2026-07-14 08:00:00','2026-07-16 17:00:00',NULL,'planned','SO-2026-0736'

  -- ── LGA-B ────────────────────────────────────────────────────
  UNION ALL SELECT 'LGA-B','P-LEG-002',1,1100,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0737'
  UNION ALL SELECT 'LGA-B','P-LEG-004',2,1000,'2026-07-07 08:00:00','2026-07-09 17:00:00',NULL,'planned','SO-2026-0738'
  UNION ALL SELECT 'LGA-B','P-LEG-005',3, 900,'2026-07-10 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0739'
  UNION ALL SELECT 'LGA-B','P-JOG-002',4, 800,'2026-07-14 08:00:00','2026-07-15 17:00:00',NULL,'planned','SO-2026-0740'

  -- ── TSH-A  T-Shirt Knit ───────────────────────────────────────
  UNION ALL SELECT 'TSH-A','P-TSH-001',1,3000,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0741'
  UNION ALL SELECT 'TSH-A','P-TSH-002',2,2500,'2026-07-07 08:00:00','2026-07-09 17:00:00',NULL,'planned','SO-2026-0742'
  UNION ALL SELECT 'TSH-A','P-TSH-003',3,2000,'2026-07-10 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0743'
  UNION ALL SELECT 'TSH-A','P-TSH-001',4,3500,'2026-07-14 08:00:00','2026-07-16 17:00:00',NULL,'planned','SO-2026-0744'

  -- ── TSH-B ────────────────────────────────────────────────────
  UNION ALL SELECT 'TSH-B','P-TSH-004',1,2500,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0745'
  UNION ALL SELECT 'TSH-B','P-TSH-005',2,2200,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0746'
  UNION ALL SELECT 'TSH-B','P-TSH-002',3,2000,'2026-07-09 08:00:00','2026-07-10 17:00:00',NULL,'planned','SO-2026-0747'
  UNION ALL SELECT 'TSH-B','P-TSH-004',4,2800,'2026-07-14 08:00:00','2026-07-16 17:00:00',NULL,'planned','SO-2026-0748'

  -- ── TCM-A  Tank & Cami ────────────────────────────────────────
  UNION ALL SELECT 'TCM-A','P-TNK-001',1,2000,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0749'
  UNION ALL SELECT 'TCM-A','P-TNK-002',2,1500,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0750'
  UNION ALL SELECT 'TCM-A','P-CAM-001',3, 800,'2026-07-09 08:00:00','2026-07-09 17:00:00',NULL,'planned','SO-2026-0751'
  UNION ALL SELECT 'TCM-A','P-TNK-003',4,1800,'2026-07-10 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0752'
  UNION ALL SELECT 'TCM-A','P-TNK-001',5,2200,'2026-07-14 08:00:00','2026-07-15 17:00:00',NULL,'planned','SO-2026-0753'

  -- ── SJG-A  Shorts & Jogger ────────────────────────────────────
  UNION ALL SELECT 'SJG-A','P-SHT-001',1,2000,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0754'
  UNION ALL SELECT 'SJG-A','P-JOG-002',2,1500,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0755'
  UNION ALL SELECT 'SJG-A','P-SHT-003',3,1800,'2026-07-09 08:00:00','2026-07-10 17:00:00',NULL,'planned','SO-2026-0756'
  UNION ALL SELECT 'SJG-A','P-SHT-001',4,2500,'2026-07-14 08:00:00','2026-07-16 17:00:00',NULL,'planned','SO-2026-0757'

  -- ── SJG-B ────────────────────────────────────────────────────
  UNION ALL SELECT 'SJG-B','P-SHT-002',1,1500,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0758'
  UNION ALL SELECT 'SJG-B','P-JOG-003',2,1200,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0759'
  UNION ALL SELECT 'SJG-B','P-SHT-004',3,1500,'2026-07-09 08:00:00','2026-07-10 17:00:00',NULL,'planned','SO-2026-0760'
  UNION ALL SELECT 'SJG-B','P-JOG-001',4,1300,'2026-07-14 08:00:00','2026-07-15 17:00:00',NULL,'planned','SO-2026-0761'

  -- ── SWM-A  Swimwear ───────────────────────────────────────────
  UNION ALL SELECT 'SWM-A','P-SWM-001',1, 500,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0762'
  UNION ALL SELECT 'SWM-A','P-SWM-002',2, 400,'2026-07-07 08:00:00','2026-07-09 17:00:00',NULL,'planned','SO-2026-0763'
  UNION ALL SELECT 'SWM-A','P-SWM-001',3, 600,'2026-07-10 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0764'

  -- ── SWM-B ────────────────────────────────────────────────────
  UNION ALL SELECT 'SWM-B','P-SWM-003',1, 600,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0765'
  UNION ALL SELECT 'SWM-B','P-SWM-001',2, 450,'2026-07-07 08:00:00','2026-07-09 17:00:00',NULL,'planned','SO-2026-0766'
  UNION ALL SELECT 'SWM-B','P-SWM-002',3, 500,'2026-07-10 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0767'

  -- ── BKU-A  Bikini & Underwear ─────────────────────────────────
  UNION ALL SELECT 'BKU-A','P-BKN-001',1,1500,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0768'
  UNION ALL SELECT 'BKU-A','P-BKN-002',2,1200,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0769'
  UNION ALL SELECT 'BKU-A','P-BKN-003',3,1000,'2026-07-09 08:00:00','2026-07-10 17:00:00',NULL,'planned','SO-2026-0770'
  UNION ALL SELECT 'BKU-A','P-HIP-003',4,1800,'2026-07-14 08:00:00','2026-07-15 17:00:00',NULL,'planned','SO-2026-0771'

  -- ── BBX-A  Boxer & Boyshort ───────────────────────────────────
  UNION ALL SELECT 'BBX-A','P-BOX-001',1,2500,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0772'
  UNION ALL SELECT 'BBX-A','P-BYS-002',2,2000,'2026-07-07 08:00:00','2026-07-08 17:00:00',NULL,'planned','SO-2026-0773'
  UNION ALL SELECT 'BBX-A','P-BOX-002',3,2200,'2026-07-09 08:00:00','2026-07-10 17:00:00',NULL,'planned','SO-2026-0774'
  UNION ALL SELECT 'BBX-A','P-BOX-001',4,3000,'2026-07-14 08:00:00','2026-07-16 17:00:00',NULL,'planned','SO-2026-0775'

  -- ── DRS-A  Dress & Romper ─────────────────────────────────────
  UNION ALL SELECT 'DRS-A','P-DRS-001',1, 300,'2026-07-03 08:00:00','2026-07-04 17:00:00','2026-07-03 08:00:00','in_progress','SO-2026-0776'
  UNION ALL SELECT 'DRS-A','P-DRS-002',2, 250,'2026-07-07 08:00:00','2026-07-09 17:00:00',NULL,'planned','SO-2026-0777'
  UNION ALL SELECT 'DRS-A','P-DRS-001',3, 350,'2026-07-10 08:00:00','2026-07-11 17:00:00',NULL,'planned','SO-2026-0778'

) lp
JOIN teams    pl ON pl.code = lp.ln
JOIN products p  ON p.code  = lp.pc;

SET FOREIGN_KEY_CHECKS = 1;
