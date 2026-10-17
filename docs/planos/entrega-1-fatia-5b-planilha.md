# E1 · Fatia 5b — Carga por planilha · 5 h · semanas 2 e 3

Detalha a fatia 5b do [plano da Entrega 1](entrega-1-base-uau-leitura.md), na
ordem dos [próximos passos](entrega-1-proximos-passos.md). Depende da fatia 3
(tabelas `importacoes_pessoas`, `importacao_pessoa_linhas`, `vinculos_sugeridos`,
enums `StatusImportacao` e `ResultadoImportacao`) e da fatia 5 (`ImportarPessoa`,
fila `uau`, `TrataFalhaDoUau`, `UauSimulado`).

Ao fim desta fatia:
- quem tem `pessoa-importar` baixa o modelo, sobe um CSV ou XLSX com as colunas
  `cpf` e `cnpj` e vê o resultado de cada linha;
- cabeçalho errado recusa o arquivo inteiro na hora; documento com dígito
  verificador errado é marcado na linha, sem chamar o UAU;
- cada CPF e CNPJ válido passa pelo `ImportarPessoa` da fatia 5, em segundo plano
  na fila `uau`. Quando a linha traz os dois e ambos existem no UAU, nasce um vínculo sugerido;
- subir a mesma planilha de novo não duplica pessoas nem vínculos;
- as rules `Cpf` e `Cnpj` existem e são as mesmas que a E2 vai usar.

Branch `e1/fatia-5b-planilha`, a partir de `e1/fatia-5-importacao` (ou da
`develop`, se a 5 já tiver sido juntada). Um commit por tarefa, com os testes no mesmo commit.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 5b.1 | `maatwebsite/excel`, rules `Cpf`/`Cnpj` e leitura da planilha | 1,5 |
| 5b.2 | Carga (upload → linhas) e processamento em lotes na fila `uau` | 1,5 |
| 5b.3 | Telas: carregar, modelo, histórico e resultado | 1,5 |
| 5b.4 | Verificação no Docker e documentação | 0,5 |
| | **Total** | **5** |

---

## Decisões

**D5b.1 — Validação em dois tempos.**
- **No upload, síncrono:** formato e tamanho do arquivo, cabeçalho, limite de linhas
  e dígito verificador. Problema no arquivo (cabeçalho, vazio, grande demais)
  recusa tudo com mensagem no formulário, e nada é gravado.
- **Ainda no upload:** cada linha útil vira uma linha em `importacao_pessoa_linhas`.
  Documento inválido já sai com resultado `invalida` e a mensagem ("CPF com dígito verificador inválido").
- **Na fila:** só as consultas ao UAU. O usuário recebe a tela de resultado na
  hora e acompanha o andamento.

Assim o relatório de linhas inválidas sai sem depender do UAU, e o job só tem trabalho de integração.

**D5b.2 — Formato da planilha.**

| Item | Regra |
| --- | --- |
| Tipos | `.xlsx` e `.csv` (UTF-8, separador `;` ou `,` detectado), até 2 MB |
| Cabeçalho | primeira linha; precisa ter `cpf` e `cnpj` (maiúsculas, acentos e espaços ignorados pelo `WithHeadingRow`); colunas extras são ignoradas |
| Linha | CPF, CNPJ ou os dois; com ou sem máscara |
| Linha vazia | ignorada, não conta em `total_linhas` |
| Linha sem documento (mas com outra coluna preenchida) | gravada como `invalida`, "Informe o CPF ou o CNPJ" |
| Limite | `config('uau.planilha.max_linhas')`, padrão 2000; acima disso, recusa o arquivo |
| Número da linha | o da planilha (o cabeçalho é a 1), para o usuário achar a linha no Excel |

**D5b.3 — Zeros à esquerda perdidos pelo Excel.** O Excel guarda `03174682940`
como número e entrega `3174682940`.
- Célula numérica na coluna `cpf` é completada com zeros até 11 dígitos; na `cnpj`, até 14.
- O dígito verificador é conferido depois de completar, então um número truncado
  volta a ser o documento original, e um número errado continua inválido.
- O modelo para download já sai com as duas colunas no formato texto, para evitar o problema na origem.
- CNPJ em notação científica (`1,12223E+13`, de CSV exportado do Excel) não tem
  como ser recuperado: vira `invalida` com a mensagem "CNPJ em notação científica;
  formate a coluna como texto".

**D5b.4 — Rules `Cpf` e `Cnpj`** em `app/Rules/`, no padrão `ValidationRule` do Laravel 12:
- método estático `valido(string): bool`, usado pela carga e pela E2. A rule de
  formulário chama esse método;
- aceitam com ou sem máscara (`Documento::digitos()`) e recusam sequências repetidas (`111.111.111-11`);
- o helper `validaCPF()` de `app/Http/helpers.php` passa a chamar `Cpf::valido()`.
  Os dois controllers que o usam não mudam (troca por `new Cpf` fica para quando forem mexidos).
  Fica a checagem de e-mail que o helper faz hoje, para não mudar o comportamento.

**D5b.5 — Processamento em lotes, retomável.**
- `ProcessarImportacaoPessoasJob($importacaoId)` na fila `uau`, com `TrataFalhaDoUau`,
  `tries = 4`, `backoff = [60, 300, 900, 3600]` (D5.8).
- Cada execução processa até `config('uau.planilha.lote')` linhas pendentes (padrão 50).
  Se sobrarem linhas, despacha a si mesmo de novo.
- **Linha pendente** é a que tem `cpf` sem `resultado_pf`, ou `cnpj` sem `resultado_pj`.
  Cada documento grava seu resultado assim que volta do UAU. Uma queda no meio do
  lote não repete os documentos já consultados.
- **Unicidade:** `ShouldBeUniqueUntilProcessing` (por `importacao:{id}`), porque o
  `ShouldBeUnique` comum seguraria a trava enquanto o próprio job tenta se despachar de novo.
- **Por quê lotes:** 2000 linhas × 2 consultas não cabem num job de 300 s. Lotes
  também deixam a conciliação diária (mesma fila, 1 processo) entrar entre um lote e outro.

**D5b.6 — Recusa do UAU fica na linha, não derruba a carga.**
- `UauRejeitou` numa linha (ex.: "resposta de outra pessoa", D5.7) é capturada pelo
  job: o documento recebe o resultado novo `recusada` ("Recusada pelo UAU"), com a mensagem, e a carga segue.
- `UauIndisponivel` sobe para o `TrataFalhaDoUau`: o lote é tentado de novo com o backoff.
- Esgotadas as tentativas, `failed()` marca a carga como `falhou`, com o erro. As
  linhas já processadas ficam, e as pendentes aparecem como "não processada" no relatório.
- `ResultadoImportacao` ganha o caso `Recusada = 'recusada'`. Cabe na coluna `string(15)`; não precisa de migration.

**D5b.7 — Vínculo sugerido.**
- Nasce quando a linha tem CPF e CNPJ e os dois resultados têm pessoa local:
  `importada`, `ja_existia` ou `inativa`. Inativa conta, porque o vínculo é só sugestão para a E2.
- `firstOrCreate` pelo par (PF, PJ), com `importacao_pessoa_id` da primeira carga
  que o sugeriu. Recarregar a planilha não duplica (índice único da fatia 3).
- **Vínculo excluído não volta:** a busca usa `withTrashed()`. Se alguém o
  descartou (E2), uma nova carga não o recria.

**D5b.8 — Arquivo guardado no disco privado.**
- `storage/app/importacoes-pessoas/{uuid}.{ext}` (disco `local`, fora do `public`).
  O nome original fica só em `nome_original`.
- O arquivo é lido uma vez no upload (D5b.1) e fica guardado como evidência da carga, sem rota de download na E1.
- Contém CPFs: o prazo de retenção (LGPD) é decidido na E6 junto com a política de backup. Registrado em "Fora desta fatia".

**D5b.9 — Auditoria.**
- `Auditoria::registrar('planilha-carregada', $importacao, ['arquivo' => ..., 'linhas' => ...])` no upload aceito.
- `Auditoria::OBJETOS` ganha `ImportacaoPessoa::class => ['Carga de planilha', 'nome_original']`.
- O resultado de cada consulta já está em `integracao_logs` (fatia 4). As pessoas
  importadas não entram no `activity_log` (D5.11).

**D5b.10 — Resumo.** `importacoes_pessoas.resumo` guarda as contagens ao fim da carga:
`{"pf": {"importada": 3, "invalida": 1, ...}, "pj": {...}, "vinculos": 2, "nao_processadas": 0}`.
A tela lê o resumo da carga concluída e calcula na hora a da carga em andamento.

---

## 5b.1 Pacote, rules e leitura — 1,5 h

- `composer require maatwebsite/excel` (D3 do README). A imagem Docker já tem
  `php8.4-zip`, `-xml` e `-gd`. Conferir que o `composer.lock` resolve com PHP 8.4 e Laravel 12.
- `app/Rules/Cpf.php` e `app/Rules/Cnpj.php` (D5b.4); `validaCPF()` delega para `Cpf::valido()`.
- `App\Imports\PlanilhaPessoas` (`ToCollection`, `WithHeadingRow`, `WithCustomCsvSettings`
  para detectar o separador, `SkipsEmptyRows`): só lê e devolve as linhas cruas, com o número da linha.
- `App\Actions\Pessoas\LinhaPlanilha` (readonly): `linha`, `cpf`, `cnpj`, `mensagem`.
  A normalização (D5b.3) e a validação (D5b.2, D5b.4) ficam num método `deCelulas(int $linha, array $celulas)`, testável sem arquivo.
- `App\Exports\ModeloPlanilhaPessoas` (`WithHeadings`, `WithColumnFormatting` texto nas duas colunas).

**Testes**
- `tests/Unit/CpfCnpjTest.php`:
  - válidos com e sem máscara;
  - dígito errado recusado;
  - sequência repetida recusada;
  - tamanho errado recusado;
  - `validaCPF()` continua respondendo igual.
- `tests/Unit/LinhaPlanilhaTest.php`:
  - célula numérica `3174682940` vira `03174682940` válido;
  - CNPJ em notação científica vira inválido com a mensagem do D5b.3;
  - linha só com CPF, só com CNPJ e com os dois;
  - linha sem documento.

## 5b.2 Carga e processamento — 1,5 h

**`App\Actions\Pessoas\CarregarPlanilha::executar(UploadedFile, User): ImportacaoPessoa`**
1. Lê o arquivo com `PlanilhaPessoas` antes de gravar qualquer coisa.
2. Confere cabeçalho, linhas vazias e o limite (D5b.2). Erro → `PlanilhaInvalida` (exceção com a mensagem para o formulário).
3. Numa transação:
   - guarda o arquivo (D5b.8);
   - cria a `ImportacaoPessoa` (`pendente`, `total_linhas`);
   - grava as linhas (`insert` em blocos de 500), com os inválidos já marcados (D5b.1).
4. Registra a auditoria (D5b.9).
5. Despacha `ProcessarImportacaoPessoasJob` depois do commit (`afterCommit`).

**`App\Actions\Pessoas\ProcessarLinha::executar(ImportacaoPessoaLinha, int $tentativa)`**
- Chama `ImportarPessoa` para o CPF e para o CNPJ que estiverem pendentes, um de
  cada vez, gravando cada resultado logo que volta (D5b.5).
- Trata `UauRejeitou` como `recusada` (D5b.6).
- Cria o vínculo sugerido quando cabe (D5b.7).

**`App\Integrations\Uau\Jobs\ProcessarImportacaoPessoasJob`** (D5b.5)
- Primeira execução: `processando` e `iniciada_em`.
- Processa um lote de linhas pendentes (`orderBy('linha')`) com `ProcessarLinha`.
- Sobrou linha pendente: despacha de novo. Não sobrou: `concluida`, `concluida_em` e `resumo` (D5b.10).
- `failed()`: `falhou`, `erro` (até 2000 caracteres, como no log de integração) e o `resumo` parcial.

**Testes** (`tests/Feature/Pessoas/CarregarPlanilhaTest.php`, com `UauSimulado` e arquivos gerados no teste):
- **Recusas:**
  - cabeçalho sem `cnpj` recusa o arquivo e não grava nada (nem arquivo, nem carga);
  - arquivo vazio e arquivo acima do limite também.
- **Linhas:** um CSV com `;` e um XLSX chegam às mesmas linhas.
- **Um resultado de cada:** uma planilha com os documentos do simulado dá:
  - `52998224725` → importada;
  - `74620153826` → inativa;
  - `11144477735` → não encontrada;
  - `27543198000106` → importada (PJ);
  - um CPF com dígito errado → inválida, sem chamada ao UAU (`FakeUauClient::chamadas()`).
- **Vínculo:** a linha com `52998224725` + `27543198000106` cria o vínculo. Uma linha com CPF não encontrado não cria.
- **Recarga:** subir a mesma planilha de novo dá `ja_existia` e não cria pessoa nem vínculo novo.
  Com o vínculo excluído antes, ele não volta.
- **Recusa do UAU:** um documento que o simulado devolve com outro número vira `recusada`, e as outras linhas seguem.
- **Lotes e retomada:**
  - com `lote = 2` e 5 linhas, o job roda três vezes e a carga termina `concluida`;
  - `falharEm(PessoaPorDocumento, UauIndisponivel)` no meio do lote → o job é
    liberado, e na nova tentativa os documentos já gravados não são consultados de novo (contar chamadas).
- **Falha final:** esgotadas as tentativas, a carga fica `falhou` e conta as linhas não processadas.
- **Fila:** o job vai para a fila `uau`.

## 5b.3 Telas — 1,5 h

Padrão da `AuditoriaController` (Yajra `Builder` + `ajaxWithForm`, Blade AdminLTE). Controller
`App\Http\Controllers\Pessoas\ImportacaoPessoaController`. Todas as rotas com `permission:pessoa-importar`:

| Método | URI | Nome | Faz |
| --- | --- | --- | --- |
| GET | `/pessoas/planilhas` | `importacao-pessoa.index` | formulário de upload + histórico de cargas |
| GET | `/pessoas/planilhas/modelo` | `importacao-pessoa.modelo` | baixa `modelo-pessoas.xlsx` (D5b.3) |
| POST | `/pessoas/planilhas` | `importacao-pessoa.store` | `CarregarPlanilha`; redireciona para o resultado |
| GET | `/pessoas/planilhas/{id}` | `importacao-pessoa.show` | contadores + linhas |

- **Upload:** `FormRequest` com `file`, `mimes:csv,txt,xlsx`, `max:2048`. `PlanilhaInvalida`
  volta como erro do campo, com o texto do D5b.2.
- **Histórico:** data, arquivo, quem carregou, status (`rotulo()`), linhas e um resumo curto ("120 importadas, 3 inválidas").
- **Resultado:**
  - cartões de contagem por resultado, para PF e PJ, e de vínculos sugeridos;
  - tabela das linhas, com filtro "só com problema" ligado por padrão: inválida,
    não encontrada, recusada, não processada. Mostra a linha, o CPF/CNPJ formatado, os resultados e a mensagem;
  - enquanto `pendente` ou `processando`, a página se recarrega a cada 10 s e mostra
    "Processando: N de M linhas". Sem WebSocket.
- **Menu:** o item `importacao-pessoa.index` de `config/menu.php` passa a aparecer sozinho com a rota.
- **Carregamento sob demanda barrado:** o histórico usa `with('criadoPor')`.

**Testes** (`tests/Feature/Pessoas/ImportacaoPessoaTelaTest.php`)
- o upload válido cria a carga, registra `planilha-carregada` e redireciona para o resultado;
- o upload com cabeçalho errado volta com o erro no campo `arquivo`;
- o modelo baixa um XLSX com o cabeçalho `cpf`, `cnpj`;
- o filtro "só com problema" esconde as linhas importadas;
- as quatro rotas entram em `PermissoesRotasTest` (e `pessoa-importar` na lista das "outras" permissões).

## 5b.4 Verificação e documentação — 0,5 h

- `config/uau.php`: bloco `planilha` (`max_linhas`, `lote`, `disco`).
- `docs/desenvolvimento.md`, seção "Integração UAU": a carga por planilha, os documentos do simulado e como reprocessar uma carga que falhou
  (`ProcessarImportacaoPessoasJob::dispatch($id)` no tinker; ela retoma das pendentes).
- **Verificação à mão no Docker, com o simulado e o Horizon rodando:**
  - baixar o modelo, preencher no Excel com um CPF começando por zero e subir;
  - conferir o resultado, `integracao_logs` e o vínculo;
  - subir de novo e conferir que nada duplica.
- `migrate:fresh --seed` no SQLite e no MySQL de um banco temporário (não há migration nova, mas a regra do processo vale).

---

## Fora desta fatia

| Item | Onde |
| --- | --- |
| Busca avulsa por CPF/CNPJ | E2 (Q22) |
| Usar, aceitar ou descartar vínculo sugerido | E2 |
| Telas de pessoas físicas e jurídicas (o detalhe mostra os vínculos) | Fatia 7 |
| Retenção dos arquivos carregados (LGPD) e limpeza agendada | E6, com a política de backup |
| "Reprocessar" pela tela | Fatia 8 olha `integracao_logs`; uma carga `falhou` é retomada pelo tinker até lá |
| CNPJ alfanumérico | Ver riscos |

## Pronto quando

1. `vendor/bin/phpunit` verde, com os testes de 5b.1 a 5b.3.
2. Uma planilha com cabeçalho errado é recusada sem gravar nada, e um documento com dígito errado é marcado sem consultar o UAU.
3. Cada resultado aparece no relatório: importada, já existia, não encontrada, inativa, inválida e recusada.
4. Recarregar a mesma planilha não duplica pessoas nem vínculos.
5. Um CPF com zero à esquerda, salvo pelo Excel como número, é importado certo.
6. Uma queda do UAU no meio da carga é retomada sem refazer as linhas prontas.
7. As rotas estão em `PermissoesRotasTest` e o item "Carregar planilha" aparece para quem tem `pessoa-importar`.

## Riscos

| Risco | Saída |
| --- | --- |
| A planilha real da ABL PRIME chega fora do modelo (outras colunas, nome e documento na mesma célula) | Recusa com mensagem clara. Pedir a planilha na semana 3 e, se preciso, ajustar só o leitor (`PlanilhaPessoas`) |
| CNPJ alfanumérico (Receita Federal, IN RFB 2.229/2024, novos CNPJs desde julho de 2026) | A E1 aceita só dígitos, como o UAU e as colunas atuais. Um CNPJ com letra vira `invalida` com mensagem própria. Confirmar com a ABL PRIME e a Globaltec se o UAU já aceita e planejar a mudança (rule, `Documento`, colunas) na E2 |
| 5 h apertadas para leitura, fila e duas telas | O relatório pode sair sem os cartões (só a tabela) e ganhar os cartões na fatia 7. Usa a folga da E1 |
| Carga grande atrasa a conciliação diária (mesma fila, 1 processo) | Lotes de 50 deixam a conciliação entrar entre lotes. Medir com a planilha real |
| `maatwebsite/excel` traz `phpspreadsheet` pesado (memória em XLSX grande) | Limite de 2 MB e 2000 linhas; `ToCollection` lê uma vez, no upload |
