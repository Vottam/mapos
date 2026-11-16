<?php

defined('BASEPATH') or exit('No direct script access allowed');

if (! function_exists('garantiaNormalizarDias')) {
    /** Inteiro não negativo representável pelo INT UNSIGNED do schema, ou null. */
    function garantiaNormalizarDias($valor): ?int
    {
        if (! is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '' || ! preg_match('/^[0-9]+$/D', $texto)) {
            return null;
        }

        $normalizado = ltrim($texto, '0');
        $normalizado = $normalizado === '' ? '0' : $normalizado;
        $maximo = '4294967295';
        if (strlen($normalizado) > strlen($maximo)
            || (strlen($normalizado) === strlen($maximo) && strcmp($normalizado, $maximo) > 0)
        ) {
            return null;
        }

        return (int) $normalizado;
    }
}

if (! function_exists('garantiaDiasValido')) {
    /** Validação simples: zero permitido; prazos positivos também. */
    function garantiaDiasValido($dias): bool
    {
        return garantiaNormalizarDias($dias) !== null;
    }
}

if (! function_exists('garantiaValorDoItem')) {
    function garantiaValorDoItem($item, string $campo)
    {
        if (is_object($item)) {
            return $item->{$campo} ?? null;
        }
        if (is_array($item)) {
            return $item[$campo] ?? null;
        }

        return null;
    }
}

if (! function_exists('garantiaRotuloItem')) {
    /** Não calcula vencimento: a data efetiva de início pode não estar disponível. */
    function garantiaRotuloItem($item): string
    {
        $dias = garantiaNormalizarDias(garantiaValorDoItem($item, 'garantia_dias_snapshot'));
        if ($dias === null) {
            return 'Não informado';
        }

        return $dias . ' ' . ($dias === 1 ? 'dia' : 'dias');
    }
}

if (! function_exists('garantiaSnapshotDoCatalogo')) {
    /** Campos autoritativos de snapshot copiados do catálogo, ou null se inválidos. */
    function garantiaSnapshotDoCatalogo($cadastro): ?array
    {
        if (! is_object($cadastro)) {
            return null;
        }

        $dias = garantiaNormalizarDias($cadastro->garantia_dias ?? null);
        if ($dias === null) {
            return null;
        }

        return [
            'garantia_dias_snapshot' => $dias,
        ];
    }
}

if (! function_exists('garantiaEditorItem')) {
    /** Interface única por item: um prazo numérico e aviso zero. */
    function garantiaEditorItem($item, string $contexto, int $idOperacao, int $idItem, string $rota, bool $editavel): HtmlSeguro
    {
        $dias = garantiaNormalizarDias(garantiaValorDoItem($item, 'garantia_dias_snapshot'));
        $rotulo = htmlspecialchars(garantiaRotuloItem($item), ENT_QUOTES, 'UTF-8');
        $avisoZero = $dias === 0
            ? '<small class="block text-warning">' . htmlspecialchars(garantiaAvisoZero(), ENT_QUOTES, 'UTF-8') . '</small>'
            : '';

        if (! $editavel || $dias === null || ! in_array($contexto, ['venda', 'produto', 'servico'], true)) {
            return new HtmlSeguro('<span class="inline-flex flex-col gap-1">' . $rotulo . $avisoZero . '</span>');
        }

        $campoDias = 'garantia-dias-' . $contexto . '-' . $idItem;
        $campoOperacao = $contexto === 'venda' ? 'idVendas' : 'idOs';
        $tipoHidden = $contexto === 'venda' ? '' : '<input type="hidden" name="tipo" value="' . $contexto . '">';
        $ci = get_instance();
        $nomeCsrf = htmlspecialchars($ci->security->get_csrf_token_name(), ENT_QUOTES, 'UTF-8');
        $valorCsrf = htmlspecialchars($ci->security->get_csrf_hash(), ENT_QUOTES, 'UTF-8');
        $csrfHidden = '<input type="hidden" name="' . $nomeCsrf . '" value="' . $valorCsrf . '">';
        $idOperacaoHtml = htmlspecialchars((string) $idOperacao, ENT_QUOTES, 'UTF-8');
        $idItemHtml = htmlspecialchars((string) $idItem, ENT_QUOTES, 'UTF-8');
        $rotaHtml = htmlspecialchars($rota, ENT_QUOTES, 'UTF-8');
        $diasHtml = htmlspecialchars((string) $dias, ENT_QUOTES, 'UTF-8');
        $classe = 'grid min-w-44 gap-1';

        return new HtmlSeguro('<form method="post" action="' . $rotaHtml . '" class="' . $classe . '" data-os-acao="' . ($contexto === 'servico' ? 'servico' : 'produto') . '">'
            . $csrfHidden
            . '<input type="hidden" name="' . $campoOperacao . '" value="' . $idOperacaoHtml . '">'
            . '<input type="hidden" name="idItem" value="' . $idItemHtml . '">' . $tipoHidden
            . '<label class="sr-only" for="' . $campoDias . '">Prazo comercial informado, em dias</label>'
            . '<input id="' . $campoDias . '" name="dias" type="number" min="0" step="1" inputmode="numeric" value="' . $diasHtml . '" class="form-input" required>'
            . '<button type="submit" class="text-caption text-primary underline">Salvar prazo</button>'
            . '<small class="text-caption text-muted">Prazo comercial; os direitos legais permanecem aplicáveis.</small>'
            . '<small class="text-caption text-warning" data-garantia-zero-aviso' . ($dias === 0 ? '' : ' hidden') . ' aria-live="polite">' . htmlspecialchars(garantiaAvisoZero(), ENT_QUOTES, 'UTF-8') . '</small>'
            . '</form>');
    }
}

if (! function_exists('garantiaHtmlItemImpressao')) {
    function garantiaHtmlItemImpressao($item): string
    {
        $dias = garantiaNormalizarDias(garantiaValorDoItem($item, 'garantia_dias_snapshot'));
        $rotulo = htmlspecialchars(garantiaRotuloItem($item), ENT_QUOTES, 'UTF-8');
        if ($dias !== 0) {
            return $rotulo;
        }

        return $rotulo . '<br><small>' . htmlspecialchars(garantiaAvisoZero(), ENT_QUOTES, 'UTF-8') . '</small>';
    }
}

if (! function_exists('garantiaAvisoLegal')) {
    function garantiaAvisoLegal(): string
    {
        return 'Prazo comercial por item. Os direitos previstos na legislação de defesa do consumidor permanecem aplicáveis; prazos legais são independentes deste registro e não são somados automaticamente.';
    }
}

if (! function_exists('garantiaAvisoZero')) {
    function garantiaAvisoZero(): string
    {
        return 'Prazo comercial informado: 0 dias. Os direitos previstos na legislação de defesa do consumidor permanecem aplicáveis.';
    }
}