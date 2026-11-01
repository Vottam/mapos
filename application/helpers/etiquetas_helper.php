<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once __DIR__ . '/../vendor/autoload.php';
// MpdfClienteSemRede (cliente HTTP que nunca sai para a rede) vive no helper oficial.
require_once __DIR__ . '/mpdf_helper.php';

/**
 * Etiquetas 40x25mm do MapOS 5 — motor único, local, sem CDN.
 *
 * O fork LIVE carregava JsBarcode e qrcodejs2 de cdn.jsdelivr.net. A v5
 * removeu toda dependência de CDN e imprime código de barras com o mPDF
 * nativo (<barcode>) e QR com mpdf/qrcode. As etiquetas compartilham a
 * área física de 40x25mm e a stack local; cada template mantém somente
 * os campos próprios do seu tipo. A etiqueta de serial é intencionalmente
 * limpa: Code128 do serial e serial textual, sem OS/equipamento.
 *
 * Regras:
 *  - Nada de CDN e nada de requisição de rede (MpdfClienteSemRede).
 *  - O payload do código de barras da OS é "MT-OS-{id}"; na etiqueta de
 *    serial, o payload é o serial cru, para preservar a leitura existente.
 *  - Identificadores internos de resolução nunca entram no template de serial.
 *
 * DIMENSÕES (medidas, não estimativas):
 *  - Papel: 40 x 25 mm, margens de 0,5mm -> área útil de 39 x 24 mm.
 *  - A caixa .etiqueta ocupa 39 x 24 mm com 0,3mm de padding, e o
 *    overflow hidden garante que nada vaze para uma segunda página.
 *  - O mPDF dimensiona o <barcode> por:
 *        largura = (modulos + quietL + quietR) * nom-X * size
 *        altura  = ((2*lightTB*nom-X) + nom-H) * size * bheight
 *    com nom-X = 0,381mm, nom-H = 10mm e quiet de 10 X-dims de cada lado.
 *    Como "size" comanda a largura E a altura, ele precisa ser derivado
 *    dos dois limites ao mesmo tempo — foi por ignorar a altura que a
 *    primeira versão gerava etiquetas de 2 páginas.
 *  - O Code128 não tem troca automática A/B/C no mPDF (o JsBarcode do fork
 *    tinha). Só o C128C (2 dígitos por símbolo) é mais compacto que o
 *    C128B, e ele é usado quando o serial é todo numérico com tamanho par.
 *  - X-dim: para os seriais mais longos da base (28 caracteres) não há
 *    como ter módulo de 0,25mm dentro de 39mm — é limite físico da
 *    etiqueta de 40mm, não do código. Nesses casos o serial também vai
 *    impresso por extenso, para a etiqueta continuar utilizável.
 */
if (! function_exists('etiquetaFormatoPagina')) {
    /** Formato físico da etiqueta, em mm. */
    function etiquetaFormatoPagina(): array
    {
        return [40, 25];
    }
}

if (! function_exists('etiquetaMargemPagina')) {
    /** Margem da página da etiqueta, em mm. */
    function etiquetaMargemPagina(): float
    {
        return 0.5;
    }
}

if (! function_exists('etiquetaLarguraCodigo')) {
    /**
     * Largura disponível para o código de barras, em mm.
     * 39mm de papel útil - 0,3mm de padding da etiqueta - 0,1mm do
     * padding do próprio .barcode em cada lado.
     */
    function etiquetaLarguraCodigo(): float
    {
        return 38.2;
    }
}

if (! function_exists('etiquetaAlturaCodigo')) {
    /**
     * Altura disponível para o código de barras, em mm.
     * 24mm de papel útil - 0,3mm de padding - 4,1mm do número da OS
     * - 2,7mm do subtítulo - 2,1mm do rodapé - 2,7mm da referência.
     */
    function etiquetaAlturaCodigo(): float
    {
        return 11.0;
    }
}

if (! function_exists('etiquetaCssBase')) {
    /**
     * CSS da etiqueta. O mPDF só desenha <barcode> quando a tag recebe
     * estilo de cor/margem (é o que o .barcode do assets/dist/pdf.css já
     * faz para a impressão em lote de produtos).
     *
     * A caixa tem tamanho fixo e overflow hidden: sem isso, uma descrição
     * de produto longa empurrava o rodapé para uma segunda página, que
     * saía impressa em branco.
     */
    function etiquetaCssBase(): string
    {
        return '<style>' . "\n"
            . 'html,body{margin:0;padding:0;background:#fff;color:#000;}' . "\n"
            . '.etiqueta{width:39mm;height:24mm;padding:0.3mm;box-sizing:border-box;text-align:center;overflow:hidden;}' . "\n"
            . '.etiqueta .numero{font-size:10pt;font-weight:bold;line-height:1.05;margin:0 0 0.4mm 0;}' . "\n"
            . '.etiqueta .sub{font-size:5.6pt;line-height:1.1;margin:0 0 0.5mm 0;}' . "\n"
            . '.etiqueta .desc{font-size:5.2pt;font-weight:bold;line-height:1.05;margin:0 0 0.3mm 0;height:4.2mm;overflow:hidden;}' . "\n"
            . '.etiqueta .celula{text-align:center;vertical-align:middle;padding:0;}' . "\n"
            . '.etiqueta .barcode{vertical-align:top;color:#000;margin:0;padding:0.1mm;}' . "\n"
            . '.etiqueta .rodape{font-size:5.2pt;line-height:1.05;margin:0.2mm 0 0 0;}' . "\n"
            . '.etiqueta .aviso{font-size:7pt;font-weight:bold;}' . "\n"
            . '</style>';
    }
}

if (! function_exists('etiquetaMpdf')) {
    /**
     * mPDF em 40x25mm, sem acesso à rede.
     *
     * @return \Mpdf\Mpdf
     */
    function etiquetaMpdf(): \Mpdf\Mpdf
    {
        $formato = etiquetaFormatoPagina();
        $margem = etiquetaMargemPagina();
        $tempDir = function_exists('armazenamentoPasta') ? armazenamentoPasta('tmp/mpdf') : null;

        if ($tempDir === null || ! is_writable($tempDir)) {
            throw new RuntimeException('Diretório temporário do mPDF não está gravável.');
        }

        // O formato é passado por chave (a forma posicional só aceita A4/A4-L).
        return new \Mpdf\Mpdf(
            [
                'format' => $formato,
                'margin_left' => $margem,
                'margin_right' => $margem,
                'margin_top' => $margem,
                'margin_bottom' => $margem,
                'margin_header' => 0,
                'margin_footer' => 0,
                'tempDir' => $tempDir,
            ],
            new \Mpdf\Container\SimpleContainer(['httpClient' => new MpdfClienteSemRede()])
        );
    }
}

if (! function_exists('etiquetaBarcodeOs')) {
    /**
     * Payload do código de barras da etiqueta de OS — idêntico ao fork.
     */
    function etiquetaBarcodeOs(int $idOs): string
    {
        return 'MT-OS-' . $idOs;
    }
}

if (! function_exists('etiquetaPayloadProduto')) {
    /**
     * Payload do QR da etiqueta de produto — idêntico ao fork
     * ("PROD:" + id com 4 dígitos).
     */
    function etiquetaPayloadProduto(int $idProduto): string
    {
        return 'PROD:' . str_pad((string) $idProduto, 4, '0', STR_PAD_LEFT);
    }
}

if (! function_exists('etiquetaTipoBarcode')) {
    /**
     * Escolhe o subtype Code128 mais compacto que aceita o payload.
     *
     * C128A e C128B gastam 11 módulos por caractere; só o C128C empacota
     * 2 dígitos por símbolo (6 módulos), e ele exige payload só numérico
     * com tamanho par. Nos casos em que o C128C não se aplica, o C128B é
     * o tipo genérico que aceita qualquer ASCII imprimível.
     *
     * @return string Tipo aceito pelo mPDF ('C128C' ou 'C128B').
     */
    function etiquetaTipoBarcode(string $payload): string
    {
        if (preg_match('/^[0-9]+$/', $payload) && strlen($payload) % 2 === 0) {
            return 'C128C';
        }

        return 'C128B';
    }
}

if (! function_exists('etiquetaConfigBarcode')) {
    /**
     * Calcula tipo, size e bheight do <barcode> para o payload caber na
     * etiqueta com o maior X-dim (largura do módulo) possível.
     *
     * @return array{tipo: string, size: float, bheight: float, xdim: float, modulos: int, largura: float, altura: float}
     */
    function etiquetaConfigBarcode(string $payload, float $alturaUtil = 11.0): array
    {
        $tipo = etiquetaTipoBarcode($payload);
        $barcode = new \Mpdf\Barcode();

        try {
            $arr = $barcode->getBarcodeArray($payload, $tipo);
        } catch (\Throwable $e) {
            $arr = false;
        }

        if ($arr === false) {
            // Payload fora do ASCII (ex.: "PATRIMÔNIO 000113"): Code128 não
            // codifica. O chamador decide o que fazer; aqui devolvemos um
            // marcador explícito para não gerar um código mentiroso.
            return [
                'tipo' => null, 'size' => 0.0, 'bheight' => 0.0, 'xdim' => 0.0,
                'modulos' => 0, 'largura' => 0.0, 'altura' => 0.0,
            ];
        }

        $modulos = (int) $arr['maxw'];
        $nomX = (float) $arr['nom-X'];
        $nomH = (float) $arr['nom-H'];
        $quiet = (float) $arr['lightmL'] + (float) $arr['lightmR'];

        $larguraUtil = etiquetaLarguraCodigo();
        $alturaUtil = $alturaUtil;

        // size que faz o código ocupar exatamente a largura disponível
        $sizeLargura = $larguraUtil / (($modulos + $quiet) * $nomX);

        // maior bheight (1..3) que cabe na altura com o size de largura
        $bheight = 1.0;
        foreach ([1.0, 1.5, 2.0, 2.5, 3.0] as $candidato) {
            if (($nomH * $sizeLargura * $candidato) <= $alturaUtil) {
                $bheight = $candidato;
            }
        }

        // se ainda assim a altura estourou, reduz o size
        $sizeAltura = $alturaUtil / ($nomH * $bheight);
        $size = min($sizeLargura, $sizeAltura);

        $largura = ($modulos + $quiet) * $nomX * $size;
        $altura = $nomH * $size * $bheight;

        return [
            'tipo' => $tipo,
            'size' => round($size, 4),
            'bheight' => $bheight,
            'xdim' => round($nomX * $size, 4),
            'modulos' => $modulos,
            'largura' => round($largura, 2),
            'altura' => round($altura, 2),
        ];
    }
}

if (! function_exists('etiquetaHtmlOs')) {
    /**
     * HTML da etiqueta da OS: número da OS, nome do cliente e Code128.
     *
     * @param object $os Linha de os com idOs e nomeCliente.
     */
    function etiquetaHtmlOs(object $os): string
    {
        $idOs = (int) $os->idOs;
        $payload = etiquetaBarcodeOs($idOs);
        $cliente = trim((string) ($os->nomeCliente ?? ''));
        $cfg = etiquetaConfigBarcode($payload);

        $html = etiquetaCssBase();
        $html .= '<div class="etiqueta">';
        $html .= '<div class="numero">OS #' . $idOs . '</div>';
        if ($cliente !== '') {
            $html .= '<div class="sub">' . e(mb_strimwidth($cliente, 0, 44, '…')) . '</div>';
        }
        if ($cfg['tipo'] !== null) {
            $html .= '<div class="celula"><barcode code="' . e($payload) . '" text="0" type="' . e($cfg['tipo']) . '" size="' . e((string) $cfg['size']) . '" height="' . e((string) $cfg['bheight']) . '" disableborder="0" class="barcode" /></div>';
        }
        $html .= '</div>';

        return $html;
    }
}

if (! function_exists('etiquetaHtmlSerial')) {
    /**
     * HTML da etiqueta limpa de serial do equipamento.
     *
     * O LIVE imprime somente o Code128 do serial e o serial em texto,
     * com a barra acima e o texto abaixo. O ID da OS e a referência do
     * equipamento são aceitos na assinatura por compatibilidade com os
     * chamadores existentes, mas são dados internos e nunca são renderizados.
     *
     * @param string     $serial      Serial do fabricante (num_serie).
     * @param int|null   $osId        Dado interno legado; não é impresso.
     * @param string|null $referencia Dado interno legado; não é impresso.
     */
    function etiquetaHtmlSerial(string $serial, ?int $osId = null, ?string $referencia = null): string
    {
        $serial = trim($serial);

        $html = etiquetaCssBase();
        $html .= '<div class="etiqueta">';

        if ($serial === '') {
            $html .= '</div>';

            return $html;
        }

        $cfg = etiquetaConfigBarcode($serial);

        if ($cfg['tipo'] !== null) {
            $html .= '<div class="celula"><barcode code="' . e($serial) . '" text="0" type="' . e($cfg['tipo']) . '" size="' . e((string) $cfg['size']) . '" height="' . e((string) $cfg['bheight']) . '" disableborder="0" class="barcode" /></div>';
        } else {
            // Serial fora do ASCII: Code128 não codifica. Não inventa
            // metadados; mantém somente o aviso técnico para esse caso.
            $html .= '<div class="aviso">Serial não codificável</div>';
        }

        $html .= '<div class="rodape">' . e($serial) . '</div>';
        $html .= '</div>';

        return $html;
    }
}

if (! function_exists('etiquetaCssProduto')) {
    /**
     * CSS da etiqueta individual de produto 40x25mm.
     *
     * Classes proprias do produto (etqp-*), nao compartilhadas com as
     * etiquetas de OS e de serial, para nao alterar aqueles layouts.
     *
     * Area util: 39 x 24 mm (papel 40 x 25 mm menos margens de 0,5 mm).
     */
    function etiquetaCssProduto(): string
    {
        return '<style>' . "\n"
            . 'html,body{margin:0;padding:0;background:#fff;color:#000;}' . "\n"
            . '.etqp-etiqueta{width:39mm;height:24mm;padding:0.3mm 0.2mm;box-sizing:border-box;text-align:center;overflow:hidden;}' . "\n"
            . '.etqp-descricao{font-size:6.8pt;font-weight:bold;line-height:1.12;margin:0 0 0.5mm 0;height:5.8mm;overflow:hidden;}' . "\n"
            . '.etqp-celula{text-align:center;vertical-align:middle;padding:0;}' . "\n"
            . '.etqp-barcode{vertical-align:middle;color:#000;margin:0;padding:0;}' . "\n"
            . '.etqp-codigo{font-size:5.8pt;margin:0.5mm 0 0.4mm 0;}' . "\n"
            . '.etqp-preco{font-size:13pt;font-weight:bold;line-height:1.0;margin:0.6mm 0 0 0;}' . "\n"
            . '.etqp-aviso{font-size:7pt;font-weight:bold;}' . "\n"
            . '</style>';
    }
}

if (! function_exists('etiquetaHtmlProduto')) {
    /**
     * HTML da etiqueta individual de produto 40x25mm.
     *
     * Layout: descricao no topo (negrito, maior, centralizada), codigo de
     * barras 1D no meio (CODE 128, sem QR), codigo discreto e preco em
     * grande destaque na base. O preco e impresso por extenso, nunca embutido
     * no codigo de barras, para manter a leitura robusta.
     *
     * @param object $produto Linha de produtos com idProdutos, descricao, precoVenda e codDeBarra.
     */
    function etiquetaHtmlProduto(object $produto): string
    {
        $id = (int) $produto->idProdutos;
        $descricao = trim((string) ($produto->descricao ?? ''));
        $preco = isset($produto->precoVenda) ? (float) $produto->precoVenda : 0.0;
        $codigo = '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);

        // Payload do codigo de barras 1D: usa o codigo de barras do produto
        // quando houver; senao, o identificador interno (id). Nunca inventa URL.
        $codDeBarra = trim((string) ($produto->codDeBarra ?? ''));
        $payload = $codDeBarra !== '' ? $codDeBarra : 'PROD:' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
        // Altura de codigo limitada para caber ao lado da descricao e do preco
        // dentro dos 24 mm de altura util da etiqueta.
        $cfg = etiquetaConfigBarcode($payload, 8.5);

        $html = etiquetaCssProduto();
        $html .= '<div class="etqp-etiqueta">';
        // TOPO: descricao (1 ou 2 linhas, negrito, maior que a anterior).
        $html .= '<div class="etqp-descricao">' . e(mb_strtoupper($descricao !== '' ? mb_strimwidth($descricao, 0, 52, '…') : 'PRODUTO')) . '</div>';
        // MEIO: codigo de barras 1D centralizado.
        $html .= '<div class="etqp-celula">';
        if ($cfg['tipo'] !== null) {
            $html .= '<barcode code="' . e($payload) . '" text="0" type="' . e($cfg['tipo']) . '" size="' . e((string) $cfg['size']) . '" height="' . e((string) $cfg['bheight']) . '" disableborder="0" class="etqp-barcode" />';
        } else {
            $html .= '<div class="etqp-aviso">Codigo nao codificavel</div>';
        }
        $html .= '</div>';
        // Codigo humano discreto (nao compete com o preco).
        $html .= '<div class="etqp-codigo">' . e($codigo) . '</div>';
        // BASE: preco em grande destaque, formato PT-BR.
        $html .= '<div class="etqp-preco">R$ ' . e(number_format($preco, 2, ',', '.')) . '</div>';
        $html .= '</div>';

        return $html;
    }
}

if (! function_exists('etiquetaGerarPdf')) {
    /**
     * Gera o PDF da etiqueta e o envia ao navegador.
     *
     * @param string $html    HTML pronto para o mPDF.
     * @param string $filename Nome do arquivo (sem extensão).
     */
    function etiquetaGerarPdf(string $html, string $filename): void
    {
        $mpdf = etiquetaMpdf();
        $mpdf->showImageErrors = true;
        $mpdf->WriteHTML($html);
        $mpdf->Output($filename . '.pdf', 'I');
    }
}
