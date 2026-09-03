# Linguagem do TopwebCRM

Este arquivo e exclusivamente um glossario. Regras, fluxos, endpoints e detalhes de implementacao pertencem aos documentos do modulo e ADRs.

## CRM

**TopwebCRM**: fork do Krayin CRM 2.2 voltado a operacao interna, seguranca de dados e comunicacao integrada.

**Pessoa (Person)**: individuo com dados de contato, Organizacao opcional, Leads, Atividades e Tags.

**Organizacao (Organization)**: entidade empresarial que agrupa Pessoas e pode pertencer a um Usuario.

**Lead**: oportunidade comercial vinculada a Pessoa, Usuario, Pipeline e Etapa.

**Pipeline**: sequencia de Etapas percorridas pelo Lead.

**Etapa (Stage)**: posicao do Lead no Pipeline.

**Usuario (User)**: conta humana autenticada no painel administrativo.

**Administrador**: Usuario com escopo administrativo total no dominio correspondente. Administracao do CRM nao implica exposicao de credenciais de integracao no navegador.

**Dono do Lead**: Usuario atribuido ao Lead e fonte de verdade para acesso as conversas vinculadas.

## Seguranca

**Concessao Individual**: autorizacao explicita em `users.can_view_sensitive_data` para visualizar dados sensiveis integrais.

**Dado Sensivel**: informacao privada, de contato, financeira, estrategica ou operacional cuja exposicao fora do escopo autorizado gera risco.

**Mascaramento**: transformacao de saida que preserva contexto operacional sem revelar o valor integral.

**Midia Privada**: anexo armazenado fora da area publica e servido somente por rota autenticada e autorizada.

## TopwebChat

**TopwebChat**: modulo do TopwebCRM que opera conversas WhatsApp, seus vinculos comerciais, autorizacao e historico local.

**OpenWA**: provedor self-hosted responsavel por sessoes WhatsApp, transporte de mensagens e entrega de webhooks.

**Sessao OpenWA**: sessao remota identificada pelo UUID retornado pelo OpenWA. O nome e apenas uma identificacao humana.

**Instancia (Instance)**: configuracao local que vincula o TopwebCRM a uma Sessao OpenWA, incluindo UUID, URL, credenciais e estado.

**Sessao Padrao (planejada)**: Instancia escolhida para iniciar novas Conversas quando nenhuma sessao especifica for selecionada.

**Conversa (Conversation)**: historico local entre uma Instancia e uma Identidade Remota, vinculado a Pessoa, Lead quando conhecido e ao Dono do Lead.

**Mensagem (Message)**: registro local de comunicacao recebida ou enviada, com tipo, conteudo, estado e identificador externo.

**Identidade Remota**: identificador WhatsApp normalizado, armazenado de forma protegida e associado a Pessoa somente quando houver correspondencia inequivoca.

**Quarentena de Identidade (planejada)**: estado administrativo de uma Conversa cuja Identidade Remota e desconhecida, ambigua ou nao resolvida. Nao cria Pessoa automaticamente.

**Historico Importado**: mensagens anteriores obtidas do OpenWA. Nao gera Atendimento WhatsApp retroativo.

**Atendimento WhatsApp**: Activity agregadora aberta pela primeira Mensagem enviada por um Usuario do CRM e encerrada apos 24 horas sem Mensagem real.

**Atendimento Continuado**: novo Atendimento WhatsApp aberto por Mensagem enviada por Usuario do CRM depois do encerramento de um atendimento anterior.

**Mensagem Real**: conteudo enviado ou recebido por uma pessoa. Reacao, ACK, leitura e evento tecnico nao sao Mensagens Reais.

**Relato de Atendimento**: texto unico registrado na Activity para resumir decisoes, duvidas, receios e resultado do atendimento.

**Projecao de Midia do Lead**: vinculo idempotente que apresenta uma midia inbound do TopwebChat como arquivo nativo da Pessoa e do Lead, apontando para o mesmo objeto no storage privado.

**Roleta de Distribuicao**: politica futura que atribui Leads e suas Conversas a Usuarios elegiveis de forma concorrente, justa e auditavel.

## Integracao

**Provedor de Mensageria (Messaging Provider)**: fronteira que isola contratos externos do dominio do TopwebCRM.

**Webhook Event**: registro idempotente e protegido de um evento recebido do provedor.

**Outbox Local**: persistencia da intencao de envio antes da chamada externa, usada para evitar duplicidade em falhas ambiguas.

**Reconciliação**: comparacao e correcao de divergencias entre o estado local e o estado confirmado pelo provedor.
