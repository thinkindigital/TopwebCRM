# Operacao do TopwebChat

## Estado operacional atual

O OpenWA pode operar separadamente em `http://localhost:2785`, mas o Settings atual do TopwebCRM ainda nao descobre sessoes remotas. Ate a implementacao da Epic E03 corrigida, cadastro, QR, webhooks e historico pelo CRM nao devem ser considerados operacionais.

## Diagnostico somente leitura

1. Verificar `GET http://localhost:2785/api/health`.
2. Consultar `GET /api/sessions` com `X-API-Key`.
3. Confirmar `status=ready` e `engineLoaded=true` na sessao usada.
4. Confirmar que existe uma Instance local com `provider=openwa`, `session_uuid` e `base_url` correspondentes.
5. Listar webhooks da sessao e verificar URL, eventos e estado ativo.
6. Inspecionar `storage/logs/laravel.log` sem registrar tokens ou payloads integrais.

## Sessoes

- `stop` preserva credenciais e permite novo `start` sem QR quando a autenticacao continua valida.
- `logout` remove credenciais e exige novo pareamento.
- `delete` remove a sessao remota; nao deve ser confundido com desvincular apenas o registro local.
- Ao reiniciar o OpenWA, sessoes persistidas podem aparecer como `disconnected` quando auto-start estiver desabilitado.
- QR e pairing code sao controles administrativos.

## Filas e scheduler

O codigo atual registra reconciliacao no `TopwebChatServiceProvider`:

- `topweb-chat:reconcile` para estado.
- `topweb-chat:reconcile --history` para historico conhecido.

As opcoes `--state`, `--full` e `--limit` nao existem atualmente. Jobs usam a fila Laravel padrao ate que filas dedicadas sejam implementadas e documentadas.

### Reenvio de mensagens

- `Tentar novamente` aparece somente para outbound `failed` com `provider_instance_not_connected` e sem ID remoto.
- O backend revalida permissao, vinculo com a conversa, estado da mensagem e sessao `ready` sob lock.
- Mensagens `unknown` podem ter sido aceitas antes de um timeout e nunca devem ser reenviadas cegamente.
- Recuperacao automatica de falhas apos a sessao voltar a `ready` ainda nao esta habilitada.

## Integracoes externas

n8n, Meta e Google nao sao operados pelo TopwebCRM. O projeto pode manter exemplos de payload, contratos e testes HTTP para validar compatibilidade, mas retries, filas e regras executadas nessas ferramentas sao apenas requisitos informativos externos.

## Desenvolvimento local

- CRM: `http://127.0.0.1:8000`
- OpenWA: `http://localhost:2785`
- O webhook precisa de URL alcancavel pelo processo OpenWA.
- Nao alterar PHP, Scoop, PATH ou servicos do host como parte de uma correcao do modulo sem autorizacao explicita.

## Criterios de saude futura

Uma sessao somente aparece como operacional no CRM quando:

- o health do OpenWA responde;
- a sessao foi importada ou criada pelo CRM;
- o estado e `ready`;
- `engineLoaded` e verdadeiro;
- o webhook foi registrado e testado;
- o worker e scheduler estao ativos;
- envio, recebimento e status possuem verificacao recente.

## Pos-deploy

```bash
php artisan migrate --force
php artisan optimize:clear
php artisan queue:restart
php artisan route:list --name=topweb_chat
```

Nao documentar testes como existentes ou aprovados enquanto os arquivos e a execucao reproduzivel nao estiverem no repositorio.
