<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

require_once __DIR__ . '/../vendor/autoload.php';

if (! class_exists('MpdfClienteSemRede')) {
    /**
     * Cliente HTTP do mPDF que nunca sai para a rede (#2879): um relatório com
     * <img src="https://..."> ou CSS remoto faria o servidor requisitar a URL
     * (SSRF cego). A resposta é 403, o que o mPDF trata como recurso ausente.
     * Logos e imagens locais não passam por aqui.
     */
    class MpdfClienteSemRede implements \Mpdf\Http\ClientInterface
    {
        public function sendRequest(\Psr\Http\Message\RequestInterface $request)
        {
            return new \Mpdf\PsrHttpMessageShim\Response(403);
        }
    }
}

if (! function_exists('mpdfDiretorioTemporario')) {
    /** Diretório privado e gravável para os artefatos temporários do mPDF. */
    function mpdfDiretorioTemporario(): string
    {
        $pasta = function_exists('armazenamentoPasta') ? armazenamentoPasta('tmp/mpdf') : null;
        if ($pasta === null || ! is_dir($pasta) || ! is_writable($pasta)) {
            throw new RuntimeException('Diretório temporário do mPDF não está gravável.');
        }

        return rtrim($pasta, '/\\');
    }
}

if (! function_exists('mpdfDiretorioArquivos')) {
    /** Diretório privado para PDFs persistidos quando o caller pede saída em arquivo. */
    function mpdfDiretorioArquivos(): string
    {
        $pasta = function_exists('armazenamentoPasta') ? armazenamentoPasta('pdf') : null;
        if ($pasta === null || ! is_dir($pasta) || ! is_writable($pasta)) {
            throw new RuntimeException('Diretório privado de PDFs não está gravável.');
        }

        return rtrim($pasta, '/\\');
    }
}

if (! function_exists('mpdfNomeArquivoSeguro')) {
    /**
     * Normaliza o nome sem permitir separadores, NUL ou escrita fora do storage.
     * Separadores legados (por exemplo, datas d/m/y) viram underscore.
     */
    function mpdfNomeArquivoSeguro(string $filename): string
    {
        $nome = trim(str_replace(["\\", '/', "\0"], '_', $filename));
        $nome = preg_replace('/[^\p{L}\p{N}._-]+/u', '_', $nome) ?? '';
        $nome = trim($nome, " ._-\t\r\n");

        if ($nome === '' || $nome === '.' || $nome === '..') {
            throw new InvalidArgumentException('Nome de arquivo PDF inválido.');
        }

        return $nome;
    }
}

if (! function_exists('mpdfCriar')) {
    /** mPDF A4 sem acesso à rede, com storage temporário privado do projeto. */
    function mpdfCriar(bool $landscape = false): \Mpdf\Mpdf
    {
        return new \Mpdf\Mpdf(
            [
                'mode' => 'c',
                'format' => $landscape ? 'A4-L' : 'A4',
                'tempDir' => mpdfDiretorioTemporario(),
            ],
            new \Mpdf\Container\SimpleContainer(['httpClient' => new MpdfClienteSemRede()])
        );
    }
}

if (! function_exists('pdf_create')) {
    /**
     * Mantém o contrato legado: stream=true envia inline; stream=false grava e
     * devolve o caminho absoluto do PDF privado gerado.
     */
    function pdf_create($html, $filename, $stream = true, $landscape = false)
    {
        $nome = mpdfNomeArquivoSeguro((string) $filename);
        $mpdf = mpdfCriar((bool) $landscape);

        $mpdf->showImageErrors = false;
        $mpdf->WriteHTML($html);

        if ($stream) {
            $mpdf->Output($nome . '.pdf', 'I');

            return;
        }

        $caminho = mpdfDiretorioArquivos() . DIRECTORY_SEPARATOR . $nome . '.pdf';
        $mpdf->Output($caminho, 'F');

        if (! is_file($caminho)) {
            throw new RuntimeException('O mPDF não gerou o arquivo PDF solicitado.');
        }

        return $caminho;
    }
}
