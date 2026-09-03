# Issue tracker

- Repositório: https://github.com/thinkindigital/TopwebCRM
- Tracker oficial: GitHub Issues
- Roadmap resumido: `ORCHESTRATOR-ROADMAP.md`
- IDs de Epic: `E##`, estáveis e não reutilizáveis

Cada Issue de implementação deve conter objetivo, escopo, critérios de aceite verificáveis, dependências por URL, riscos de segurança e evidência exigida. `.scratch/` é área temporária de elaboração e não substitui a Issue publicada.

## Política de branches

Branch representa uma entrega coesa e revisável, não cada microcorreção. Issues relacionadas podem compartilhar branch e PR quando pertencem ao mesmo módulo, dependem do mesmo rollout e podem ser validadas juntas. Abra outra branch somente quando houver independência de release, risco ou revisão, conflito de cronograma, ou quando a branch atual já tiver PR/escopo incompatível. O PR deve mapear explicitamente todas as Issues atendidas e distinguir o que foi concluído do que permaneceu pendente.

## Relatos públicos e dados sensíveis

As Issues, Pull Requests e seus comentários são tratados como superfícies públicas. O relato deve conter evidência técnica suficiente para reprodução e auditoria, mas nunca o dado de negócio usado durante o teste.

### Não publicar

- nomes, telefones, e-mails, documentos ou outros identificadores de Pessoa, Lead, Organização ou Usuário;
- conteúdo de mensagens, mídias, notas internas ou transcrições;
- credenciais, cookies, tokens, chaves, assinaturas, cabeçalhos de autorização ou valores de `.env`;
- payloads integrais, dumps de banco, HARs ou logs brutos;
- IPs, hostnames privados, caminhos com segredos ou detalhes operacionais que ampliem a superfície de ataque;
- screenshots sem redação e remoção de metadados.

IDs internos, horários exatos e URLs de produção também devem ser omitidos quando permitirem correlacionar um registro real. Use placeholders como `<conversation-id>`, `<contact>` e `<production-host>`.

### Abrir ou atualizar uma Issue

1. Descreva o comportamento observado e o esperado sem identificar a pessoa usada no teste.
2. Informe apenas ambiente genérico, versão, commit/PR público, rota com placeholders e status HTTP necessários.
3. Reduza logs a campos técnicos permitidos e remova conteúdo, identificadores externos, user-agent e contexto de negócio desnecessário.
4. Mantenha evidência sensível somente no ambiente autorizado; na Issue, registre que ela foi validada de forma privada e quem pode reproduzi-la.
5. Se o problema revelar uma vulnerabilidade explorável, use um GitHub Security Advisory privado em vez de uma Issue pública.

### Encerrar uma Issue

O comentário de encerramento deve registrar causa raiz por categoria, novo comportamento, testes executados, referência ao PR/commit e resultado da validação. Não deve reproduzir o payload que causou o erro nem revelar os registros, contas ou contatos usados no teste.

Se um dado sensível for publicado por engano, remova-o do GitHub, preserve somente uma evidência sanitizada e avalie imediatamente rotação de credenciais e revisão do histórico.

## Pendências operacionais conhecidas

Estas pendências são o ponto de partida atual e devem permanecer rastreáveis nas GitHub Issues:

- **Confiabilidade do deploy automático:** a publicação no GHCR e o redeploy pelo Portainer concluíram com sucesso nas entregas mais recentes. A Issue #36 deve acompanhar novas execuções antes de encerrar a falha intermitente; se ela reaparecer, o rollout manual no manager continua sendo o procedimento de contingência.
- **Refinamento da timeline:** a atualização automática sem recarregar a página e a permanência no fim da conversa foram validadas em produção na PR #42. A Issue #22 permanece aberta apenas para refinamento visual, responsividade e cenários de leitura de histórico.
- **Mídia de saída:** o provider OpenWA já suporta envio de mídia, mas o composer do TopwebChat ainda cobre apenas texto. A interface de anexos, limites, pré-visualização e auditoria devem ser implementados antes de considerar esse fluxo completo.
- **Activities e mídia recebida:** as Issues #17 e #46 acompanham a projeção das janelas de atendimento e dos arquivos recebidos. Só podem ser encerradas depois de testes, revisão, imagem aplicada e validação privada em produção.
- **Consolidação histórica:** conversas duplicadas antigas podem existir quando um celular brasileiro foi salvo sem o nono dígito. A normalização nova evita novas duplicatas; a consolidação de registros antigos deve ser feita por migração revisável, sem apagar mensagens.

Uma pendência só deve ser removida depois de evidência reproduzível (teste automatizado, log ou validação no ambiente de produção) e referência ao commit ou Issue correspondente.
