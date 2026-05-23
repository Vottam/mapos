<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Etiqueta Serial OS #<?= (int) $os->idOs ?></title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <style>
        @page {
            size: 40mm 25mm;
            margin: 0;
        }

        html, body {
            margin: 0;
            padding: 0;
            width: 40mm;
            height: 25mm;
            overflow: hidden;
            background: #fff;
            font-family: Arial, sans-serif;
            color: #000;
        }

        * {
            box-sizing: border-box;
        }

        .label {
            width: 40mm;
            height: 25mm;
            padding: 1.2mm 1.2mm 1mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.8mm;
        }

        .barcode-wrap {
            width: 100%;
            height: 12.5mm;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        #barcode {
            width: 100%;
            height: 100%;
            display: block;
        }

        .serial {
            width: 100%;
            text-align: center;
            font-size: 7pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 0.25px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .mensagem {
            width: 100%;
            text-align: center;
            font-size: 7pt;
            line-height: 1.15;
            font-weight: 700;
            padding: 2mm 1mm;
        }
    </style>
</head>
<body>
<?php
    $serial = trim((string) ($serial ?? ''));
?>
<?php if ($serial !== '') : ?>
    <div class="label">
        <div class="barcode-wrap">
            <svg id="barcode"></svg>
        </div>
        <div class="serial"><?= html_escape($serial) ?></div>
    </div>

    <script>
        window.addEventListener('load', function () {
            JsBarcode('#barcode', <?= json_encode($serial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>, {
                format: 'CODE128',
                width: 1.15,
                height: 28,
                displayValue: false,
                margin: 0,
                lineColor: '#000000',
                background: '#ffffff'
            });

            setTimeout(function () {
                window.print();
            }, 150);
        });
    </script>
<?php else : ?>
    <div class="label">
        <div class="mensagem">Esta OS ainda não possui número de série.</div>
    </div>
<?php endif; ?>
</body>
</html>
