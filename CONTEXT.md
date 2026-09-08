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

**Conta WhatsApp (planejada)**: identidade duradoura do numero proprio autenticado, confirmada pelo campo `phone` do OpenWA. E dona do historico local e nao se confunde com uma conexao substituivel.

**Sessao OpenWA**: conexao remota descartavel identificada pelo UUID retornado pelo OpenWA. O nome e apenas uma identificacao humana; uma nova Sessao OpenWA pode substituir outra da mesma Conta WhatsApp.

**Instancia (Instance)**: modelo atual que concentra configuracao local, vinculo com Sessao OpenWA, UUID, URL, credenciais e estado. Deve ser separado da Conta WhatsApp duradoura.

**Sessao Padrao (planejada)**: Instancia escolhida para iniciar novas Conversas quando nenhuma sessao especifica for selecionada.

**Conversa (Conversation)**: historico local entre uma Conta WhatsApp e uma Identidade Remota, vinculado a Pessoa, Lead quando conhecido e ao Dono do Lead. Atualmente o codigo ainda a vincula diretamente a uma Instancia.

**Fila Sem Atendente**: conjunto operacional de Conversas abertas com `assigned_user_id` vazio. Fica visivel para agentes autorizados e a primeira Mensagem enviada por um agente assume a Conversa de forma atomica.

**Mensagem (Message)**: registro local de comunicacao recebida ou enviada, com tipo, conteudo, estado e identificador externo.

**Identidade Remota**: identificador WhatsApp normalizado, armazenado de forma protegida e associado a Pessoa somente quando houver correspondencia inequivoca.

**Quarentena de Identidade (planejada)**: estado administrativo de uma Conversa cuja Identidade Remota e desconhecida, ambigua ou nao resolvida. Nao cria Pessoa automaticamente.

**Historico Importado**: mensagens anteriores obtidas do OpenWA. Nao gera Atendimento WhatsApp retroativo.

**Arquivamento de Sessao (planejado)**: retirada de uma Sessao OpenWA da operacao, com logout remoto retentavel, sem apagar Conta WhatsApp, Conversas, Mensagens, notas, midias ou Activities.

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
