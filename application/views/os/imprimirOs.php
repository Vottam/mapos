<?php
$totalServico  = 0;
$totalProdutos = 0;
$garantiaAte = ($result->garantia_modelo ?? null) === 'por_item'
    ? null
    : osVencimentoGarantia($result->dataFinal, $result->garantia, $result->status, $result->dataEntrega ?? null);
$logoConfigurado = $emitente->url_logo ?? null;
$logoConfiguradoLocal = $logoConfigurado ? convertUrlToUploadsPath($logoConfigurado) : null;
$logoFallback = FCPATH . 'assets/img/mastertrend-logo.png';
// mPDF bloqueia requisições HTTP(S): priorize o upload local configurado e use
// a cópia local verificada da mesma marca quando o staging só tem a URL pública.
$logoImpressao = is_string($logoConfiguradoLocal)
    && is_file($logoConfiguradoLocal)
    && is_readable($logoConfiguradoLocal)
    ? $logoConfiguradoLocal
    : (is_file($logoFallback) && is_readable($logoFallback) ? $logoFallback : '');
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <title><?= $this->config->item('app_name') ?> - <?= $result->idOs ?> - <?= $result->nomeCliente ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <style>
        html, body { width: auto; height: auto; min-height: 0; margin: 0; padding: 0; background: #fff; }
        body { font-family: DejaVuSans, sans-serif; font-size: 8.5pt; line-height: 1.18; color: #1f2937; }
        header { display: table; table-layout: fixed; width: 100%; margin: 0 0 1mm; padding: 0 0 1mm; border: 0; border-bottom: 1px solid #cbd5e1; }
        table { width: 100%; margin: 0; border-collapse: collapse; border-spacing: 0; table-layout: fixed; font-family: DejaVuSans, sans-serif; font-size: 8pt; page-break-inside: auto; }
        thead { display: table-header-group; }
        th, td { padding: 0.8mm 1mm; border: 0.25mm solid #94a3b8; vertical-align: top; }
        th { background-color: #e2e8f0; color: #0f172a; font-size: 7.5pt; font-weight: bold; }
        tr { page-break-inside: avoid; page-break-after: auto; }
        footer { width: 100%; margin-top: 0; padding: 0; border: 0; page-break-before: avoid; page-break-inside: auto; }
        p { margin: 0 0 1mm; }
    </style>
</head>
<body class="os-document" style="font-family:DejaVuSans,sans-serif;font-size:8.5pt;line-height:1.18;color:#1f2937;">
    <div class="main-page">
        <div class="sub-page">
            <header style="display:table;table-layout:fixed;width:100%;margin:0 0 1mm;padding:0 0 1mm;border:0;border-bottom:1px solid #cbd5e1;">
                <?php if ($emitente == null) : ?>
                    <div class="alert alert-danger" role="alert">
                        Você precisa configurar os dados do emitente. >>> <a href="<?=base_url()?>index.php/mapos/emitente">Configurar</a>
                    </div>
                <?php else : ?>
                    <div class="imgLogo align-middle" style="display:table-cell;width:22%;padding-right:2mm;vertical-align:middle;">
                        <?php if ($logoImpressao !== '') : ?><img src="<?= html_escape($logoImpressao) ?>" alt="Logotipo MasterTrend" class="img-fluid" style="width:44mm;max-height:28mm;height:auto;"><?php endif; ?>
                    </div>
                    <div class="emitente" style="display:table-cell;width:44%;padding:0 2mm;vertical-align:middle;font-size:8pt;line-height:1.2;">
                        <span style="font-size: 15px;"><b><?= $emitente->nome ?></b></span><br>
                        <?php if ($emitente->cnpj != "00.000.000/0000-00") : ?>
                            <span class="align-middle">CNPJ: <?= $emitente->cnpj ?></span><br>
                        <?php endif; ?>
                        <span class="align-middle">
                            <?= $emitente->rua.', '.$emitente->numero.', '.$emitente->bairro ?><br>
                            <?= $emitente->cidade.' - '.$emitente->uf.' - '.$emitente->cep ?>
                        </span>
                    </div>
                    <div class="contatoEmitente" style="display:table-cell;width:34%;padding:0;vertical-align:middle;text-align:right;font-size:8pt;line-height:1.2;">
                        <span style="font-weight: bold;">Tel: <?= $emitente->telefone ?></span><br>
                        <span style="font-weight: bold;"><?= $emitente->email ?></span><br>
                        <span style="word-break: break-word;">Responsável: <b><?= $result->nome ?></b></span>
                    </div>
                <?php endif; ?>
            </header>
            <section>
                <div class="title" style="display:block;width:100%;margin:2mm 0;padding:1mm 0;border-bottom:2px solid #f97316;color:#111827;font-family:DejaVuSans,sans-serif;font-size:15pt;line-height:1.15;font-weight:bold;text-align:left;">
                    <?php if ($configuration['control_2vias']) : ?><span class="via" style="display:inline-block;margin-right:3mm;color:#ea580c;font-size:8pt;font-weight:600;">Via cliente</span><?php endif; ?>
                    ORDEM DE SERVIÇO #<?= str_pad($result->idOs, 4, 0, STR_PAD_LEFT) ?>
                    <span class="emissao" style="float:right;padding-top:1mm;color:#475569;font-size:8pt;font-weight:normal;">Emissão: <?= date('d/m/Y H:i:s') ?></span>
                </div>

                <?php if ($result->dataInicial != null): ?>
                    <div class="tabela" style="width:100%;margin:1mm 0 0;padding:0;page-break-inside:auto;">
                        <table class="table table-bordered">
                            <thead>
                                <tr class="table-secondary">
                                    <th class="text-center" style="text-align:center;">STATUS</th>
                                    <th class="text-center" style="text-align:center;">DATA INICIAL</th>
                                    <th class="text-center" style="text-align:center;">DATA FINAL</th>
                                    <?php if (($result->garantia_modelo ?? null) !== 'por_item' && $result->garantia) : ?>
                                        <th class="text-center" style="text-align:center;">GARANTIA</th>
                                    <?php endif; ?>
                                    <?php if (($result->garantia_modelo ?? null) !== 'por_item' && in_array($result->status, ['Finalizado', 'Faturado', 'Entregue'])) : ?>
                                        <th class="text-center" style="text-align:center;">VENC. GARANTIA</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td class="text-center" style="text-align:center;"><?= $result->status ?></td>
                                    <td class="text-center" style="text-align:center;"><?= date('d/m/Y', strtotime($result->dataInicial)) ?></td>
                                    <td class="text-center" style="text-align:center;"><?= $result->dataFinal ? date('d/m/Y', strtotime($result->dataFinal)) : '' ?></td>
                                    <?php if (($result->garantia_modelo ?? null) !== 'por_item' && $result->garantia) : ?>
                                        <td class="text-center" style="text-align:center;"><?= $result->garantia . ' dia(s)' ?></td>
                                    <?php endif; ?>
                                    <?php if (($result->garantia_modelo ?? null) !== 'por_item' && in_array($result->status, ['Finalizado', 'Faturado', 'Entregue'])) : ?>
                                        <td class="text-center" style="text-align:center;"><?= $garantiaAte ? date('d/m/Y', strtotime($garantiaAte)) : '' ?></td>
                                    <?php endif; ?>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">DADOS DO CLIENTE</div>
                <div class="dados dados-cliente" style="display:table;width:100%;table-layout:fixed;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                    <div style="display:table-cell;width:52%;padding-right:2mm;vertical-align:top;text-align:left;">
                        <span><b><?= $result->nomeCliente ?></b></span><br />
                        <span>CPF/CNPJ: <?= $result->documento ?></span><br />
                        <span>Tel: <?= $result->contato_cliente ? $result->contato_cliente.' / ' : '' ?><?= $result->telefone ?><?= $result->celular ? ' / '.$result->celular : '' ?></span><br />
                        <span><?= $result->email ?></span><br />
                    </div>
                    <div style="display:table-cell;width:48%;padding-left:2mm;vertical-align:top;text-align:left;">
                        <span><b><?= $result->rua.', '.$result->numero ?></b></span><br />
                        <span><?= $result->bairro ?></span><br />
                        <span><?= $result->cidade.' / '.$result->estado ?><?= $result->complemento ? ' — '.$result->complemento : '' ?></span><br />
                        <span>CEP: <?= $result->cep ?></span><br />
                    </div>
                </div>

                <?php if ($result->descricaoProduto) : ?>
                    <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">DESCRIÇÃO</div>
                    <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                        <div style="text-align: justify;">
                            <?= printSafeHtml($result->descricaoProduto) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($result->defeito) : ?>
                    <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">DEFEITO APRESENTADO</div>
                    <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                        <div style="text-align: justify;">
                            <?= printSafeHtml($result->defeito) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($result->observacoes) : ?>
                    <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">OBSERVAÇÕES</div>
                    <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                        <div style="text-align: justify;">
                            <?= printSafeHtml($result->observacoes) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($result->laudoTecnico) : ?>
					<div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">PARECER TÉCNICO</div>
                    <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                        <div style="text-align: justify;">
    						<?= printSafeHtml($result->laudoTecnico) ?>
						</div>
                    </div>
                <?php endif; ?>

                <?php if (($result->garantia_modelo ?? null) !== 'por_item' && $result->garantias_id) : ?>
                    <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">TERMO DE GARANTIA</div>
                    <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                        <div style="text-align: justify;"><?= printSafeHtml($result->textoGarantia) ?></div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($equipamento)) : ?>
                    <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">EQUIPAMENTO</div>
                    <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                        <div>
                            <span>Tipo: <b><?= !empty($equipamento->equipamento) ? html_escape($equipamento->equipamento) : 'Não informado' ?></b></span><br />
                            <span>Marca: <b><?= !empty($equipamento->marca_nome) ? html_escape($equipamento->marca_nome) : 'Não informado' ?></b></span><br />
                            <span>Modelo: <b><?= !empty($equipamento->modelo) ? html_escape($equipamento->modelo) : 'Não informado' ?></b></span><br />
                            <span>Número de série: <b><?= !empty($equipamento->num_serie) ? html_escape($equipamento->num_serie) : 'Não informado' ?></b></span><br />
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($produtos) : ?>
                    <div class="tabela" style="width:100%;margin:1mm 0 0;padding:0;page-break-inside:auto;">
                        <table class="table table-bordered">
                            <thead>
                                <tr class="table-secondary">
                                    <th>PRODUTO(S)</th>
                                    <th class="text-center" style="text-align:center;" width="10%">QTD</th>
                                    <th class="text-center" style="text-align:center;" width="10%">VALOR UNIT.</th>
                                    <?php if (($result->garantia_modelo ?? null) === 'por_item') : ?>
                                        <th>GARANTIA</th>
                                    <?php endif; ?>
                                    <th class="text-end" style="text-align:right;" width="15%" >SUBTOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($produtos as $p) :
                                    $subtotal = osValorDoItem($p);
                                    $totalProdutos = $totalProdutos + $subtotal;
                                    echo '<tr>';
                                    echo '  <td>' . $p->descricao . '</td>';
                                    echo '  <td class="text-center" style="text-align:center;">' . $p->quantidade . '</td>';
                                    echo '  <td class="text-center" style="text-align:center;">' . number_format($p->preco ?: $p->precoVenda, 2, ',', '.') . '</td>';
                                    if (($result->garantia_modelo ?? null) === 'por_item') {
                                        echo '  <td>' . garantiaHtmlItemImpressao($p) . '</td>';
                                    }
                                    echo '  <td class="text-end" style="text-align:right;">R$ ' . number_format($subtotal, 2, ',', '.') . '</td>';
                                    echo '</tr>';
                                endforeach; ?>
                                <tr>
                                    <td colspan="<?= ($result->garantia_modelo ?? null) === 'por_item' ? 4 : 3 ?>" class="text-end" style="text-align:right;"><b>TOTAL PRODUTOS:</b></td>
                                    <td class="text-end" style="text-align:right;"><b>R$ <?= number_format($totalProdutos, 2, ',', '.') ?></b></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>

                <?php if ($servicos) : ?>
                    <div class="tabela" style="width:100%;margin:1mm 0 0;padding:0;page-break-inside:auto;">
                        <table class="table table-bordered">
                            <thead>
                                <tr class="table-secondary">
                                    <th>SERVIÇO(S)</th>
                                    <th class="text-center" style="text-align:center;" width="10%">QTD</th>
                                    <th class="text-center" style="text-align:center;" width="10%">VALOR UNIT.</th>
                                    <?php if (($result->garantia_modelo ?? null) === 'por_item') : ?>
                                        <th>GARANTIA</th>
                                    <?php endif; ?>
                                    <th class="text-end" style="text-align:right;" width="15%" >SUBTOTAL</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                    setlocale(LC_MONETARY, 'en_US');
                    foreach ($servicos as $s) :
                        $preco = $s->preco ?: $s->precoVenda;
                        $subtotal = osValorDoItem($s);
                        $totalServico = $totalServico + $subtotal;
                        echo '<tr>';
                        echo '  <td>' . $s->nome . '</td>';
                        echo '  <td class="text-center" style="text-align:center;">' . ($s->quantidade ?: 1) . '</td>';
                        echo '  <td class="text-center" style="text-align:center;">' . number_format($preco, 2, ',', '.') . '</td>';
                        if (($result->garantia_modelo ?? null) === 'por_item') {
                            echo '  <td>' . garantiaHtmlItemImpressao($s) . '</td>';
                        }
                        echo '  <td class="text-end" style="text-align:right;">R$ ' . number_format($subtotal, 2, ',', '.') . '</td>';
                        echo '</tr>';
                    endforeach; ?>
                                <tr>
                                    <td colspan="<?= ($result->garantia_modelo ?? null) === 'por_item' ? 4 : 3 ?>" class="text-end" style="text-align:right;"><b>TOTAL SERVIÇOS:</b></td>
                                    <td class="text-end" style="text-align:right;"><b>R$ <?= number_format($totalServico, 2, ',', '.') ?></b></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>


                <?php $resumoValores = osTotais($totalProdutos, $totalServico, $result->valor_desconto ?? 0, $result->tipo_desconto ?? null, $result->desconto ?? 0); ?>
                <?php if ($totalProdutos != 0 || $totalServico != 0) : ?>
                    <?php $temPix = !empty($configuration['pix_key']); ?>
                    <div class="pagamento" style="display:table;width:100%;margin-top:1mm;border-collapse:collapse;page-break-inside:avoid;">
                        <?php if ($temPix) : ?>
                            <div class="qrcode" style="display:table-cell;width:30%;padding-right:3mm;vertical-align:top;text-align:center;">
                                <div><img width="130px" src="<?= $qrCode ?>" alt="QR Code de Pagamento" /></div>
                                <div>
                                    <div style="width:100%;text-align:center;">Escaneie o QRCode ao lado para pagar por Pix</div>
                                    <div class="chavePix">Chave Pix: <b><?= $chaveFormatada ?></b></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <div style="display:table-cell;width:<?= $temPix ? '70%' : '100%' ?>;vertical-align:top;">
                            <div class="tabela" style="width:100%;margin:0;padding:0;page-break-inside:avoid;">
                                <table class="table table-bordered">
                                    <thead>
                                        <tr class="table-secondary">
                                            <th colspan="2">RESUMO DOS VALORES</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="width:50%;">
                                                <div>PRODUTOS: <b>R$ <?= number_format($totalProdutos, 2, ',', '.') ?></b></div>
                                                <div>SERVIÇOS: <b>R$ <?= number_format($totalServico, 2, ',', '.') ?></b></div>
                                            </td>
                                            <td style="width:50%;">
                                                <div>DESCONTO: <b><?= $resumoValores['desconto'] > 0 ? '- ' : '' ?>R$ <?= number_format($resumoValores['desconto'], 2, ',', '.') ?></b></div>
                                                <div>TOTAL: <b>R$ <?= number_format($resumoValores['total'], 2, ',', '.') ?></b></div>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
            <footer>
                <table class="assinaturas" style="width:100%;margin-top:1mm;border-collapse:collapse;border:0;page-break-inside:avoid;">
                    <tbody>
                        <tr>
                            <td style="width:46%;padding:1mm 2mm 0;border:0;border-top:1px solid #64748b;text-align:center;font-size:7pt;">Assinatura do cliente</td>
                            <td style="width:8%;padding:0;border:0;"></td>
                            <td style="width:46%;padding:1mm 2mm 0;border:0;border-top:1px solid #64748b;text-align:center;font-size:7pt;">Assinatura do técnico</td>
                        </tr>
                    </tbody>
                </table>
            </footer>
        </div>

        <?php if ($configuration['control_2vias']) : ?>
            <div class="sub-page novaPagina" style="page-break-before:always;">
                <header style="display:table;table-layout:fixed;width:100%;margin:0 0 1mm;padding:0 0 1mm;border:0;border-bottom:1px solid #cbd5e1;">
                    <?php if ($emitente == null) : ?>
                        <div class="alert alert-danger" role="alert">
                            Você precisa configurar os dados do emitente. >>> <a href="<?=base_url()?>index.php/mapos/emitente">Configurar</a>
                        </div>
                    <?php else : ?>
                        <div class="imgLogo align-middle" style="display:table-cell;width:22%;padding-right:2mm;vertical-align:middle;">
                            <?php if ($logoImpressao !== '') : ?><img src="<?= html_escape($logoImpressao) ?>" alt="Logotipo MasterTrend" class="img-fluid" style="width:44mm;max-height:28mm;height:auto;"><?php endif; ?>
                        </div>
                        <div class="emitente" style="display:table-cell;width:44%;padding:0 2mm;vertical-align:middle;font-size:8pt;line-height:1.2;">
                            <span style="font-size: 15px;"><b><?= $emitente->nome ?></b></span><br>
                            <?php if ($emitente->cnpj != "00.000.000/0000-00") : ?>
                                <span class="align-middle">CNPJ: <?= $emitente->cnpj ?></span><br>
                            <?php endif; ?>
                            <span class="align-middle">
                                <?= $emitente->rua.', '.$emitente->numero.', '.$emitente->bairro ?><br>
                                <?= $emitente->cidade.' - '.$emitente->uf.' - '.$emitente->cep ?>
                            </span>
                        </div>
                        <div class="contatoEmitente" style="display:table-cell;width:34%;padding:0;vertical-align:middle;text-align:right;font-size:8pt;line-height:1.2;">
                            <span style="font-weight: bold;">Tel: <?= $emitente->telefone ?></span><br>
                            <span style="font-weight: bold;"><?= $emitente->email ?></span><br>
                            <span style="word-break: break-word;">Responsável: <b><?= $result->nome ?></b></span>
                        </div>
                    <?php endif; ?>
                </header>
                <section>
                    <div class="title" style="display:block;width:100%;margin:2mm 0;padding:1mm 0;border-bottom:2px solid #f97316;color:#111827;font-family:DejaVuSans,sans-serif;font-size:15pt;line-height:1.15;font-weight:bold;text-align:left;">
                        <!-- VIA EMPRESA  -->
                        <?php $totalServico = 0;
$totalProdutos = 0; ?>
                        <?php if ($configuration['control_2vias']) : ?><span class="via" style="display:inline-block;margin-right:3mm;color:#ea580c;font-size:8pt;font-weight:600;">Via Empresa</span><?php endif; ?>
                        ORDEM DE SERVIÇO #<?= str_pad($result->idOs, 4, 0, STR_PAD_LEFT) ?>
                        <span class="emissao" style="float:right;padding-top:1mm;color:#475569;font-size:8pt;font-weight:normal;">Emissão: <?= date('d/m/Y') ?></span>
                    </div>

                    <?php if ($result->dataInicial != null): ?>
                        <div class="tabela" style="width:100%;margin:1mm 0 0;padding:0;page-break-inside:auto;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr class="table-secondary">
                                        <th class="text-center" style="text-align:center;">STATUS</th>
                                        <th class="text-center" style="text-align:center;">DATA INICIAL</th>
                                        <th class="text-center" style="text-align:center;">DATA FINAL</th>
                                        <?php if (($result->garantia_modelo ?? null) !== 'por_item' && $result->garantia) : ?>
                                            <th class="text-center" style="text-align:center;">GARANTIA</th>
                                        <?php endif; ?>
                                        <?php if (($result->garantia_modelo ?? null) !== 'por_item' && in_array($result->status, ['Finalizado', 'Faturado', 'Entregue'])) : ?>
                                            <th class="text-center" style="text-align:center;">VENC. GARANTIA</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td class="text-center" style="text-align:center;"><?= $result->status ?></td>
                                        <td class="text-center" style="text-align:center;"><?= date('d/m/Y', strtotime($result->dataInicial)) ?></td>
                                        <td class="text-center" style="text-align:center;"><?= $result->dataFinal ? date('d/m/Y', strtotime($result->dataFinal)) : '' ?></td>
                                        <?php if (($result->garantia_modelo ?? null) !== 'por_item' && $result->garantia) : ?>
                                            <td class="text-center" style="text-align:center;"><?= $result->garantia . ' dia(s)' ?></td>
                                        <?php endif; ?>
                                        <?php if (($result->garantia_modelo ?? null) !== 'por_item' && in_array($result->status, ['Finalizado', 'Faturado', 'Entregue'])) : ?>
                                            <td class="text-center" style="text-align:center;"><?= $garantiaAte ? date('d/m/Y', strtotime($garantiaAte)) : '' ?></td>
                                        <?php endif; ?>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">DADOS DO CLIENTE</div>
                    <div class="dados dados-cliente" style="display:table;width:100%;table-layout:fixed;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                        <div style="display:table-cell;width:52%;padding-right:2mm;vertical-align:top;text-align:left;">
                            <span><b><?= $result->nomeCliente ?></b></span><br />
                            <span>CPF/CNPJ: <?= $result->documento ?></span><br />
                            <span>Tel: <?= $result->contato_cliente ? $result->contato_cliente.' / ' : '' ?><?= $result->telefone ?><?= $result->celular ? ' / '.$result->celular : '' ?></span><br />
                            <span><?= $result->email ?></span><br />
                        </div>
                        <div style="display:table-cell;width:48%;padding-left:2mm;vertical-align:top;text-align:left;">
                            <span><b><?= $result->rua.', '.$result->numero ?></b></span><br />
                            <span><?= $result->bairro ?></span><br />
                            <span><?= $result->cidade.' / '.$result->estado ?><?= $result->complemento ? ' — '.$result->complemento : '' ?></span><br />
                            <span>CEP: <?= $result->cep ?></span><br />
                        </div>
                    </div>

                    <?php if ($result->descricaoProduto) : ?>
                        <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">DESCRIÇÃO</div>
                        <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                            <div>
                                <?= printSafeHtml($result->descricaoProduto) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($result->defeito) : ?>
                        <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">DEFEITO APRESENTADO</div>
                        <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                            <div>
                                <?= printSafeHtml($result->defeito) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($result->observacoes) : ?>
                        <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">OBSERVAÇÕES</div>
                        <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                            <div>
                                <?= printSafeHtml($result->observacoes) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($result->laudoTecnico) : ?>
                        <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">PARECER TÉCNICO</div>
                        <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                            <div>
                                <?= printSafeHtml($result->laudoTecnico) ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (($result->garantia_modelo ?? null) !== 'por_item' && $result->garantias_id) : ?>
                        <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">TERMO DE GARANTIA</div>
                        <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                            <div style="text-align: justify;"><?= printSafeHtml($result->textoGarantia) ?></div>
                        </div>
                    <?php endif; ?>

                    <?php if ($produtos) : ?>
                        <div class="tabela" style="width:100%;margin:1mm 0 0;padding:0;page-break-inside:auto;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr class="table-secondary">
                                        <th>PRODUTO(S)</th>
                                        <th class="text-center" style="text-align:center;" width="10%">QTD</th>
                                        <th class="text-center" style="text-align:center;" width="10%">VALOR UNIT.</th>
                                        <?php if (($result->garantia_modelo ?? null) === 'por_item') : ?>
                                            <th>GARANTIA</th>
                                        <?php endif; ?>
                                        <th class="text-end" style="text-align:right;" width="15%" >SUBTOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($produtos as $p) :
                                        $subtotal = osValorDoItem($p);
                                        $totalProdutos = $totalProdutos + $subtotal;
                                        echo '<tr>';
                                        echo '  <td>' . $p->descricao . '</td>';
                                        echo '  <td class="text-center" style="text-align:center;">' . $p->quantidade . '</td>';
                                        echo '  <td class="text-center" style="text-align:center;">' . number_format($p->preco ?: $p->precoVenda, 2, ',', '.') . '</td>';
                                        if (($result->garantia_modelo ?? null) === 'por_item') {
                                            echo '  <td>' . garantiaHtmlItemImpressao($p) . '</td>';
                                        }
                                        echo '  <td class="text-end" style="text-align:right;">R$ ' . number_format($subtotal, 2, ',', '.') . '</td>';
                                        echo '</tr>';
                                    endforeach; ?>
                                    <tr>
                                        <td colspan="<?= ($result->garantia_modelo ?? null) === 'por_item' ? 4 : 3 ?>" class="text-end" style="text-align:right;"><b>TOTAL PRODUTOS:</b></td>
                                        <td class="text-end" style="text-align:right;"><b>R$ <?= number_format($totalProdutos, 2, ',', '.') ?></b></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                    <?php if ($servicos) : ?>
                        <div class="tabela" style="width:100%;margin:1mm 0 0;padding:0;page-break-inside:auto;">
                            <table class="table table-bordered">
                                <thead>
                                    <tr class="table-secondary">
                                        <th>SERVIÇO(S)</th>
                                        <th class="text-center" style="text-align:center;" width="10%">QTD</th>
                                        <th class="text-center" style="text-align:center;" width="10%">VALOR UNIT.</th>
                                        <?php if (($result->garantia_modelo ?? null) === 'por_item') : ?>
                                            <th>GARANTIA</th>
                                        <?php endif; ?>
                                        <th class="text-end" style="text-align:right;" width="15%" >SUBTOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        setlocale(LC_MONETARY, 'en_US');
                        foreach ($servicos as $s) :
                            $preco = $s->preco ?: $s->precoVenda;
                            $subtotal = osValorDoItem($s);
                            $totalServico = $totalServico + $subtotal;
                            echo '<tr>';
                            echo '  <td>' . $s->nome . '</td>';
                            echo '  <td class="text-center" style="text-align:center;">' . ($s->quantidade ?: 1) . '</td>';
                            echo '  <td class="text-center" style="text-align:center;">' . number_format($preco, 2, ',', '.') . '</td>';
                            if (($result->garantia_modelo ?? null) === 'por_item') {
                                echo '  <td>' . garantiaHtmlItemImpressao($s) . '</td>';
                            }
                            echo '  <td class="text-end" style="text-align:right;">R$ ' . number_format($subtotal, 2, ',', '.') . '</td>';
                            echo '</tr>';
                        endforeach; ?>
                                    <tr>
                                        <td colspan="<?= ($result->garantia_modelo ?? null) === 'por_item' ? 4 : 3 ?>" class="text-end" style="text-align:right;"><b>TOTAL SERVIÇOS:</b></td>
                                        <td class="text-end" style="text-align:right;"><b>R$ <?= number_format($totalServico, 2, ',', '.') ?></b></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>


                    <?php $resumoValores = osTotais($totalProdutos, $totalServico, $result->valor_desconto ?? 0, $result->tipo_desconto ?? null, $result->desconto ?? 0); ?>
                    <?php if ($totalProdutos != 0 || $totalServico != 0) : ?>
                        <?php $temPix = !empty($configuration['pix_key']); ?>
                        <div class="pagamento" style="display:table;width:100%;margin-top:1mm;border-collapse:collapse;page-break-inside:avoid;">
                            <?php if ($temPix) : ?>
                                <div class="qrcode" style="display:table-cell;width:30%;padding-right:3mm;vertical-align:top;text-align:center;">
                                    <div><img width="130px" src="<?= $qrCode ?>" alt="QR Code de Pagamento" /></div>
                                    <div>
                                        <div style="width:100%;text-align:center;">Escaneie o QRCode ao lado para pagar por Pix</div>
                                        <div class="chavePix">Chave Pix: <b><?= $chaveFormatada ?></b></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <div style="display:table-cell;width:<?= $temPix ? '70%' : '100%' ?>;vertical-align:top;">
                                <div class="tabela" style="width:100%;margin:0;padding:0;page-break-inside:avoid;">
                                    <table class="table table-bordered">
                                        <thead>
                                            <tr class="table-secondary">
                                                <th colspan="2">RESUMO DOS VALORES</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td style="width:50%;">
                                                    <div>PRODUTOS: <b>R$ <?= number_format($totalProdutos, 2, ',', '.') ?></b></div>
                                                    <div>SERVIÇOS: <b>R$ <?= number_format($totalServico, 2, ',', '.') ?></b></div>
                                                </td>
                                                <td style="width:50%;">
                                                    <div>DESCONTO: <b><?= $resumoValores['desconto'] > 0 ? '- ' : '' ?>R$ <?= number_format($resumoValores['desconto'], 2, ',', '.') ?></b></div>
                                                    <div>TOTAL: <b>R$ <?= number_format($resumoValores['total'], 2, ',', '.') ?></b></div>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </section>
                <footer>
                    <table class="assinaturas" style="width:100%;margin-top:1mm;border-collapse:collapse;border:0;page-break-inside:avoid;">
                        <tbody>
                            <tr>
                                <td style="width:46%;padding:1mm 2mm 0;border:0;border-top:1px solid #64748b;text-align:center;font-size:7pt;">Assinatura do cliente</td>
                                <td style="width:8%;padding:0;border:0;"></td>
                                <td style="width:46%;padding:1mm 2mm 0;border:0;border-top:1px solid #64748b;text-align:center;font-size:7pt;">Assinatura do técnico</td>
                            </tr>
                        </tbody>
                    </table>
                </footer>
            </div>
        <?php endif; ?>

        <?php if ($anexos && $imprimirAnexo) : ?>
            <div class="sub-page" id="anexos" style="page-break-before:always;">
                <header style="display:table;table-layout:fixed;width:100%;margin:0 0 1mm;padding:0 0 1mm;border:0;border-bottom:1px solid #cbd5e1;">
                    <?php if ($emitente == null) : ?>
                        <div class="alert alert-danger" role="alert">
                            Você precisa configurar os dados do emitente. >>> <a href="<?= base_url() ?>index.php/mapos/emitente">Configurar</a>
                        </div>
                    <?php else : ?>
                        <div class="imgLogo align-middle" style="display:table-cell;width:22%;padding-right:2mm;vertical-align:middle;">
                            <?php if ($logoImpressao !== '') : ?><img src="<?= html_escape($logoImpressao) ?>" alt="Logotipo MasterTrend" class="img-fluid" style="width:44mm;max-height:28mm;height:auto;"><?php endif; ?>
                        </div>
                        <div style="display:table-cell;width:44%;padding:0 2mm;vertical-align:middle;font-size:8pt;line-height:1.2;">
                            <span style="font-size: 15px;"><b><?= $emitente->nome ?></b></span><br>
                            <?php if ($emitente->cnpj != "00.000.000/0000-00") : ?>
                                <span class="align-middle">CNPJ: <?= $emitente->cnpj ?></span><br>
                            <?php endif; ?>
                            <span class="align-middle">
                                <?= $emitente->rua.', '.$emitente->numero.', '.$emitente->bairro ?><br>
                                <?= $emitente->cidade.' - '.$emitente->uf.' - '.$emitente->cep ?>
                            </span>
                        </div>
                        <div style="display:table-cell;width:34%;padding:0;vertical-align:middle;text-align:right;font-size:8pt;line-height:1.2;">
                            <span style="font-weight: bold;">Tel: <?= $emitente->telefone ?></span><br>
                            <span style="font-weight: bold;"><?= $emitente->email ?></span><br>
                            <span style="word-break: break-word;">Responsável: <b><?= $result->nome ?></b></span>
                        </div>
                    <?php endif; ?>
                </header>
                <section>
                    <div class="title" style="display:block;width:100%;margin:2mm 0;padding:1mm 0;border-bottom:2px solid #f97316;color:#111827;font-family:DejaVuSans,sans-serif;font-size:15pt;line-height:1.15;font-weight:bold;text-align:left;">
                        ORDEM DE SERVIÇO #<?= str_pad($result->idOs, 4, 0, STR_PAD_LEFT) ?>
                        <span class="emissao" style="float:right;padding-top:1mm;color:#475569;font-size:8pt;font-weight:normal;">Emissão: <?= date('d/m/Y') ?></span>
                    </div>
                    <div class="subtitle" style="width:100%;margin:1mm 0 0;padding:0.8mm 1.5mm;border:0;border-left:2px solid #f97316;background-color:#f1f5f9;color:#334155;font-family:DejaVuSans,sans-serif;font-size:8pt;line-height:1.2;font-weight:bold;text-align:left;">ANEXO(S)</div>
                    <div class="dados" style="display:block;width:auto;margin:0;padding:1mm 1.5mm;border:1px solid #e2e8f0;font-weight:normal;line-height:1.25;page-break-inside:avoid;">
                        <div style="width: 100%; display: flex; justify-content: space-around; align-items: center; flex-wrap: wrap;">
                            <?php
                                $contaAnexos = 0;
foreach ($anexos as $a) :
    if ($a->thumb) :
        $thumb = $a->url.'/thumbs/'.$a->thumb;
        $link  = $a->url.'/'.$a->anexo;
        ?>
                                        <img src="<?= $link ?>" alt="">
                            <?php
    endif;
endforeach;
?>
                        </div>
                    </div>
                <section>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
