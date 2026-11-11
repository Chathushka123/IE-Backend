<?php

namespace App\Support\DemoData;

/**
 * The reference content of the demo dataset: what a small apparel factory's operation bank looks like.
 * Plain data only; DemoDatasetBuilder turns it into rows. Every code starts with PREFIX so the
 * rows are easy to recognise (and to refuse a second run).
 */
class DemoCatalog
{
    public const PREFIX = 'DM-';

    /** code => name */
    public const SKILLS = [
        'SNLS' => 'Single Needle Lockstitch',
        'OVL' => 'Overlock Operation',
        'FLK' => 'Flatlock Coverstitch',
        'BLS' => 'Blind Stitch Operation',
        'BTK' => 'Bartack Operation',
        'BTH' => 'Buttonhole Making',
        'BTA' => 'Button Attachment',
        'PRS' => 'Steam Pressing',
        'FUS' => 'Fusing Operation',
        'ELS' => 'Elastic Attachment',
        'LBL' => 'Label Attachment',
        'QCI' => 'Quality Inspection',
        'PKG' => 'Folding and Packing',
    ];

    /** code => [name, level] */
    public const GRADES = [
        'TRN' => ['Trainee', 1],
        'JNR' => ['Junior', 2],
        'STD' => ['Standard', 3],
        'SNR' => ['Senior', 4],
        'EXP' => ['Expert', 5],
    ];

    /** Expected efficiency bands (top, upper-mid, lower-mid) by grade: harder work, lower bar. */
    public const BANDS = [
        'TRN' => [90, 75, 60],
        'JNR' => [90, 75, 60],
        'STD' => [85, 70, 55],
        'SNR' => [80, 65, 50],
        'EXP' => [75, 60, 45],
    ];

    public const CATEGORIES = [
        'Preparation & Fusing',
        'Assembly',
        'Seaming',
        'Hemming & Finishing',
        'Attachment',
        'Pressing',
        'Quality & Inspection',
    ];

    /**
     * Base operations: name => [category, machine type (exact name in machine_types), base SMV, grade, skill codes].
     */
    public const BASE_OPERATIONS = [
        'Fusing Front Panel' => ['Preparation & Fusing', 'Fusing-Mini', 0.80, 'JNR', ['FUS']],
        'Fusing Collar & Cuff' => ['Preparation & Fusing', 'Fusing-Large', 0.70, 'JNR', ['FUS']],
        'Join Shoulder Seams' => ['Assembly', '4Overlock Machines [514]', 0.35, 'STD', ['OVL']],
        'Join Side Seams' => ['Assembly', '4Overlock Machines [514]', 0.50, 'STD', ['OVL']],
        'Join Front to Back Panel' => ['Assembly', 'Single Needle Lock Stitch [301]', 0.90, 'STD', ['SNLS']],
        'Cup Assembly' => ['Assembly', 'Zig Zag - 1Step [304]', 1.90, 'SNR', ['SNLS']],
        'Band Attach' => ['Assembly', 'Zig Zag - 3Step [315]', 1.10, 'SNR', ['ELS']],
        'Neckline Seam' => ['Seaming', '3N-Cover Stitch Small Cylinder [407]', 0.60, 'SNR', ['FLK']],
        'Armhole Seam' => ['Seaming', '4Overlock Machines [514]', 0.55, 'STD', ['OVL']],
        'Inseam Sewing' => ['Seaming', '5Overlock Machines [516]', 0.65, 'STD', ['OVL']],
        'Outseam Sewing' => ['Seaming', '5Overlock Machines [516]', 0.75, 'STD', ['OVL']],
        'Crotch Seam' => ['Seaming', '4N-Flat Seam [607]', 0.55, 'SNR', ['FLK']],
        'Sleeve Hem' => ['Hemming & Finishing', '2N-Cover Stitch RH Cutter [406]', 0.45, 'STD', ['FLK']],
        'Bottom Hem' => ['Hemming & Finishing', '2N-Cover Stitch LH Cutter Small Cylinder [406]', 0.50, 'JNR', ['FLK']],
        'Leg Hem' => ['Hemming & Finishing', '3N-Cover Stitch Cylinder Bed', 0.55, 'STD', ['FLK']],
        'Waist Hem' => ['Hemming & Finishing', '3N-Cover Stitch Flat Bed', 0.60, 'STD', ['FLK']],
        'Neck Hem' => ['Hemming & Finishing', '2N-Cover Stitch Binding [406]', 0.40, 'STD', ['FLK']],
        'Label Attach' => ['Attachment', 'Single Needle Lock Stitch [301]', 0.30, 'JNR', ['LBL', 'SNLS']],
        'Elastic Attach Waist' => ['Attachment', 'Multi Needle Chain Stitch [401]', 0.85, 'SNR', ['ELS']],
        'Strap Attach' => ['Attachment', 'Zig Zag - 1Step [304]', 0.65, 'STD', ['ELS']],
        'Collar Attach' => ['Attachment', 'Single Needle Lock Stitch [301]', 1.40, 'EXP', ['SNLS']],
        'Cuff Attach' => ['Attachment', 'Single Needle Lock Stitch [301]', 1.00, 'SNR', ['SNLS']],
        'Placket Make' => ['Attachment', 'Auto Placket Setter [304]', 1.20, 'SNR', ['SNLS']],
        'Pocket Attach' => ['Attachment', 'Auto Pocket Setter [304]', 0.80, 'STD', ['SNLS']],
        'Button Attach' => ['Attachment', 'Button Sew [304]', 0.35, 'JNR', ['BTA']],
        'Buttonhole Making' => ['Attachment', 'Button Hole [304]', 0.30, 'JNR', ['BTH']],
        'Bartack' => ['Attachment', 'Bartack [304]', 0.25, 'JNR', ['BTK']],
        'Hook and Eye Attach' => ['Attachment', 'Single Needle Lock Stitch [301]', 0.50, 'SNR', ['SNLS']],
        'Seam Pressing' => ['Pressing', 'Iron Electric', 0.45, 'JNR', ['PRS']],
        'Final Pressing' => ['Pressing', 'Iron Electric', 0.60, 'STD', ['PRS']],
        'Heat Seal Label' => ['Pressing', 'Heat Seal Single Head', 0.30, 'JNR', ['LBL']],
        'Fold & Pack' => ['Quality & Inspection', 'Shirt Folding Machines', 0.45, 'JNR', ['PKG', 'QCI']],
    ];

    /** Product category (exact name in product_categories) => [base operation => SMV multiplier], in routing order. */
    public const RECIPES = [
        'Tee shirt' => [
            'Join Shoulder Seams' => 1, 'Neckline Seam' => 1, 'Armhole Seam' => 1, 'Join Side Seams' => 1, 'Sleeve Hem' => 1,
            'Bottom Hem' => 1, 'Neck Hem' => 1, 'Label Attach' => 1, 'Bartack' => 0.8, 'Seam Pressing' => 1, 'Final Pressing' => 1, 'Fold & Pack' => 1,
        ],
        'Polo tee' => [
            'Fusing Collar & Cuff' => 1, 'Join Shoulder Seams' => 1.1, 'Collar Attach' => 1, 'Placket Make' => 1, 'Buttonhole Making' => 1,
            'Button Attach' => 1, 'Armhole Seam' => 1.1, 'Join Side Seams' => 1.1, 'Sleeve Hem' => 1.1, 'Bottom Hem' => 1.1, 'Label Attach' => 1,
            'Bartack' => 1, 'Final Pressing' => 1.2, 'Fold & Pack' => 1,
        ],
        'Active bottom' => [
            'Outseam Sewing' => 1, 'Inseam Sewing' => 1, 'Crotch Seam' => 1, 'Elastic Attach Waist' => 1.2, 'Waist Hem' => 1, 'Leg Hem' => 1,
            'Label Attach' => 1, 'Heat Seal Label' => 1, 'Bartack' => 1, 'Seam Pressing' => 1, 'Final Pressing' => 1, 'Fold & Pack' => 1,
        ],
        'Bra' => [
            'Fusing Front Panel' => 1, 'Cup Assembly' => 1, 'Band Attach' => 1, 'Strap Attach' => 1, 'Elastic Attach Waist' => 0.8,
            'Hook and Eye Attach' => 1, 'Join Front to Back Panel' => 0.8, 'Label Attach' => 1, 'Bartack' => 1, 'Heat Seal Label' => 1,
            'Final Pressing' => 1, 'Fold & Pack' => 1.2,
        ],
        'Shirt' => [
            'Fusing Collar & Cuff' => 1, 'Collar Attach' => 1.1, 'Cuff Attach' => 1.1, 'Placket Make' => 1, 'Pocket Attach' => 1,
            'Join Front to Back Panel' => 1, 'Armhole Seam' => 1.2, 'Join Side Seams' => 1.2, 'Buttonhole Making' => 1, 'Button Attach' => 1.2,
            'Bottom Hem' => 1.2, 'Sleeve Hem' => 1.2, 'Label Attach' => 1, 'Final Pressing' => 1.6, 'Fold & Pack' => 1.2,
        ],
        'Short' => [
            'Outseam Sewing' => 0.8, 'Inseam Sewing' => 0.8, 'Crotch Seam' => 0.9, 'Waist Hem' => 1, 'Elastic Attach Waist' => 1, 'Leg Hem' => 0.8,
            'Pocket Attach' => 1, 'Label Attach' => 1, 'Bartack' => 1, 'Final Pressing' => 1, 'Fold & Pack' => 1,
        ],
        'PJ-top' => [
            'Join Shoulder Seams' => 1, 'Collar Attach' => 0.9, 'Placket Make' => 0.9, 'Buttonhole Making' => 1, 'Button Attach' => 1,
            'Armhole Seam' => 1, 'Join Side Seams' => 1, 'Sleeve Hem' => 1, 'Bottom Hem' => 1, 'Label Attach' => 1, 'Final Pressing' => 1, 'Fold & Pack' => 1,
        ],
    ];

    /** [name, customer code, season name, product category, factory codes] */
    public const STYLES = [
        ['Levis Classic Crew Tee', 'LEVIS', 'SP27', 'Tee shirt', ['AXN2']],
        ['Levis Heritage Polo', 'LEVIS', 'SP27', 'Polo tee', ['AXN2', 'AXN3']],
        ['Levis Oxford Shirt', 'LEVIS', 'SP27', 'Shirt', ['AXN3']],
        ['VS Everyday Bra', 'VS', 'SU27', 'Bra', ['AXN2']],
        ['VS Seamless Bralette', 'VS', 'Spring 2027', 'Bra', ['AXN2', 'AXN3']],
        ['Lululemon Align Legging', 'LL', 'WI27', 'Active bottom', ['AXN2']],
        ['Lululemon Pace Short', 'LL', 'FA27', 'Short', ['AXN2', 'AXN3']],
        ['Nike Dri-FIT Tee', 'NIKE', 'SP27', 'Tee shirt', ['AXN2', 'AXN3']],
        ['Nike Flex Short', 'NIKE', 'SP27', 'Short', ['AXN2']],
        ['Walmart Basic Polo', 'WM', 'FA27', 'Polo tee', ['AXN2', 'AXN3']],
        ['Walmart Sleep Top', 'WM', 'FA27', 'PJ-top', ['AXN2']],
        ['Walmart Everyday Tee', 'WM', 'FA27', 'Tee shirt', ['AXN3']],
    ];

    /** Seasons the dataset adds for customers that have none (customer code => season names). */
    public const EXTRA_SEASONS = ['NIKE' => ['SP27'], 'WM' => ['FA27']];

    /** team code => product categories that line mostly runs. Egypt's teams are created by the builder. */
    public const TEAM_FOCUS = [
        'T01' => ['Tee shirt', 'Short'],
        'T02' => ['Tee shirt', 'Polo tee'],
        'T03' => ['Polo tee', 'PJ-top'],
        'T04' => ['Active bottom', 'Short'],
        'T05' => ['Active bottom'],
        'T06' => ['Bra'],
        'T07' => ['Bra'],
        'T08' => ['Shirt', 'Polo tee'],
        'T09' => ['Shirt'],
        'DM-E1' => ['Polo tee', 'Tee shirt'],
        'DM-E2' => ['Short', 'Shirt'],
    ];

    /** code => name */
    public const DOWNTIME_REASONS = [
        'CHECKING' => 'Checking',
        'THREAD_BREAK' => 'Thread Break',
        'BOBBIN_CHANGE' => 'Bobbin Change',
        'BUNDLE_HANDLING' => 'Bundle Handling',
        'RECORD_AND_TICK' => 'Record & Tick',
        'REPAIR' => 'Repair',
        'INSTRUCTIONS' => 'Instructions',
        'MACHINE_BREAKDOWN' => 'Machine Breakdown',
        'WAITING_FOR_WORK' => 'Waiting For Work',
        'OTHERS' => 'Others',
    ];

    /** Weights for picking a reason (same order as DOWNTIME_REASONS). */
    public const DOWNTIME_WEIGHTS = [10, 18, 14, 16, 6, 8, 6, 4, 10, 8];

    public const FABRICS = ['Single jersey', 'Pique', 'Interlock', 'Woven poplin', 'Spandex jersey'];
}
