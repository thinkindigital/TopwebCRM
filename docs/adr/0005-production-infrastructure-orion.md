# Infraestrutura de produção baseada no Setup Orion

**Contexto**: Deploy em Docker Swarm com Portainer Business Edition, Traefik, ferramentas auxiliares.
**Decisão**: Reutilizar rede Traefik confirmada; não reutilizar MySQL/Redis globais sem análise de isolamento; imagem imutável (`Dockerfile.production`) com código, deps Composer, assets compilados; stack parametrizada (`compose.production.yaml`) com secrets externos; volumes externos com nomes estáveis e afinidade por label de nó; migrations separadas de queue/scheduler por variáveis de fase; espera ativa pelo banco, healthchecks, limites de recursos; worker reiniciado pelo Swarm com `retry_after` e grace period superiores ao timeout.
**Por que**: Setup Orion é base operacional validada; isolamento de dados evita vazamento entre stacks; imagem imutável garante reprodutibilidade; secrets externos fora da imagem.

**Riscos**: Volumes locais presos ao nó rotulado (exige placement fixo ou storage compartilhado); rollback de imagem não desfaz migrations; registry necessário em Swarm multinó; segredos publicados anteriormente devem ser rotacionados.