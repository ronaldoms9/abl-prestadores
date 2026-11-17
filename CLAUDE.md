# abl-prestadores

Sistema de Gestão de Prestadores da ABL PRIME (Laravel + MySQL, web responsiva, integração com o ERP UAU). Implementa a proposta comercial de 584 h em 6 entregas.

## Onde está cada coisa

- **Andamento (fonte da verdade do status):** painel https://claude.ai/artifact/C1rJGpvjuzDrstcqeRJtmv. Itens, horas feitas por entrega e pendências externas.
- **Especificação técnica (referência):** https://claude.ai/artifact/BU1tyS1dfn1KoaBFe47wG9. Arquitetura, modelo de dados, regras e backlog. É um doc do Claude: leia e edite com as ferramentas do Claude Docs, nunca com publicação de arquivo.
- **Proposta comercial:** https://claude.ai/artifact/DUYdrGSKfuipS8Mo5wtscv.
- **Planos por entrega e fatia:** `docs/planos/` (README com as decisões D1 a D8). O plano da fatia vem antes do código.
- **Desenvolvimento e infraestrutura:** `docs/desenvolvimento.md`, `docs/infra/`, `docs/homologacao/`.

## Decisões fixas

- Base do repositório mantida: Blade + yajra DataTables, tabelas próprias de perfis e permissões, PHPUnit. Sem Filament, Livewire, spatie-permission ou Pest.
- R$ 100/h em tudo. Pagamento: 10% de sinal e 90% distribuídos pela aceitação de cada entrega.
- Fora do escopo: folha CLT, emissão de NF, CNAB, assinatura digital, app nativo, migração de legado.
- Remuneração e benefícios têm vigência: reajuste cria registro novo e encerra o anterior. Fechamento aprovado é imutável.
- A pessoa jurídica tem um responsável vigente (pessoa física), só no banco local, pela ação `DefinirResponsavel`.
- Identidade visual: paleta e fonte do site 15anosprime.com.br (azul-marinho, azul `#235A9A`, dourado, Montserrat). A cor principal vem de `APP_COR_PRIMARIA`.

## Fluxo de trabalho

1. Plano detalhado da fatia em `docs/planos/` antes do código.
2. Branch a partir da `develop`, um commit por tarefa, com os testes no mesmo commit. Rotas novas entram no `PermissoesRotasTest`.
3. Antes de fechar: `vendor/bin/phpunit` e `migrate:fresh --seed` em SQLite e em MySQL.
4. Sem `gh` CLI: as fatias entram na `develop` por `git merge --no-ff`. O `git push` é feito pelo usuário.
5. **Ao fechar uma fatia ou item, atualizar o painel de andamento** (linhas do item e horas da entrega, com `ArtifactData`) e, se mudou regra ou modelo, a especificação.
6. Pendência externa nova ou resolvida também vai para o painel.

## Cuidados

- Carregamento sob demanda de relações é barrado fora de produção: toda relação exibida precisa de `with()`.
- Nunca gravar senha ou token em `payload` ou `resposta` de `integracao_logs`.
- `UAU_FAKE=true` até haver HTTPS e token do UAU de homologação.
