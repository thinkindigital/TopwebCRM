# TopwebChat

TopwebChat é o módulo nativo do TopwebCRM para operar conversas WhatsApp dentro do contexto comercial. O OpenWA mantém sessão e transporte; o CRM mantém autorização, relacionamento com Pessoa/Lead, histórico local, atribuição, notas e trilha dos eventos.

## Estado implementado

O código atual contém:

- domínio persistente de `Instance`, `Conversation`, `Message`, `InternalNote` e `WebhookEvent`;
- inbox e timeline, abertura a partir de Pessoa ou Lead, atribuição e mudança de etapa;
- envio de texto pela fila, estados da mensagem e retry manual restrito;
- webhook OpenWA com validação HMAC sobre o corpo bruto e persistência idempotente;
- resolução de identidades privadas `@lid` pela API oficial do OpenWA antes do vínculo com Pessoa;
- download assíncrono de mídia recebida para o disco privado e visualização autorizada na timeline;
- reconciliação periódica de instância e sincronização de histórico conhecido;
- cadastro de instância, saúde do provedor, listagem das sessões remotas e configuração do webhook;
- autorização no backend baseada no escopo do Lead/Pessoa;
- mascaramento de dados sensíveis e concessão administrativa individual;
- adapter `OpenWaProvider` por trás do contrato `MessagingProvider`.

Os testes de feature em `tests/Feature/TopwebChat` cobrem o contrato HTTP principal, Settings, webhook, histórico, retry/timeline e geração da URL pública. Isso não substitui o smoke test com uma sessão WhatsApp real em cada release.

Algumas capacidades existem no contrato do provider, mas ainda não formam um fluxo completo na interface. Entre elas estão os controles de ciclo da sessão, QR/pairing, **envio** de mídia e os recursos ampliados de grupos, chamadas e perfil. O recebimento e a consulta autorizada de mídia estão implementados; o ciclo de “Atendimento WhatsApp” em Activities, quarentena de identidades não resolvidas e automações avançadas continuam sujeitos ao roadmap. Não descreva uma dessas capacidades como entregue apenas porque há um método no adapter.

OpenWA é um gateway comunitário não oficial, baseado em clientes de engenharia reversa. Existe risco não nulo de restrição da conta; use número dedicado, consentimento dos destinatários, limites de envio e um canal alternativo para fluxos críticos. O engine padrão desta stack é `whatsapp-web.js`, que prioriza um comportamento mais próximo ao WhatsApp Web ao custo de mais memória por sessão.

## Fronteiras

```text
UI / controllers
    -> autorização e serviços do TopwebChat
    -> banco local e jobs Redis
    -> MessagingProvider
    -> OpenWaProvider
    -> OpenWA

OpenWA
    -> webhook HTTPS + assinatura HMAC
    -> WebhookEvent idempotente
    -> normalização
    -> Instance / Conversation / Message
```

Regras específicas do OpenWA ficam no adapter e nos normalizadores. Controllers validam, autorizam e coordenam; o navegador nunca recebe API key nem segredo HMAC.

## Endereços

O adapter espera uma **URL base sem `/api`**:

| Ambiente | Base do OpenWA cadastrada no CRM |
|---|---|
| app Laravel no host local | `http://localhost:2785` |
| app em Compose na mesma rede do OpenWA | `http://<servico-openwa>:2785` |
| produção, stack Portainer chamada `openwa` | `http://openwa_openwa_api:2785` |

O adapter acrescenta caminhos como `/api/health` e `/api/sessions/{uuid}`. Em produção, prefira o DNS interno da rede `topweb_integrations`; a URL pública do OpenWA fica reservada ao dashboard e à operação administrativa.

O webhook precisa alcançar o CRM por HTTPS:

```text
https://crm.<dominio-do-cliente>/api/topweb-chat/webhooks/openwa/<instance>
```

`TOPWEB_CHAT_PUBLIC_URL` é a fonte preferencial dessa URL e deve representar o domínio público do CRM. Em produção o serviço rejeita bases locais como `localhost`.

## Configuração de uma instância

Pré-requisitos:

- OpenWA saudável;
- sessão criada com UUID válido;
- API key autorizada;
- queue e scheduler do Laravel ativos;
- CRM e OpenWA conectados à mesma rede overlay em produção.

Em **TopwebChat → Configurações**:

1. informe nome, UUID, URL base e API key;
2. habilite e salve a instância;
3. confira a saúde do OpenWA e a listagem de sessões;
4. configure o webhook;
5. solicite a reconciliação;
6. valide uma mensagem enviada, uma recebida e ao menos uma mudança de status.

Ao salvar, a API key, o segredo do webhook e demais atributos sensíveis usam criptografia vinculada à `APP_KEY`. Banco sem a chave correspondente não é uma restauração funcional.

## Fluxos de execução

### Saída

1. O usuário autorizado envia texto pela conversa.
2. O CRM persiste a mensagem outbound e despacha `SendMessage`.
3. O worker chama o OpenWA usando `X-API-Key`.
4. O ID remoto e o estado retornado são gravados.
5. Webhooks posteriores reconciliam ACK, entrega, falha, edição ou revogação.

Uma resposta HTTP de aceite não prova entrega ao destinatário. Timeout após chamada externa pode deixar o resultado como desconhecido; nesse caso nunca faça retry cego.

### Entrada

1. O OpenWA envia o evento ao webhook público.
2. O CRM valida `X-OpenWA-Signature` antes de processar o JSON.
3. O evento é persistido com chave de idempotência.
4. O processador normaliza sessão, conversa, remetente, conteúdo e estado.
5. Identidades `@lid` são resolvidas pelo endpoint de contato do OpenWA; um LID sem correspondência continua bloqueado para revisão, sem adivinhar telefone.
6. Mídias são baixadas por job, limitadas por tamanho e gravadas em `storage/app/private`.
7. A inbox e a timeline passam a ler a cópia local em ordem cronológica e atualizam a conversa aberta a cada três segundos.

A rota de mídia repete autorização da conversa e exige a concessão individual `can_view_sensitive_data`. Ela não fornece URL pública do arquivo nem libera acesso apenas por esconder elementos no navegador.

Todo evento aceito permanece persistido. O processador atual projeta no domínio apenas `message.received`, `message.sent`, `message.ack`, `message.failed` e `session.status`; os demais eventos configurados são armazenados, mas ainda não produzem atualização funcional equivalente no CRM.

### Reconciliação

O scheduler registra:

- `topweb-chat:reconcile` a cada minuto, para estado das instâncias;
- `topweb-chat:reconcile --history` a cada cinco minutos, para conversas conhecidas.

Jobs usam a fila Laravel padrão. As opções `--state`, `--full` e `--limit` não existem no comando atual.

## Regras operacionais

- `ready` com `engineLoaded=true` define uma sessão utilizável para envio.
- `stop` preserva credenciais; `logout` exige novo pareamento.
- O UUID, e não o nome da sessão, identifica as rotas.
- Uma conversa permanece vinculada à sua instância.
- Usuários devem enxergar apenas conversas permitidas pelo escopo do CRM; administrador possui visão global.
- Retry manual só é permitido nas condições validadas pelo backend. Mensagem `unknown` pode ter sido aceita e não deve ser duplicada.
- Logs não devem conter API keys, segredo HMAC ou payload integral com dados pessoais.

## Diagnóstico

A conversa exibe no cabeçalho o horário da última atualização bem-sucedida ou o estado `Falha na atualização`. Eventos do navegador que afetam o chat são gravados, sem conteúdo de mensagens, em:

```text
storage/logs/topweb-chat-client-YYYY-MM-DD.log
```

O canal mantém 14 dias e registra inicialização, divergência entre o último ID recebido e o último ID visível, referência DOM desconectada e exceções do polling. Os mesmos eventos são enviados ao `stderr`, portanto também aparecem nos logs do serviço app no Portainer.

Verifique, nesta ordem:

1. `GET /api/health/ready` no OpenWA público retorna `200`;
2. a stack mostra API, bancos, queue e scheduler sem loop de restart;
3. o CRM resolve `openwa_openwa_api` na rede overlay;
4. a instância local usa o mesmo UUID da sessão e a base sem `/api`;
5. saúde e sessões aparecem em Settings;
6. o webhook remoto aponta para o HTTPS atual do CRM;
7. worker e scheduler estão ativos;
8. logs do CRM e OpenWA não apresentam falha recorrente.

Com acesso ao container do app:

```bash
php artisan migrate:status
php artisan route:list --name=topweb_chat
php artisan schedule:list
php artisan topweb-chat:reconcile
```

Para desenvolvimento local, o webhook só funciona quando o processo OpenWA consegue alcançar a URL do CRM; `localhost` dentro de um container refere-se ao próprio container.

## Critério de saúde funcional

A integração só deve ser liberada quando houver evidência recente de:

- health do OpenWA e da aplicação;
- sessão `ready` com engine carregado;
- webhook HMAC registrado;
- worker e scheduler estáveis;
- envio, recebimento e atualização de status;
- autorização por usuário e bloqueio de acesso direto indevido;
- persistência após restart dos serviços.

- Contrato externo consumido pelo adapter: `docs/topweb-chat/OPENWA.md`.
- Deploy completo: `docs/operations/DEPLOYMENT.md`.
- Decisão arquitetural: `docs/adr/0004-topwebchat-whatsapp-module.md`.
