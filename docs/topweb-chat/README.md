# TopwebChat

Modulo nativo do TopwebCRM para operar conversas WhatsApp vinculadas a Pessoas e Leads. O OpenWA mantem sessao e transporte; o TopwebCRM mantem autorizacao, vinculo comercial, historico local, atividades e trilha operacional.

## Estado atual

O dominio local de `Instance`, `Conversation`, `Message`, `InternalNote` e `WebhookEvent` existe. Tambem existem inbox, notas, atribuicao, envio enfileirado, botao em Pessoa/Lead e um adapter `OpenWaProvider`.

O OpenWA e o unico provedor implementado. O fluxo ainda nao foi validado de ponta a ponta; recursos abaixo devem ser tratados como pendentes ate os respectivos testes passarem.

## Contratos decididos

### Provedor e sessoes

- OpenWA e o unico provedor suportado nesta etapa.
- O CRM podera descobrir, importar e criar varias sessoes OpenWA.
- Uma sessao sera a padrao para novas conversas.
- Cada conversa permanece vinculada a sua sessao; migracao exige acao administrativa explicita.
- Settings deve expor start, stop, logout, QR Code e pairing code sem enviar credenciais ao navegador.

### Autorizacao

- O dono do Lead e a fonte de verdade para acesso a uma conversa vinculada.
- Administradores veem todas as conversas.
- Conversas sem Lead ou responsavel ficam visiveis somente para administradores.
- Transferir o Lead transfere o acesso da conversa de forma atomica e auditavel.
- Listas, URLs diretas, polling, busca, historico, anexos e downloads aplicam a mesma regra no backend.

### Identidade e historico

- Historico importado e associado somente quando a identidade resolve de forma unica para Pessoa/Lead.
- Identidade desconhecida, ambigua ou `@lid` sem telefone vai para quarentena administrativa.
- Importacao nunca cria Pessoa silenciosamente.
- Historico importado nao cria Atendimentos WhatsApp retroativos.

### Atendimento WhatsApp em Activities

- A primeira mensagem enviada por um Usuario do CRM abre uma Activity `Atendimento WhatsApp`.
- Mensagens reais de ambos os lados renovam a janela de 24 horas.
- Reacoes, ACKs, leitura e eventos tecnicos nao renovam a janela.
- A janela fecha automaticamente apos 24 horas sem mensagem real.
- `Activity.comment` guarda um relato textual unico do atendimento.
- Uma nova mensagem enviada pelo Usuario do CRM apos o fechamento abre `Atendimento Continuado`.
- Mensagem recebida sozinha nao abre nem reabre atendimento.
- Uma tabela auxiliar do TopwebChat vinculara Activity, Conversation, sequencia e ultimo evento real.

### Dados sensiveis e anexos

- O responsavel pelo Lead le o conteudo operacional necessario ao atendimento.
- Administrador ou Usuario com concessao individual pode acessar conteudo sensivel integral.
- Texto aplica mascaramento seletivo a documentos, telefones e e-mails detectaveis.
- Cartoes de contato sao protegidos.
- Sem classificacao de conteudo, todo documento e sensivel; imagens permanecem visiveis nesta fase.
- Documento protegido deve ser bloqueado tambem na rota de download.

## Arquitetura alvo

```text
UI/Controller
  -> servicos de aplicacao e autorizacao
  -> jobs e persistencia local
  -> MessagingProvider
  -> OpenWaProvider
  -> OpenWA

OpenWA webhook
  -> HMAC sobre corpo bruto
  -> WebhookEvent idempotente
  -> normalizacao de evento
  -> Conversation/Message/Instance
```

Logica especifica do OpenWA nao deve escapar do adapter e normalizadores. Controllers apenas validam, autorizam e coordenam.

## Implementacao em ordem

1. Baseline de testes e contratos OpenWA.
2. Descoberta, importacao, criacao e ciclo de sessao em Settings.
3. Webhook, envio, estados e leitura compativeis com OpenWA.
4. Autorizacao pelo dono do Lead.
5. Historico e quarentena.
6. Inbox, acao WhatsApp e midia privada.
7. Atendimento WhatsApp em Activities.
8. Roleta de distribuicao em Epic propria.

Detalhes do contrato externo: `docs/topweb-chat/OPENWA.md`.
Operacao e diagnostico: `docs/topweb-chat/OPERATIONS.md`.
Decisao do modulo: `docs/adr/0004-topwebchat-whatsapp-module.md`.
