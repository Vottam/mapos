<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class ClienteDisplayTest extends TestCase
{
    public function testDecodesOneHistoricalEntityLayerWithoutChangingPersistedData(): void
    {
        self::assertSame('Empresa A & B', nomeClienteExibicao('Empresa A &amp; B'));
        self::assertSame('Empresa A &amp; B', nomeClienteExibicao('Empresa A &amp;amp; B'));
    }

    public function testLeavesPlainAmpersandAndSpecialTextAsText(): void
    {
        $nome = 'A & B "C" <D>';

        self::assertSame($nome, nomeClienteExibicao($nome));
        self::assertSame('A &amp; B &quot;C&quot; &lt;D&gt;', e($nome));
    }

    public function testEntityInputIsDecodedThenEscapedAgainForHtml(): void
    {
        $nome = nomeClienteExibicao('A &quot;B&quot; &lt;C&gt; &amp; D');

        self::assertSame('A "B" <C> & D', $nome);
        self::assertSame('A &quot;B&quot; &lt;C&gt; &amp; D', e($nome));
        self::assertStringNotContainsString('<C>', e($nome));
    }
}
