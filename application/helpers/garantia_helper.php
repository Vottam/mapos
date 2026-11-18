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

if (! function_exists('garantiaConfiguracaoAtual')) {
    /** Lê a tabela configuracoes (memoizada por request). Fora do contexto web, devolve []. */
    function garantiaConfiguracaoAtual(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];
        $ci = function_exists('get_instance') ? get_instance() : null;
        if ($ci !== null && isset($ci->db)) {
            try {
                foreach ($ci->db->get('configuracoes')->result() as $c) {
                    $cache[$c->config] = $c->valor;
                }
            } catch (Throwable $erro) {
                log_message('error', 'garantiaConfiguracaoAtual: ' . $erro->getMessage());
                $cache = [];
            }
        }

        return $cache;
    }
}

if (! function_exists('garantiaPadraoGlobalDias')) {
    /** Padrão comercial global por tipo (produto|servico). Ausente/inválido → 90 (fallback controlado). */
    function garantiaPadraoGlobalDias(?array $configuracao, string $tipo): int
    {
        $configuracao ??= garantiaConfiguracaoAtual();
        $chave = $tipo === 'servico' ? 'garantia_padrao_servico_dias' : 'garantia_padrao_produto_dias';
        $dias = garantiaNormalizarDias($configuracao[$chave] ?? null);

        return $dias ?? 90;
    }
}

if (! function_exists('garantiaDiasCustomizado')) {
    /** true = prazo personalizado no catálogo; false = herda o padrão global. Ausente → personalizado (compatibilidade). */
    function garantiaDiasCustomizado($cadastro): bool
    {
        $customBruto = garantiaValorDoItem($cadastro, 'garantia_dias_custom');
        if ($customBruto === null) {
            return true;
        }

        return (int) $customBruto === 1;
    }
}

if (! function_exists('garantiaBaseComercialDias')) {
    /** Base comercial efetiva de um item de catálogo: personalizada ou padrão global vigente. */
    function garantiaBaseComercialDias($cadastro, string $tipo, ?array $configuracao = null): ?int
    {
        if (! is_object($cadastro) && ! is_array($cadastro)) {
            return null;
        }
        if (garantiaDiasCustomizado($cadastro)) {
            return garantiaNormalizarDias(garantiaValorDoItem($cadastro, 'garantia_dias'));
        }

        return garantiaPadraoGlobalDias($configuracao, $tipo);
    }
}

if (! function_exists('garantiaFornecedorVencimento')) {
    /** Data de vencimento da garantia do fornecedor (Y-m-d) ou null quando não informada/inválida. */
    function garantiaFornecedorVencimento($cadastro): ?string
    {
        if (! is_object($cadastro) && ! is_array($cadastro)) {
            return null;
        }
        $diasBrutos = garantiaValorDoItem($cadastro, 'supplier_warranty_days');
        if ($diasBrutos === null || $diasBrutos === '') {
            return null;
        }
        $dias = garantiaNormalizarDias($diasBrutos);
        if ($dias === null) {
            return null;
        }
        $inicio = substr(trim((string) garantiaValorDoItem($cadastro, 'supplier_warranty_start_date')), 0, 10);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $inicio) || $inicio === '0000-00-00') {
            return null;
        }
        try {
            $inicioData = DateTimeImmutable::createFromFormat('!Y-m-d', $inicio, new DateTimeZone('America/Sao_Paulo'));
            if ($inicioData === false) {
                return null;
            }

            return $inicioData->add(new DateInterval('P' . $dias . 'D'))->format('Y-m-d');
        } catch (Throwable $erro) {
            return null;
        }
    }
}

if (! function_exists('garantiaFornecedorRestanteDias')) {
    /** Dias restantes da garantia do fornecedor (nunca negativo). null quando não informada. */
    function garantiaFornecedorRestanteDias($cadastro, ?string $hojeYmd = null): ?int
    {
        $vencimento = garantiaFornecedorVencimento($cadastro);
        if ($vencimento === null) {
            return null;
        }
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $hoje = garantiaHojeYmd($hojeYmd, $fuso);
        $dataVencimento = DateTimeImmutable::createFromFormat('!Y-m-d', $vencimento, $fuso);
        $dataHoje = DateTimeImmutable::createFromFormat('!Y-m-d', $hoje, $fuso);
        if (! $dataVencimento || ! $dataHoje) {
            return null;
        }
        $diferenca = (int) $dataHoje->diff($dataVencimento)->format('%r%a');

        return max(0, $diferenca);
    }
}

if (! function_exists('garantiaSugestaoCatalogoDias')) {
    /**
     * Prazo sugerido para uma NOVA operação a partir do catálogo.
     * Com repasse ativo no produto: max(base comercial, restante do fornecedor).
     */
    function garantiaSugestaoCatalogoDias($cadastro, string $tipo, ?array $configuracao = null, ?string $hojeYmd = null): ?int
    {
        $base = garantiaBaseComercialDias($cadastro, $tipo, $configuracao);
        if ($base === null) {
            return null;
        }
        if ($tipo !== 'produto') {
            return $base;
        }
        $repasseBruto = garantiaValorDoItem($cadastro, 'supplier_warranty_pass_through');
        $repasse = $repasseBruto !== null && (int) $repasseBruto === 1;
        if (! $repasse) {
            return $base;
        }
        $restante = garantiaFornecedorRestanteDias($cadastro, $hojeYmd);

        return $restante === null ? $base : max($base, $restante);
    }
}

if (! function_exists('garantiaFornecedorDecisaoSalvar')) {
    /**
     * Decide o destino dos campos de garantia do fornecedor ao salvar o produto.
     * Puro/testável; datas e flag de renovação vêm por parâmetro.
     *
     * @param int|null    $armazenadoDias prazo registrado no banco (null = não informada)
     * @param mixed       $postDiasRaw    bruto do POST ('' = não informada)
     * @param bool        $renovar        ação explícita "Renovar contagem da garantia"
     * @param string      $hojeYmd        hoje operacional (Y-m-d)
     * @return array{acao: string, days: ?int, start_date: ?string}
     *   acao: manter (nada muda) | atualizar (prazo definido/alterado → nova contagem hoje)
     *         | renovar (mesmo prazo, contagem reiniciada hoje) | limpar (prazo removido)
     *         | invalido (valor rejeitado — chamador valida antes)
     */
    function garantiaFornecedorDecisaoSalvar(?int $armazenadoDias, $postDiasRaw, bool $renovar, string $hojeYmd): array
    {
        $texto = is_scalar($postDiasRaw) ? trim((string) $postDiasRaw) : '';
        if ($texto === '') {
            return $armazenadoDias === null
                ? ['acao' => 'manter', 'days' => null, 'start_date' => null]
                : ['acao' => 'limpar', 'days' => null, 'start_date' => null];
        }
        $postDias = garantiaNormalizarDias($texto);
        if ($postDias === null) {
            return ['acao' => 'invalido', 'days' => $armazenadoDias, 'start_date' => null];
        }
        if ($armazenadoDias === null || $postDias !== $armazenadoDias) {
            // Prazo novo ou alterado: contagem reiniciada a partir de hoje.
            return ['acao' => 'atualizar', 'days' => $postDias, 'start_date' => $hojeYmd];
        }
        if ($renovar) {
            return ['acao' => 'renovar', 'days' => $postDias, 'start_date' => $hojeYmd];
        }

        return ['acao' => 'manter', 'days' => $postDias, 'start_date' => null];
    }
}

if (! function_exists('garantiaSnapshotDoCatalogo')) {
    /** Campos autoritativos de snapshot copiados do catálogo, ou null se inválidos. */
    function garantiaSnapshotDoCatalogo($cadastro, string $tipo = 'produto', ?array $configuracao = null, ?string $hojeYmd = null): ?array
    {
        if (! is_object($cadastro) && ! is_array($cadastro)) {
            return null;
        }

        $dias = garantiaSugestaoCatalogoDias($cadastro, $tipo, $configuracao, $hojeYmd);
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

        if ($contexto === 'venda') {
            $descricao = (string) (garantiaValorDoItem($item, 'descricao') ?? garantiaValorDoItem($item, 'nome') ?? ('Item #' . $idItem));
            $descricaoHtml = htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8');
            return new HtmlSeguro('<form method="post" action="' . $rotaHtml . '" class="flex min-w-0 flex-col gap-1" data-garantia-autosave="1" data-garantia-id="' . $idItemHtml . '" data-garantia-nome="' . $descricaoHtml . '">'
                . $csrfHidden
                . '<input type="hidden" name="' . $campoOperacao . '" value="' . $idOperacaoHtml . '">'
                . '<input type="hidden" name="idItem" value="' . $idItemHtml . '">'
                . '<label class="sr-only" for="' . $campoDias . '">Prazo de garantia de ' . $descricaoHtml . ', em dias</label>'
                . '<div class="flex items-center gap-1">'
                . '<input id="' . $campoDias . '" name="dias" type="number" min="0" step="1" inputmode="numeric" value="' . $diasHtml . '" class="block w-20 rounded-sm border bg-field px-2 py-1 text-right text-body-md text-text placeholder:text-muted max-sm:min-h-11 focus:outline-3 focus:outline-offset-0 focus:outline-ring focus:shadow-field-focus border-input" data-garantia-dias required>'
                . '<span class="text-caption text-muted">dias</span>'
                . '</div>'
                . '<small class="text-caption text-muted" data-garantia-item-status role="status" aria-live="polite">Salvo</small>'
                . '<small class="text-caption text-warning-ink" data-garantia-zero-aviso' . ($dias === 0 ? '' : ' hidden') . ' aria-live="polite">' . htmlspecialchars(garantiaAvisoZero(), ENT_QUOTES, 'UTF-8') . '</small>'
                . '</form>');
        }

        $tipoHtml = htmlspecialchars($contexto, ENT_QUOTES, 'UTF-8');
        $chaveHtml = htmlspecialchars($contexto . ':' . $idItem, ENT_QUOTES, 'UTF-8');
        $nomeItem = (string) (garantiaValorDoItem($item, 'descricao') ?? garantiaValorDoItem($item, 'nome') ?? ('Item #' . $idItem));
        $nomeItemHtml = htmlspecialchars($nomeItem, ENT_QUOTES, 'UTF-8');

        return new HtmlSeguro('<form method="post" action="' . $rotaHtml . '" class="grid min-w-44 gap-1" data-os-garantia-item="1" data-os-garantia-id="' . $chaveHtml . '" data-garantia-tipo="' . $tipoHtml . '" data-garantia-nome="' . $nomeItemHtml . '">'
            . $csrfHidden
            . '<input type="hidden" name="idOs" value="' . $idOperacaoHtml . '">'
            . '<input type="hidden" name="idItem" value="' . $idItemHtml . '">'
            . '<input type="hidden" name="tipo" value="' . $tipoHtml . '">'
            . '<label class="sr-only" for="' . $campoDias . '">Prazo de garantia de ' . $nomeItemHtml . ', em dias</label>'
            . '<div class="flex items-center gap-1">'
            . '<input id="' . $campoDias . '" name="dias" type="number" min="0" step="1" inputmode="numeric" value="' . $diasHtml . '" class="form-input" data-garantia-dias required>'
            . '<span class="text-caption text-muted">dias</span>'
            . '</div>'
            . '<small class="text-caption text-muted" data-garantia-item-status role="status" aria-live="polite">Salvo</small>'
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

if (! function_exists('garantiaAvisoZero')) {
    function garantiaAvisoZero(): string
    {
        return 'Prazo informado: 0 dias.';
    }
}

if (! function_exists('garantiaDataEntregaEfetiva')) {
    /** Normaliza a data efetiva da entrega (Y-m-d) ou devolve null quando ausente/inválida. */
    function garantiaDataEntregaEfetiva($dataEntrega): ?string
    {
        $iso = dataIsoParaYmd(substr(trim((string) $dataEntrega), 0, 10));
        if ($iso === null || $iso === '0000-00-00') {
            return null;
        }

        return $iso;
    }
}

if (! function_exists('garantiaHojeYmd')) {
    /** Hoje em Y-m-d no fuso operacional; aceita override explícito para testes. */
    function garantiaHojeYmd(?string $hojeYmd = null, ?DateTimeZone $fuso = null): string
    {
        $fuso = $fuso ?? new DateTimeZone('America/Sao_Paulo');
        if ($hojeYmd !== null) {
            $iso = dataIsoParaYmd(substr(trim((string) $hojeYmd), 0, 10));
            if ($iso !== null) {
                return $iso;
            }
        }

        return (new DateTimeImmutable('now', $fuso))->format('Y-m-d');
    }
}

if (! function_exists('garantiaEstadoPorItem')) {
    /**
     * Estado de garantia de UM item da OS a partir do snapshot e da data
     * efetiva de entrega. Sem efeitos colaterais e sem consultar o catálogo.
     *
     * @param object|array $item       linha do item com garantia_dias_snapshot
     * @param string|null  $dataEntrega data efetiva (Y-m-d) ou null
     * @param string|null  $hojeYmd     override de hoje para testes
     * @return array{snapshot: ?int, prazo: int, prazo_texto: string, data_vencimento: ?string, data_vencimento_br: ?string, dias_restantes: ?int, dias_vencidos: ?int, situacao: string, situacao_texto: string}
     */
    function garantiaEstadoPorItem($item, ?string $dataEntrega, ?string $hojeYmd = null): array
    {
        $snapshotBruto = garantiaValorDoItem($item, 'garantia_dias_snapshot');
        $snapshot = $snapshotBruto === null ? null : garantiaNormalizarDias($snapshotBruto);
        $prazo = $snapshot === null ? 0 : $snapshot;
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $hoje = garantiaHojeYmd($hojeYmd, $fuso);
        $base = garantiaDataEntregaEfetiva($dataEntrega);

        $estado = [
            'snapshot' => $snapshot,
            'prazo' => $prazo,
            'prazo_texto' => $snapshot === null
                ? 'Não informado'
                : $prazo . ' ' . ($prazo === 1 ? 'dia' : 'dias'),
            'data_vencimento' => null,
            'data_vencimento_br' => null,
            'dias_restantes' => null,
            'dias_vencidos' => null,
            'situacao' => 'nao_informado',
            'situacao_texto' => 'Informação histórica indisponível',
        ];

        // Histórico legado (snapshot NULL): não inventa prazo nem vencimento.
        if ($snapshot === null) {
            return $estado;
        }

        // Zero registrado: sem data de vencimento, não entra no agregado.
        if ($prazo === 0) {
            $estado['situacao'] = 'zero_registrado';
            $estado['situacao_texto'] = '0 dias registrados';
            return $estado;
        }

        // Sem data efetiva: aguardando entrega, sem vencimento fictício.
        if ($base === null) {
            $estado['situacao'] = 'aguardando_entrega';
            $estado['situacao_texto'] = 'Aguardando entrega';
            return $estado;
        }

        $dataBase = DateTimeImmutable::createFromFormat('!Y-m-d', $base, $fuso);
        $dataHoje = DateTimeImmutable::createFromFormat('!Y-m-d', $hoje, $fuso);
        if (! $dataBase || ! $dataHoje) {
            $estado['situacao'] = 'nao_informado';
            return $estado;
        }

        try {
            $vencimento = $dataBase->add(new DateInterval('P' . $prazo . 'D'));
        } catch (Throwable $erro) {
            $estado['situacao'] = 'nao_informado';
            return $estado;
        }

        $diferenca = (int) $dataHoje->diff($vencimento)->format('%r%a');
        $estado['data_vencimento'] = $vencimento->format('Y-m-d');
        $estado['data_vencimento_br'] = $vencimento->format('d/m/Y');

        if ($diferenca === 0) {
            $estado['situacao'] = 'vence_hoje';
            $estado['situacao_texto'] = 'Vence hoje';
            $estado['dias_restantes'] = 0;
            return $estado;
        }

        if ($diferenca > 0) {
            $estado['situacao'] = 'vigente';
            $estado['situacao_texto'] = 'Restam ' . $diferenca . ' ' . ($diferenca === 1 ? 'dia' : 'dias');
            $estado['dias_restantes'] = $diferenca;
            return $estado;
        }

        $diasVencidos = -$diferenca;
        $estado['situacao'] = 'vencido';
        $estado['situacao_texto'] = 'Venceu há ' . $diasVencidos . ' ' . ($diasVencidos === 1 ? 'dia' : 'dias');
        $estado['dias_vencidos'] = $diasVencidos;

        return $estado;
    }
}

if (! function_exists('garantiaUltimoVencimento')) {
    /** Maior data de vencimento entre os itens com prazo positivo e entrega definida. */
    function garantiaUltimoVencimento(array $itens, ?string $dataEntrega, ?string $hojeYmd = null): ?string
    {
        $ultimo = null;
        foreach ($itens as $item) {
            $estado = garantiaEstadoPorItem($item, $dataEntrega, $hojeYmd);
            if ($estado['data_vencimento'] === null) {
                continue;
            }
            if ($ultimo === null || strcmp($estado['data_vencimento'], $ultimo) > 0) {
                $ultimo = $estado['data_vencimento'];
            }
        }

        return $ultimo;
    }
}

if (! function_exists('garantiaMaiorData')) {
    /** Maior data Y-m-d entre as informadas; null se nenhuma for válida. */
    function garantiaMaiorData(?string $a, ?string $b): ?string
    {
        $a = $a !== null ? substr(trim((string) $a), 0, 10) : null;
        $b = $b !== null ? substr(trim((string) $b), 0, 10) : null;
        if ($a === null || $a === '') {
            return $b !== null && $b !== '' ? $b : null;
        }
        if ($b === null || $b === '') {
            return $a;
        }

        return strcmp($a, $b) >= 0 ? $a : $b;
    }
}

if (! function_exists('garantiaPillPorItem')) {
    /**
     * Pill da coluna "Venc. garantia" para OS com garantia por item.
     * - sem entrega efetiva → "Aguardando entrega" (neutro)
     * - nenhum prazo positivo → "Zero registrado" (neutro)
     * - data geral calculável → data + variante vigente/vencido
     *
     * @return array{label: string, variant: string, dot: bool, title?: string, attrs?: array}
     */
    function garantiaPillPorItem(?string $dataEntrega, ?string $vencimentoGeral, ?string $hojeYmd = null): array
    {
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $hoje = garantiaHojeYmd($hojeYmd, $fuso);
        $base = garantiaDataEntregaEfetiva($dataEntrega);

        if ($base === null) {
            return [
                'label' => 'Aguardando entrega',
                'variant' => 'neutral',
                'dot' => false,
                'title' => 'Aguardando a data efetiva de entrega',
            ];
        }

        $vencimento = $vencimentoGeral !== null ? substr(trim((string) $vencimentoGeral), 0, 10) : null;
        if ($vencimento === null || $vencimento === '') {
            return [
                'label' => 'Zero registrado',
                'variant' => 'neutral',
                'dot' => false,
                'title' => 'Todos os prazos comerciais são zero',
            ];
        }

        $vencData = DateTimeImmutable::createFromFormat('!Y-m-d', $vencimento, $fuso);
        $hojeData = DateTimeImmutable::createFromFormat('!Y-m-d', $hoje, $fuso);
        if (! $vencData || ! $hojeData) {
            return [
                'label' => 'Aguardando entrega',
                'variant' => 'neutral',
                'dot' => false,
                'title' => 'Data inválida para cálculo',
            ];
        }

        $dias = (int) $hojeData->diff($vencData)->format('%r%a');
        $dataFormatada = $vencData->format('d/m/Y');

        if ($dias === 0) {
            return [
                'label' => $dataFormatada,
                'variant' => 'warning',
                'dot' => false,
                'title' => 'Vence hoje',
            ];
        }

        if ($dias > 0) {
            return [
                'label' => $dataFormatada,
                'variant' => 'success',
                'dot' => false,
                'title' => 'Último vencimento comercial: ' . $dataFormatada . ' (restam ' . $dias . ' dias)',
            ];
        }

        return [
            'label' => $dataFormatada,
            'variant' => 'danger',
            'dot' => false,
            'title' => 'Último vencimento comercial: ' . $dataFormatada . ' (venceu há ' . (-$dias) . ' dias)',
        ];
    }
}

if (! function_exists('garantiaItensNormalizados')) {
    /**
     * Normaliza os itens de uma OS para exibição do CONTROLE DE GARANTIAS.
     *
     * @param list<object|array> $itens  itens com tipo, nome e garantia_dias_snapshot
     * @param string|null        $dataEntrega
     * @param string|null        $hojeYmd
     * @return list<array{tipo: string, nome: string, prazo: ?int, prazo_texto: string, vencimento: ?string, vencimento_br: ?string, dias_restantes: ?int, dias_vencidos: ?int, situacao: string, situacao_texto: string, variant: string}>
     */
    function garantiaItensNormalizados(array $itens, ?string $dataEntrega, ?string $hojeYmd = null): array
    {
        $normalizados = [];
        foreach ($itens as $item) {
            $estado = garantiaEstadoPorItem($item, $dataEntrega, $hojeYmd);
            $tipo = (string) (garantiaValorDoItem($item, 'tipo') ?? 'produto');
            $nome = trim((string) (garantiaValorDoItem($item, 'nome') ?? garantiaValorDoItem($item, 'descricao') ?? ''));

            $variantes = [
                'vigente' => 'success',
                'vence_hoje' => 'warning',
                'vencido' => 'danger',
                'aguardando_entrega' => 'neutral',
                'zero_registrado' => 'neutral',
                'nao_informado' => 'neutral',
            ];

            $normalizados[] = [
                'tipo' => $tipo === 'servico' ? 'Serviço' : 'Produto',
                'nome' => $nome !== '' ? $nome : ('Item #' . (int) (garantiaValorDoItem($item, 'idItem') ?? 0)),
                'prazo' => $estado['snapshot'],
                'prazo_texto' => $estado['prazo_texto'],
                'vencimento' => $estado['data_vencimento'],
                'vencimento_br' => $estado['data_vencimento_br'],
                'dias_restantes' => $estado['dias_restantes'],
                'dias_vencidos' => $estado['dias_vencidos'],
                'situacao' => $estado['situacao'],
                'situacao_texto' => $estado['situacao_texto'],
                'variant' => $variantes[$estado['situacao']] ?? 'neutral',
            ];
        }

        return $normalizados;
    }
}

if (! function_exists('garantiaResumoItens')) {
    /**
     * Situação geral de um conjunto de itens de garantia (usada no cartão e
     * no modal de retorno por serial).
     *
     * @param list<array> $itensNormalizados saída de garantiaItensNormalizados
     * @return array{tem_vigente: bool, tem_vencido: bool, todos_vencidos: bool, todos_zero: bool, aguardando_entrega: bool, resumo: string}
     */
    function garantiaResumoItens(array $itensNormalizados): array
    {
        $temVigente = false;
        $temVencido = false;
        $temZero = false;
        $temAguardando = false;
        $temIndefinido = false;

        foreach ($itensNormalizados as $item) {
            switch ((string) ($item['situacao'] ?? '')) {
                case 'vigente':
                case 'vence_hoje':
                    $temVigente = true;
                    break;
                case 'vencido':
                    $temVencido = true;
                    break;
                case 'zero_registrado':
                    $temZero = true;
                    break;
                case 'aguardando_entrega':
                    $temAguardando = true;
                    break;
                default:
                    $temIndefinido = true;
                    break;
            }
        }

        $todosZero = $temZero && ! $temVigente && ! $temVencido && ! $temAguardando && ! $temIndefinido;
        $todosVencidos = $temVencido && ! $temVigente;
        $aguardando = $temAguardando && ! $temVigente && ! $temVencido && ! $temZero && ! $temIndefinido;

        if ($temVigente && $temVencido) {
            $resumo = 'Há itens com prazos vigentes e itens vencidos';
        } elseif ($temVigente) {
            $resumo = 'Há itens com prazos vigentes';
        } elseif ($todosVencidos) {
            $resumo = 'Todos os prazos comerciais vencidos';
        } elseif ($todosZero) {
            $resumo = 'Zero registrado';
        } elseif ($aguardando) {
            $resumo = 'Aguardando entrega';
        } else {
            $resumo = 'Informação histórica indisponível';
        }

        return [
            'tem_vigente' => $temVigente,
            'tem_vencido' => $temVencido,
            'todos_vencidos' => $todosVencidos,
            'todos_zero' => $todosZero,
            'aguardando_entrega' => $aguardando,
            'resumo' => $resumo,
        ];
    }
}

if (! function_exists('garantiaClassificarStatusItem')) {
    /**
     * Traduz o estado agregado dos itens para o vocabulário que o JS do
     * cartão serial já conhece: vigente, vencida, sem_garantia_registrada,
     * indeterminada e misto (itens com prazos diferentes).
     */
    function garantiaClassificarStatusItem(array $itensNormalizados): string
    {
        $resumo = garantiaResumoItens($itensNormalizados);
        if ($resumo['tem_vigente'] && $resumo['tem_vencido']) {
            return 'misto';
        }
        if ($resumo['tem_vigente']) {
            return 'vigente';
        }
        if ($resumo['todos_vencidos']) {
            return 'vencida';
        }
        if ($resumo['todos_zero']) {
            return 'sem_garantia_registrada';
        }

        return 'indeterminada';
    }
}

if (! function_exists('garantiaEnriquecerHistoricoSerial')) {
    /**
     * Enriquecce a lista de OSs históricas com snapshots de garantia por item
     * e com o agregado MAX. Recebe a lista de rows e o model Os_model
     * (ou equivalente com getItensGarantiaDasOs).
     *
     * @param array  $rows
     * @param object $os_model instância com método getItensGarantiaDasOs(array $osIds): array
     * @return array
     */
    function garantiaEnriquecerHistoricoSerial(array $rows, $os_model): array
    {
        if (! is_object($os_model) || ! method_exists($os_model, 'getItensGarantiaDasOs') || $rows === []) {
            return $rows;
        }

        $osIds = [];
        foreach ($rows as $linha) {
            if (isset($linha->idOs)) {
                $osIds[] = (int) $linha->idOs;
            }
        }

        $itensPorOs = [];
        foreach ($os_model->getItensGarantiaDasOs($osIds) as $itemGarantia) {
            $itensPorOs[(int) $itemGarantia->osId][] = $itemGarantia;
        }

        foreach ($rows as $row) {
            $itensDaOs = $itensPorOs[(int) $row->idOs] ?? [];
            $row->itensGarantia = garantiaItensNormalizados($itensDaOs, $row->dataEntrega ?? null);
            $row->vencimentoGarantiaGeral = garantiaUltimoVencimento($itensDaOs, $row->dataEntrega ?? null);
            $row->garantiaResumo = garantiaResumoItens($row->itensGarantia);
        }

        return $rows;
    }
}