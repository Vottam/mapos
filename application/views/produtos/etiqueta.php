<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Etiqueta Produto #<?= (int) $produto->idProdutos ?></title>
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs2@0.0.2/qrcode.min.js"></script>
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
            font-family: Arial, sans-serif;
            background: #fff;
        }

        * {
            box-sizing: border-box;
        }

        .label {
            width: 40mm;
            height: 25mm;
            padding: 1mm 1.15mm 0.95mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            color: #000;
        }

        .descricao {
            font-size: 5.6pt;
            line-height: 1.02;
            font-weight: 700;
            text-align: center;
            text-transform: uppercase;
            max-height: 6.3mm;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            word-break: break-word;
        }

        .middle {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.9mm;
            height: 10.2mm;
            min-height: 10.2mm;
        }

        .qr-box {
            width: 13.2mm;
            height: 13.2mm;
            flex: 0 0 13.2mm;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #fff;
        }

        .qr-box > div {
            width: 100% !important;
            height: 100% !important;
        }

        .qr-box img,
        .qr-box canvas {
            width: 100% !important;
            height: 100% !important;
        }

        .price-box {
            flex: 1 1 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            min-height: 100%;
            padding-left: 0.5mm;
        }

        .price {
            font-size: 8.9pt;
            line-height: 0.95;
            font-weight: 800;
            letter-spacing: -0.35px;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .bottom {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 3.3mm;
            font-size: 6.8pt;
            line-height: 1;
            font-weight: 700;
            letter-spacing: 0.35px;
        }

        .id-code {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <?php
        $descricao = trim((string) ($produto->descricao ?? ''));
        $precoVenda = isset($produto->precoVenda) ? (float) $produto->precoVenda : 0.0;
        $codigoInferior = '#' . str_pad((string) ((int) $produto->idProdutos), 4, '0', STR_PAD_LEFT);
        $qrPayload = 'PROD:' . str_pad((string) ((int) $produto->idProdutos), 4, '0', STR_PAD_LEFT);
    ?>
    <div class="label">
        <div class="descricao"><?= html_escape($descricao !== '' ? $descricao : 'PRODUTO') ?></div>

        <div class="middle">
            <div class="qr-box">
                <div id="qrcode"></div>
            </div>
            <div class="price-box">
                <div class="price">R$ <?= number_format($precoVenda, 2, ',', '.') ?></div>
            </div>
        </div>

        <div class="bottom">
            <span class="id-code"><?= html_escape($codigoInferior) ?></span>
        </div>
    </div>

    <script>
        window.addEventListener('load', function () {
            var payload = <?= json_encode($qrPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            var target = document.getElementById('qrcode');

            new QRCode(target, {
                text: payload,
                width: 52,
                height: 52,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });

            setTimeout(function () {
                window.print();
            }, 180);
        });
    </script>
</body>
</html>
