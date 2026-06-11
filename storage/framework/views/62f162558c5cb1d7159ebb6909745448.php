<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

<head>
    <style>
        * {
            margin: 0;
            padding: 0;
        }

        body {
            font-family: sans-serif;
            margin: 6px;
        }

        .barcode-img {
            max-width: 100%;
            height: 50px;
        }

        .outer-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 5px 5px;
        }

        .sticker-td {
            width: 50%;
            max-width: 50%;
            vertical-align: top;
        }

        .sticker {
            border: 2px solid #000;
            width: 100%;
            border-collapse: collapse;
        }

        .top-info {
            vertical-align: middle;
            padding: 5px 8px;
            width: 75%;
            max-width: 75%;
            border-right: 1px solid #555;
            height: 55px;
        }

        .top-qr {
            vertical-align: middle;
            text-align: center;
            width: 25%;
            height: 55px;
            padding: 3px;
        }

        .bottom-barcode {
            text-align: center;
            border-top: 2px solid #000;
            padding: 3px 3px 2px;
            height: 50px;
        }

        .rack-text {
            font-size: 12px;
            color: #000427;
            margin-bottom: 3px;
            font-weight: bold;
        }

        .bin-text {
            font-size: 18px;
            font-weight: bold;
        }

        .bin-human-text {
            font-size: 12px;
            font-weight: bold;
            margin-top: 3px;
            text-align: center;
        }
    </style>
</head>

<body>
    <?php $colCount = 0; $stickerCount = 0; $prevRack = null; ?>
    <table class="outer-table" cellspacing="5" cellpadding="0">
        <?php $__currentLoopData = $locations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $location): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php
        $rackChanged = $prevRack !== null && ($location->rack ?? '') !== $prevRack;
        $prevRack = $location->rack ?? '';
        ?>
        <?php if($rackChanged): ?>
        <?php if($colCount == 1): ?>
        <td class="sticker-td"></td>
        </tr>
        <?php endif; ?>
    </table>
    <div style="page-break-before: always;"></div>
    <table class="outer-table" cellspacing="5" cellpadding="0">
        <?php $colCount = 0; $stickerCount = 0; ?>
        <?php endif; ?>
        <?php $colCount++; $stickerCount++; ?>
        <?php if($colCount == 1): ?>
        <tr>
            <?php endif; ?>

            <td class="sticker-td">
                <table class="sticker" cellspacing="0" cellpadding="0">
                    <tr>
                        <td class="top-info">
                            <div class="rack-text"><?php echo e($location->rack ?? ''); ?></div>
                            <div class="bin-text"><?php echo e($location->bin ?? ''); ?><?php if($location->stockMaterial): ?> | <?php echo e($location->stockMaterial->code); ?> | <?php echo e(\Illuminate\Support\Str::limit($location->stockMaterial->name, 15, '...')); ?><?php endif; ?></div>
                        </td>
                        <td class="top-qr">
                            
                            <img src="data:image/png;base64, <?php echo base64_encode(QrCode::format('svg')->size(45)->generate($location->id ?? '')); ?> ">

                        </td>
                    </tr>
                    <tr>
                        <td colspan="2" class="bottom-barcode">
                            <?php
                            $generator = new \Picqer\Barcode\BarcodeGeneratorPNG();
                            $barcodePng = base64_encode($generator->getBarcode($location->id ?? '', $generator::TYPE_CODE_39, 2, 38));
                            ?>
                            <img class="barcode-img" src="data:image/png;base64,<?php echo e($barcodePng); ?>">
                            <div class="bin-human-text"><?php echo e($location->id ?? ''); ?></div>
                        </td>
                    </tr>
                </table>
            </td>

            <?php if($colCount == 2): ?>
        </tr>
        <?php $colCount = 0; ?>
        <?php endif; ?>

        <?php if($stickerCount == 12 && !$loop->last): ?>
        <?php if($colCount == 1): ?>
        <td class="sticker-td"></td>
        </tr>
        <?php endif; ?>
    </table>
    <pagebreak style="page-break-before: always;" pagebreak="true"></pagebreak>
    <table class="outer-table" cellspacing="5" cellpadding="0">
        <?php $colCount = 0; $stickerCount = 0; $prevRack = null; ?>
        <?php endif; ?>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        <?php if($colCount == 1): ?>
        <td class="sticker-td"></td>
        </tr>
        <?php elseif($colCount == 0 && $stickerCount > 0): ?>
        
        <?php endif; ?>
    </table>
</body>

</html><?php /**PATH D:\laragon\www\sfxbackend\resources\views/print/warehouse_location_stickers.blade.php ENDPATH**/ ?>