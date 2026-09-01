# Issue tracker

- Repositório: https://github.com/thinkindigital/TopwebCRM
- Tracker oficial: GitHub Issues
- Roadmap resumido: `ORCHESTRATOR-ROADMAP.md`
- IDs de Epic: `E##`, estáveis e não reutilizáveis

Cada Issue de implementação deve conter objetivo, escopo, critérios de aceite verificáveis, dependências por URL, riscos de segurança e evidência exigida. `.scratch/` é área temporária de elaboração e não substitui a Issue publicada.

## Pendências operacionais conhecidas

Estas pendências são o ponto de partida atual e devem permanecer rastreáveis nas GitHub Issues:

- **Deploy automático pelo Portainer:** a publicação da imagem no GHCR funciona, mas o runner do GitHub não consegue conectar ao endpoint público do Portainer. Até corrigir a conectividade/rede, o rollout pode exigir `docker service update` no manager.
- **Atualização em tempo real:** o CRM usa polling autenticado a cada três segundos, sem WebSocket. Deve ser validado com uma mensagem recebida sem recarregar a página; se houver falha, registrar status HTTP e erro do console do navegador.
- **Mídia de saída:** o provider OpenWA já suporta envio de mídia, mas o composer do TopwebChat ainda cobre apenas texto. A interface de anexos, limites, pré-visualização e auditoria devem ser implementados antes de considerar esse fluxo completo.
- **Consolidação histórica:** conversas duplicadas antigas podem existir quando um celular brasileiro foi salvo sem o nono dígito. A normalização nova evita novas duplicatas; a consolidação de registros antigos deve ser feita por migração revisável, sem apagar mensagens.

Uma pendência só deve ser removida depois de evidência reproduzível (teste automatizado, log ou validação no ambiente de produção) e referência ao commit ou Issue correspondente.
