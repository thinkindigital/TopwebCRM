## Parent
Epic: E06 - [.scratch/e06-reconciliation-complete/PRD.md]

## What to build
Domínio Grupos WhatsApp (separado do fluxo 1:1):
1. **Entidades**:
   - `TopwebChatGroup`: `id`, `instance_id`, `group_jid` (criptografado), `subject`, `description`, `owner_jid`, `created_at`, `settings` (JSON: announce, locked, etc.)
   - `TopwebChatGroupParticipant`: `group_id`, `participant_jid`, `is_admin`, `joined_at`
2. **Webhooks** (OpenWA):
   - `group.join` / `group.leave` / `group.update` / `group.join_request`
   - Jobs: `ProcessGroupJoin`, `ProcessGroupLeave`, `ProcessGroupUpdate`, `ProcessGroupJoinRequest`
3. **API OpenWA**:
   - `GET /api/sessions/:sessionId/groups` → lista grupos
   - `GET /api/sessions/:sessionId/groups/:groupId` → detalhes + participantes
   - `POST /api/sessions/:sessionId/groups` → criar grupo
   - `POST /api/sessions/:sessionId/groups/:groupId/participants` → add/remove/promote/demote
   - `GET/POST /api/sessions/:sessionId/groups/:groupId/membership-requests` → aprovar/recusar
4. **UI Admin**: menu TopwebChat > Grupos (apenas admins/operadores com `topweb_chat.groups`)
5. **ACL**: `topweb_chat.groups.view`, `topweb_chat.groups.manage`, `topweb_chat.groups.moderate`

## Acceptance Criteria
- [ ] Entidades Group + Participant (migrations + models + repositories)
- [ ] Webhooks group.* processados → sincronizam estado local
- [ ] API OpenWA wrapper no `OpenWaProvider`
- [ ] UI Admin: listar, ver detalhes, gerenciar participantes, aprovar solicitações
- [ ] ACL granular para grupos
- [ ] Testes: mock group webhooks → assert sincronização; API calls → 200

## Blocked by
- #11-17 (media pipeline — grupos podem ter mídia)
- `OpenWaProvider` groups methods

## Verification
```bash
# Mock group.join webhook → verificar Group + Participant criados
# Mock group.update → verificar subject/description atualizados
# API: listar grupos → 200
```