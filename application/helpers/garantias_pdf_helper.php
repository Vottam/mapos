<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

if (! function_exists('garantiaPdfHtml')) {
    /** Gera o HTML mPDF local e determinístico dos termos de garantia. */
    function garantiaPdfHtml(?object $emitente, string $texto, ?int $osId = null, string $osDataFinal = ''): string
    {
        $nome = (string) ($emitente->nome ?? '');
        $cnpj = (string) ($emitente->cnpj ?? '');
        $endereco = trim(
            (string) ($emitente->rua ?? '') . ', nº ' . (string) ($emitente->numero ?? '') . ', '
            . (string) ($emitente->bairro ?? '') . ' - ' . (string) ($emitente->cidade ?? '') . ' - '
            . (string) ($emitente->uf ?? ''),
            ' ,'
        );
        $contato = trim((string) ($emitente->email ?? '') . ' - Fone: ' . (string) ($emitente->telefone ?? ''), ' -');
        $textoSeguro = function_exists('printSafeHtml') ? printSafeHtml($texto) : html_escape($texto);
        $osMeta = $osId !== null
            ? '<div>Garantia OS: ' . html_escape((string) $osId) . '</div><div>Emissão: '
                . html_escape($osDataFinal !== '' ? date('d/m/Y', strtotime($osDataFinal)) : '') . '</div>'
            : '';

        return '<!doctype html><html lang="pt-br"><head><meta charset="UTF-8"><style>'
            . 'body{font-family:dejavusans,sans-serif;font-size:10pt;color:#000;}'
            . 'h1{text-align:center;font-size:16pt;margin:20pt 0;}'
            . '.cabecalho{border-bottom:0.3mm solid #000;padding-bottom:10pt;}'
            . '.cabecalho strong{font-size:13pt;}.cabecalho div{margin:2pt 0;}'
            . '.texto{margin-top:20pt;line-height:1.35;}'
            . '.assinaturas{width:100%;margin-top:70pt;border-collapse:collapse;}'
            . '.assinaturas td{width:33%;padding:0 8pt;text-align:center;vertical-align:bottom;}'
            . '.linha{border-top:0.3mm solid #000;padding-top:5pt;}'
            . '</style></head><body><section class="cabecalho">'
            . ($nome !== ''
                ? '<strong>' . html_escape($nome) . '</strong><div>' . html_escape($cnpj) . '</div><div>'
                    . html_escape($endereco) . '</div><div>' . html_escape($contato) . '</div>'
                : '<div>Emitente não configurado.</div>')
            . '<div>Emissão: ' . date('d/m/Y') . '</div>' . $osMeta
            . '</section><h1>Termo de Garantia</h1><section class="texto">' . $textoSeguro . '</section>'
            . '<table class="assinaturas"><tr><td><div class="linha">Data</div></td><td><div class="linha">Assinatura do Cliente</div></td><td><div class="linha">Assinatura do Técnico Responsável</div></td></tr></table>'
            . '</body></html>';
    }
}
