<?php

declare(strict_types=1);

require_once APPPATH . 'helpers/general_helper.php';
require_once APPPATH . 'helpers/os_helper.php';

use PHPUnit\Framework\TestCase;

final class OsFormHydrationTest extends TestCase
{
    private function osPersistida(): object
    {
        return (object) [
            'nomeCliente' => 'Cliente persistido',
            'clientes_id' => 2,
            'nome' => 'Técnico persistido',
            'usuarios_id' => 3,
            'status' => 'Entregue',
            'dataInicial' => '2026-10-21',
            'dataFinal' => '2026-10-21',
            'garantia' => '180',
            'refGarantia' => 'Termo padrão',
            'garantias_id' => 4,
            'garantia_retorno' => 0,
            'garantia_origem_os_id' => null,
            'descricaoProduto' => '<p>Tela sem luz de fundo</p>',
            'defeito' => '<p>Não liga</p>',
            'observacoes' => 'Aguardar aprovação',
            'laudoTecnico' => 'Diagnóstico concluído',
        ];
    }

    public function testGetSemPostHidrataTodosOsCamposPersistidos(): void
    {
        $valores = osValoresDoFormulario(osPostParaFormulario([]), $this->osPersistida());

        $this->assertSame('Cliente persistido', $valores['cliente']);
        $this->assertSame('2', $valores['clientes_id']);
        $this->assertSame('Técnico persistido', $valores['tecnico']);
        $this->assertSame('3', $valores['usuarios_id']);
        $this->assertSame('Entregue', $valores['status']);
        $this->assertSame('2026-10-21', $valores['dataInicial']);
        $this->assertSame('2026-10-21', $valores['dataFinal']);
        $this->assertSame('180', $valores['garantia']);
        $this->assertSame('Termo padrão', $valores['termoGarantia']);
        $this->assertSame('4', $valores['garantias_id']);
        $this->assertSame('Tela sem luz de fundo', $valores['descricaoProduto']);
        $this->assertSame('Não liga', $valores['defeito']);
        $this->assertSame('Aguardar aprovação', $valores['observacoes']);
        $this->assertSame('Diagnóstico concluído', $valores['laudoTecnico']);
    }

    public function testOrigemNulaNaoCriaOSZeroEOrigemValidaEConservada(): void
    {
        $semOrigem = osValoresDoFormulario(null, $this->osPersistida());
        $this->assertSame('', $semOrigem['garantia_origem_os_id']);

        $comOrigem = $this->osPersistida();
        $comOrigem->garantia_origem_os_id = 321;
        $valores = osValoresDoFormulario(null, $comOrigem);
        $this->assertSame('321', $valores['garantia_origem_os_id']);
    }

    public function testPostRealTemPrecedenciaSobreOSPersistida(): void
    {
        $post = [
            'cliente' => 'Cliente escolhido',
            'clientes_id' => '9',
            'tecnico' => 'Técnico escolhido',
            'usuarios_id' => '8',
            'status' => 'Em Andamento',
            'dataInicial' => '2026-11-01',
            'dataFinal' => '2026-11-03',
            'garantia' => '30',
            'garantia_origem_os_id' => '',
            'descricaoProduto' => 'Novo texto',
        ];

        $valores = osValoresDoFormulario(osPostParaFormulario($post), $this->osPersistida());

        $this->assertSame('Cliente escolhido', $valores['cliente']);
        $this->assertSame('9', $valores['clientes_id']);
        $this->assertSame('Em Andamento', $valores['status']);
        $this->assertSame('30', $valores['garantia']);
        $this->assertSame('Novo texto', $valores['descricaoProduto']);
        $this->assertSame('', $valores['defeito']);
    }

    public function testDatasDoFormularioAceitamFormatoV5ELegadoERejeitamInvalidas(): void
    {
        $this->assertSame('2026-10-21', osDataParaBanco('2026-10-21'));
        $this->assertSame('2026-10-21', osDataParaBanco('21/10/2026'));
        $this->assertNull(osDataParaBanco('2026-02-30'));
        $this->assertNull(osDataParaBanco('31/04/2026'));
        $this->assertNull(osDataParaBanco(''));
        $this->assertNull(osDataParaBanco('2026/10/21'));
    }
}
