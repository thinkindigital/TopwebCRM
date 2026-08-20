# Evo CRM como referência arquitetural/funcional, não base de código

**Contexto**: Precisávamos de benchmark para funcionalidades modernas (conversas, mensageria, estrutura modular).
**Decisão**: Estudar Evo CRM Community como referência — inspirar melhorias, não copiar arquitetura.
**Por que**: Evo usa monorepo com serviços independentes (auth, CRM, frontend, processor, bot runtime), evolution-api/evolution-go para WhatsApp. Copiar isso exigiria microservicização do Krayin sem justificativa. O valor está em: organização de fluxos conversacionais, acoplamento contato-conversa, experiência de atendimento, padrão de mensageria/histórico.

**Regra prática**: Usar Evo para responder "como esse problema costuma ser resolvido em CRM moderno?" — não "como replicar exatamente esse sistema?".