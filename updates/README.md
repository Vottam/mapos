# Bootstrap e configuração de status de e-mail

## Contrato atual

Instalações novas carregam `banco.sql`. A tabela `configuracoes.config` permanece `VARCHAR(20)` e o seed de `os_email_statuses` usa `INSERT ... SELECT ... WHERE NOT EXISTS`.

O seed inicial é semanticamente:

```json
["Orçamento", "Finalizado", "Faturado"]
```

A serialização usada no SQL preserva o formato publicado (`["Or\\u00e7amento","Finalizado","Faturado"]`), para manter compatibilidade byte a byte com as instalações atuais.

A operação é idempotente: se `os_email_statuses` já existir, seu valor não é sobrescrito. Isso preserva preferências personalizadas quando o procedimento de inicialização é reaplicado.

## Script histórico substituído

`mission_014B_os_status_email_notify_list.sql` é mantido somente para rastreabilidade histórica e não faz parte do fluxo automático de instalação.

Esse script é superseded/manual-only para o contrato atual porque:

- amplia `configuracoes.config` para `VARCHAR(64)`;
- cria a chave antiga `os_status_email_notify_list`;
- não deve ser executado em instalações que usam `os_email_statuses` e `VARCHAR(20)`.

Não editar nem executar esse script como parte do bootstrap atual. Qualquer futura mudança de configuração deve ser criada em uma alteração nova, revisada e testada em banco descartável.

## Fluxo de instalação

`install/settings.json` aponta para `../banco.sql`; `install/do_install.php` lê o arquivo e executa seu conteúdo via `mysqli::multi_query`. O script histórico não é montado pelo Docker nem executado automaticamente no boot.

## Validação obrigatória

Uma correção de bootstrap deve ser validada em instalação limpa e em banco isolado com preferência preexistente, verificando:

1. criação da chave e JSON inicial válido;
2. leitura pela aplicação;
3. persistência de valor personalizado;
4. recarregamento do valor persistido;
5. execução repetida sem sobrescrever a preferência;
6. ausência de envio de e-mail.
