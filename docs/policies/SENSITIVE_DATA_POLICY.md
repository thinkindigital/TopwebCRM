---
doc_id: sensitive-data-policy
type: policy
status: active
authority: canonical
scope: topwebcrm
last_verified_commit: 205c296
update_triggers: [sensitive-field, masking, resource, search, export, file]
related: [docs/policies/SECURITY_POLICY.md, docs/policies/AUTHORIZATION_POLICY.md]
---

# Sensitive Data Policy

`users.can_view_sensitive_data` é a concessão individual para visão integral.
Role administrativa não a substitui e credenciais nunca são expostas.

Sem concessão, o sistema mascara ou omite telefone, e-mail, documento, endereço,
dados financeiros, observações estratégicas, conteúdo classificado,
identificadores externos e metadados sensíveis. A saída deve ser sanitizada na
fonte; esconder HTML não protege o payload.

Arquivos sensíveis ficam no disco privado e só saem por rota autenticada que
revalida acesso à entidade e a concessão. Mídia projetada do TopwebChat reutiliza
o mesmo objeto privado. Até existir classificação de conteúdo confiável, todo
anexo, inclusive imagem, exige acesso contextual e concessão individual. Esta
regra supera a exceção temporária do ADR 0007.

Busca, autocomplete, filtros e contadores não podem funcionar como oráculo da
existência ou do valor protegido.
