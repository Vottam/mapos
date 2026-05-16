<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Etiqueta Produto #<?= (int) $produto->idProdutos ?></title>
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
            font-family: Arial, sans-serif;
            background: #fff;
        }

        .label {
            width: 100%;
            height: 100%;
            box-sizing: border-box;
            padding: 1.4mm 1.3mm 1.2mm;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: center;
        }

        .descricao {
            font-size: 6pt;
            line-height: 1.05;
            font-weight: 700;
            text-transform: uppercase;
            max-height: 5.5mm;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .meta {
            display: flex;
            justify-content: space-between;
            gap: 1mm;
            font-size: 5pt;
            line-height: 1;
            white-space: nowrap;
        }

        .meta span {
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .barcode-wrap {
            width: 100%;
            height: 9.5mm;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        svg {
            width: 100%;
            height: 100%;
        }
    </style>
</head>
<body onload="window.print()">
    <?php
        $codigoBarra = trim((string) ($produto->codDeBarra ?? ''));
        if ($codigoBarra === '') {
            $codigoBarra = 'P-' . $produto->idProdutos;
        }

        $descricao = trim((string) ($produto->descricao ?? ''));
        $precoVenda = isset($produto->precoVenda) ? (float) $produto->precoVenda : 0.0;
        $temPreco = $precoVenda > 0;
    ?>
    <div class="label">
        <div class="descricao"><?= html_escape($descricao !== '' ? $descricao : 'PRODUTO') ?></div>

        <div class="meta">
            <span>#<?= (int) $produto->idProdutos ?></span>
            <span><?= html_escape($codigoBarra) ?></span>
            <span><?= $temPreco ? 'R$ ' . number_format($precoVenda, 2, ',', '.') : '&nbsp;' ?></span>
        </div>

        <div class="barcode-wrap">
            <svg id="barcode"></svg>
        </div>

        <div class="meta">
            <span><?= html_escape($produto->unidade ?? '') ?></span>
            <span><?= !empty($produto->estoque) ? 'Estoque: ' . (int) $produto->estoque : '&nbsp;' ?></span>
            <span>&nbsp;</span>
        </div>
    </div>

    <script>
        JsBarcode("#barcode", <?= json_encode($codigoBarra, JSON_UNESCAPED_UNICODE) ?>, {
            format: "CODE128",
            width: 1.15,
            height: 24,
            displayValue: false,
            margin: 0
        });
    </script>
</body>
</html>
