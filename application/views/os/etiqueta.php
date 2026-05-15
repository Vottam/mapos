<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Etiqueta OS <?= $os->idOs ?></title>

<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>

<style>
@page {
    size: 40mm 25mm;
    margin: 0;
}

body {
    margin: 0;
    padding: 0;
    width: 40mm;
    height: 25mm;
    font-family: Arial, sans-serif;
}

.container {
    width: 100%;
    height: 100%;
    padding: 2mm;
    box-sizing: border-box;
    text-align: center;
}

.os-numero {
    font-size: 13pt;
    font-weight: bold;
    margin-bottom: 1mm;
}

svg {
    width: 100%;
    height: 10mm;
}

.cliente {
    font-size: 7pt;
    margin-top: 1mm;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
</style>
</head>

<body onload="window.print()">

<div class="container">

    <div class="os-numero">
        OS #<?= $os->idOs ?>
    </div>

    <svg id="barcode"></svg>

    <div class="cliente">
        <?= htmlspecialchars($os->nomeCliente ?? '') ?>
    </div>

</div>

<script>
JsBarcode("#barcode", "MT-OS-<?= $os->idOs ?>", {
    format: "CODE128",
    width: 1.4,
    height: 35,
    displayValue: false,
    margin: 0
});
</script>

</body>
</html>
