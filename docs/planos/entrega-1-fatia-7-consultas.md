# E1 · Fatia 7 — Telas de consulta · 8 h · semana 3

Detalha a fatia 7 do [plano da Entrega 1](entrega-1-base-uau-leitura.md), na
ordem dos [próximos passos](entrega-1-proximos-passos.md). Depende da fatia 3
(models, scopes, `Documento`, `Endereco`), da 5 (dados vindos do UAU) e da 5b (vínculos sugeridos).

Ao fim desta fatia, quem tem a permissão consulta, só para leitura:
- pessoas físicas;
- pessoas jurídicas;
- empresas (com as obras);
- obras.

Em todas as telas:
- filtro de ativos e inativos;
- busca por nome ou por documento, com ou sem máscara;
- detalhe com endereço e vínculos.

Nada se edita: na E1 o UAU é a fonte desses cadastros.

Branch `e1/fatia-7-consultas`, a partir de `e1/fatia-6-agendamento`. Um commit por tarefa, com os testes no mesmo commit.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 7.1 | Filtros comuns e pessoas físicas | 2,5 |
| 7.2 | Pessoas jurídicas | 1,5 |
| 7.3 | Empresas (com as obras) e obras | 3 |
| 7.4 | Verificação com o simulado e documentação | 1 |
| | **Total** | **8** |

---

## Decisões

**D7.1 — Padrão da base:**
- controller + `Yajra\DataTables\Html\Builder` + Blade, como a auditoria;
- detalhe em modal (`requestIntoModal`);
- um controller por entidade em `App\Http\Controllers\Cadastros`, com `index` e `show`;
- sem botões de edição nem exclusão.

**D7.2 — Um campo de busca, dois jeitos.** O trait `FiltraCadastro` aplica a busca e a situação nas quatro telas:
- **Documento:** se o texto só tem dígitos e sinais de máscara (`.`, `-`, `/`) e
  pelo menos 3 dígitos, busca pelo começo do CPF/CNPJ só com dígitos. Assim
  `529.982` e `529982` acham `52998224725`.
- **Nome:** qualquer outro texto busca por parte do nome (PF), da razão social ou do
  nome fantasia (PJ e empresa), ou da descrição e do código (obra).
- `%` e `_` digitados são escapados: a busca é literal.

**D7.3 — Situação: ativos por padrão.** Filtro "Ativos" (padrão), "Inativos" e
"Todos". Registros excluídos (`deleted_at`) não aparecem. Na E1 ninguém exclui; isso fica para a E2.

**D7.4 — Status da obra por configuração.** O significado dos códigos de `Status_obr`
ainda não chegou da ABL PRIME.
- `config('uau.status_obra')` é um mapa código → rótulo, vazio por enquanto.
- Sem rótulo, a tela mostra "Código N".
- Quando a ABL PRIME responder, preencher o mapa resolve, sem mudar código nem tela.

**D7.5 — Relações sempre com `with()`/`withCount()`** (carregamento sob demanda barrado):

| Tela | Carrega |
| --- | --- |
| Obras (lista) | `with('empresa')` |
| Empresas (lista) | `withCount(['obras as obras_ativas_count' => ativos])` |
| Empresa (detalhe) | `with(['obras' => ordenadas por código])` |
| PF (detalhe) | `with('pessoasJuridicasSugeridas')` |
| PJ (detalhe) | `with('pessoasFisicasSugeridas')` |

**D7.6 — Vínculos sugeridos no detalhe.** O detalhe da PF lista as PJs sugeridas pela carga da
planilha (e o inverso), com o CNPJ/CPF formatado e a situação. Aceitar ou descartar um vínculo é da E2.

**D7.7 — O que cada lista mostra:**

| Tela | Colunas |
| --- | --- |
| Pessoas físicas | CPF, nome, e-mail, telefone, situação, sincronizado em |
| Pessoas jurídicas | CNPJ, razão social, nome fantasia, situação, sincronizado em |
| Empresas | código UAU, CNPJ, razão social, obras ativas, situação |
| Obras | empresa, código, descrição, status UAU, início, fim, situação; filtro por empresa |

---

## 7.1 Filtros comuns e pessoas físicas — 2,5 h

- `App\Http\Controllers\Cadastros\FiltraCadastro` (trait, D7.2 e D7.3), com os campos de busca de cada tela passados pelo controller.
- Partial `cadastros/_filtros.blade.php` (busca + situação), reaproveitado nas quatro telas.
- `PessoaFisicaController` (`pessoa-fisica.index`, `pessoa-fisica.show`, `pessoa-list`). O detalhe mostra:
  - dados;
  - endereço (`Endereco::formatado()`);
  - código UAU;
  - última sincronização;
  - PJs sugeridas (D7.6).

**Testes** (`tests/Feature/Cadastros/PessoaFisicaTelaTest.php`):
- busca por CPF com e sem máscara e pelo começo do CPF; por parte do nome;
- `%` digitado não vira curinga;
- ativos por padrão, inativos e todos; excluída não aparece;
- o detalhe mostra o endereço formatado e a PJ sugerida.

## 7.2 Pessoas jurídicas — 1,5 h

- `PessoaJuridicaController` (`pessoa-juridica.index`, `pessoa-juridica.show`, `pessoa-list`). O detalhe mostra:
  - inscrições;
  - regime tributário ("—" na E1);
  - endereço;
  - PFs sugeridas.

**Testes** (`tests/Feature/Cadastros/PessoaJuridicaTelaTest.php`):
- busca por CNPJ com máscara, por razão social e por nome fantasia;
- o detalhe mostra a PF sugerida.

## 7.3 Empresas e obras — 3 h

- `EmpresaController` (`empresa.index`, `empresa.show`, `empresa-list`). O detalhe lista
  as obras da empresa (ativas e inativas, com a situação).
- `ObraController` (`obra.index`, `obra.show`, `obra-list`):
  - filtro por empresa;
  - busca por descrição ou código;
  - status pelo D7.4.
- `config/uau.php`: `status_obra` (D7.4).

**Testes** (`tests/Feature/Cadastros/EmpresaObraTelaTest.php`):
- a lista de empresas conta só as obras ativas;
- o detalhe da empresa lista as suas obras e não as de outra;
- o filtro por empresa e a busca por código da obra;
- o status mostra "Código 1" sem mapa e o rótulo com mapa;
- busca de empresa por CNPJ com máscara;
- as oito rotas entram em `PermissoesRotasTest`.

## 7.4 Verificação e documentação — 1 h

- **No Docker, com o simulado:**
  - conciliação + carga da planilha da 5b;
  - as quatro listas e os detalhes, os filtros e a busca com máscara;
  - os itens do menu aparecem para o perfil de cadastro (`PerfilSeeder`).
- `docs/desenvolvimento.md`: a busca por documento e o mapa de status da obra.
- Roteiro de homologação (fatia 9): o que mostrar à ABL PRIME nas telas de consulta.

---

## Fora desta fatia

| Item | Onde |
| --- | --- |
| Edição de PF/PJ, regime tributário, documentos | E2 |
| Aceitar ou descartar vínculo sugerido | E2 |
| Busca avulsa no UAU por CPF/CNPJ (trazer pessoa nova) | E2 (Q22) |
| Exportar as listas para Excel | E6 |

## Pronto quando

1. `vendor/bin/phpunit` verde, com os testes de 7.1 a 7.3.
2. As quatro listas e os detalhes abrem com os dados do simulado.
3. A busca acha o documento com e sem máscara.
4. As rotas estão em `PermissoesRotasTest`.

## Notas da implementação (2026-10-17)

- A busca usa `LIKE ... ESCAPE '!'`. O `\` como escape não funciona igual no MySQL e no
  SQLite (no SQLite vira dois caracteres), e `!` funciona nos dois.
- Uma view de lista só (`cadastros.index`) serve as quatro telas, com filtros extras por
  partial (empresa, na de obras). As opções de situação vão pela view, porque constante de trait não pode ser lida de fora.
- A obra de empresa excluída continua mostrando a empresa (`withTrashed()` no `with`).
- **Verificado no Docker (MySQL), com os dados do simulado:**
  - as quatro listas e os quatro detalhes abrem;
  - busca por CPF parcial com máscara, por nome, por CNPJ com máscara e por código de obra;
  - `100%` não vira curinga;
  - o perfil RH vê Pessoas (3 itens), Empresas e Obras.

## Riscos

| Risco | Saída |
| --- | --- |
| Busca por nome com `LIKE '%x%'` fica lenta com muitos registros | Volume da ABL PRIME é de centenas de prestadores; medir na homologação. Se precisar, índice FULLTEXT na E2 |
| Status_obr continua sem significado na homologação | A tela mostra o código; o mapa é só configuração |
