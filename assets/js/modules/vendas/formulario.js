// Formulário de venda (views/vendas/formulario.php, #2843): liga os
// autocompletes de cliente e vendedor (lib/autocomplete.js) e a busca de
// cliente por CPF/CNPJ compartilhada com a OS (modules/clientes/
// documento-busca.js): o texto fica no campo visível e o id escolhido no
// campo oculto ao lado. A validação, o carregamento do botão e o envio ficam
// com o módulo formulario/padrao, no <form>.

import { iniciarAutocompletes } from '../../lib/autocomplete.js';
import { iniciarBuscaDocumento } from '../clientes/documento-busca.js';

export default function iniciar(elemento) {
    iniciarBuscaDocumento(elemento);
    iniciarAutocompletes(elemento);
}
