# E1 · Fatia 5 — Importação de empresas, obras e pessoas · 13 h · semana 2

Detalha a fatia 5 do [plano da Entrega 1](entrega-1-base-uau-leitura.md), na
ordem dos [próximos passos](entrega-1-proximos-passos.md). Depende da fatia 3
(tabelas, `Documento`, `Endereco`, `ResultadoImportacao`) e da fatia 4
(`UauGateway`, DTOs, exceções, `FakeUauClient`). As fatias 5b, 6, 7 e 8 dependem desta.

Ao fim desta fatia:
- empresas, obras e pessoas do UAU entram e se atualizam nas tabelas locais,
  sem duplicar e sem nunca apagar;
- o que saiu da lista de ativos do UAU vira `ativo = false`, mas uma lista vazia
  ou suspeita não desativa nada;
- a importação de uma pessoa devolve o resultado (importada, já existia, não
  encontrada, inativa, inválida), que a carga da planilha (5b) usa linha a linha;
- os jobs tentam de novo só quando o UAU está fora (`UauIndisponivel`), numa fila
  própria (`uau`), e a cadeia da conciliação diária está pronta para a fatia 6 agendar;
- nenhuma tela: consulta é a fatia 7, monitoramento a 8, agendamento e botão a 6.

Branch `e1/fatia-5-importacao`, a partir de `e1/fatia-4-uau-client` (ou da
`develop`, se a fatia 4 já tiver sido juntada). Um commit por tarefa, com os testes no mesmo commit.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 5.1 | Mappers DTO → atributos e o resultado da importação | 2 |
| 5.2 | `ImportarEmpresas` e `ImportarObras` (upsert, reativação, inativação, trava da lista vazia) | 3 |
| 5.3 | `ImportarPessoa` (PF ou PJ por documento) | 3 |
| 5.4 | Jobs, política de retentativa, fila `uau`, `ConciliarPessoas` e a cadeia | 3 |
| 5.5 | Dados simulados nos testes, Horizon, verificação e documentação | 2 |
| | **Total** | **13** |

---

## Decisões

**D5.1 — Regra nas Actions, job fino.** Seguindo o D4 do README e a regra do
SGTR ("command e job finos, delegam para Action"):
- As regras ficam em `app/Actions/Uau/`: `ImportarEmpresas`, `ImportarObras`, `ImportarPessoa`.
- Os jobs ficam em `app/Integrations/Uau/Jobs/` e só chamam a Action, com a
  política de retentativa.
- A carga da planilha (5b) chama `ImportarPessoa` direto, linha a linha, para
  ter o resultado na hora, sem um job por linha.

**D5.2 — Primeiro o UAU, depois a transação.**
- A Action chama o UAU fora de transação. Senão um rollback apagaria a linha de
  `integracao_logs` da falha (regra da fatia 4).
- Só depois grava no banco, dentro de `DB::transaction`.

**D5.3 — Upsert sem apagar nunca** (D3.1 da fatia 3):

| Entidade | Chave no UAU | Busca local | Encontrou excluída |
| --- | --- | --- | --- |
| Empresa | `Codigo_emp` | `uau_codigo`, com `withTrashed()` | restaura e atualiza |
| Obra | (`Empresa_obr`, `Cod_obr`) | (`empresa_id`, `uau_codigo`), com `withTrashed()` | restaura e atualiza |
| Pessoa | documento; código como reserva | `cpf`/`cnpj`, senão `uau_codigo` (documento corrigido no UAU), com `withTrashed()` | restaura e atualiza |

- **Toda gravação** marca `sincronizado_em = now()`.
- **Reativação:** um registro inativo que volta a aparecer volta a `ativo = true`.
- **Inativação:**
  - empresas: as ativas que não vieram na lista viram `ativo = false`;
  - obras: as ativas de cada empresa que não vieram na lista viram `ativo = false`;
  - pessoas: viram inativas quando o UAU diz `pessoaAtiva = 1`. Pessoa que o UAU
    não encontra mais também vira inativa.
- **Excluídas não se inativam:** registros excluídos (`deleted_at`) ficam fora da inativação; ninguém mexe neles.

**D5.4 — Lista vazia não desativa nada.**
- O cliente da fatia 4 já recusa resposta que não é lista. Uma lista vazia
  legítima (`[]`) ainda chega, e ela não pode virar "nenhuma empresa ativa".
- Regra: se a lista vier vazia e houver registros ativos localmente, a Action
  lança `ImportacaoSuspensa` sem gravar nada. O job falha sem retentar, e a falha aparece no Horizon.
- Mesma regra para uma queda brusca: se a lista desativaria mais da metade dos
  ativos (`config('uau.limite_inativacao') = 0.5`), suspende também.
- Isso evita que uma falha silenciosa do UAU desative todo o cadastro numa madrugada.

**D5.5 — Obra de empresa desconhecida é ignorada, não inventa empresa.**
- O UAU não tem consulta de empresa por código no contrato usado, e a cadeia
  roda `ImportarEmpresas` antes de `ImportarObras`.
- Uma obra cuja empresa não existe localmente (nos dados simulados, `OB09` da
  empresa 7) é contada em `ignoradas` no resumo e registrada como aviso no log da
  aplicação. As outras obras seguem.
- Se a empresa dela aparecer depois, a próxima conciliação traz a obra.

**D5.6 — Mapeamento campo a campo** (`app/Integrations/Uau/Mappers/`):

| Destino | Origem (DTO da fatia 4) |
| --- | --- |
| `empresas` | `uau_codigo` ← `codigo`; `cnpj`; `razao_social`; `inscricao_estadual`; `inscricao_municipal`; `telefone`; `endereco` ← `Endereco(logradouro: endereco)` (o UAU manda texto único) |
| `obras` | `empresa_id` (pela empresa local); `uau_codigo` ← `codigo`; `descricao`; `status_uau` ← `status`; `tipo_uau` ← `(string) tipo`; `data_inicio`; `data_fim`; `endereco` ← `Endereco(logradouro: endereco)` |
| `pessoas_fisicas` | `uau_codigo`; `cpf` ← `documento`; `nome`; `nascimento`; `email`; `telefone` ← `telefonePrincipal()`; `endereco` ← `enderecoPrincipal()?->toEndereco()`; `ativo` |
| `pessoas_juridicas` | `uau_codigo`; `cnpj` ← `documento`; `razao_social` ← `nome`; `nome_fantasia`; `inscricao_municipal`; `inscricao_estadual`; `email`; `telefone`; `endereco`; `ativo` |

- `regime_tributario` não é tocado: é da E2.
- Um campo que veio vazio do UAU grava `null`. O UAU é a fonte desses
  cadastros na E1, e ninguém os edita na tela.

**D5.7 — Resultado da importação de pessoa.** `ImportarPessoa::executar(string $documento): ResultadoPessoa`,
com `resultado` (`ResultadoImportacao`) e `pessoa` (o model PF ou PJ, ou `null`):

| Situação | Resultado | Grava? |
| --- | --- | --- |
| Documento sem 11 ou 14 dígitos | `invalida` | não chama o UAU |
| UAU não encontrou | `nao_encontrada` | inativa a local, se existir |
| Encontrou e não existia | `importada` | cria |
| Encontrou e já existia | `ja_existia` | atualiza |
| Encontrou inativa no UAU | `inativa` | cria ou atualiza com `ativo = false` |
| UAU devolveu outro documento | `UauRejeitou` ("resposta de outra pessoa") | não grava |

Valida o tamanho, não o dígito verificador: a rule `Cpf`/`Cnpj` é da 5b, no upload.

**D5.8 — Retentativa só quando adianta.**
- Todo job tem `tries = 4` e `backoff = [60, 300, 900, 3600]` (plano da E1).
- `UauIndisponivel` é relançada, e a fila tenta de novo.
- `UauRejeitou` e `ImportacaoSuspensa` chamam `$this->fail($e)`: tentar de novo não resolve.
- A tentativa vai para o log: `$uau->comContexto(entidade: ..., tentativa: $this->attempts())`.

**D5.9 — Fila `uau`, um processo.**
- Os jobs vão para a fila `uau`, num supervisor próprio do Horizon com 1 processo.
- Chamadas em série ao UAU: sem corrida de token (risco da fatia 4) e sem
  sobrecarregar o servidor da ABL PRIME. A fila `default` (e-mails) não espera a conciliação.

**D5.10 — Unicidade dos jobs** (`ShouldBeUnique`, como no SGTR):
- `ImportarEmpresasJob` e `ImportarObrasJob`: um de cada por vez (`uniqueFor = 3600`).
- `ImportarPessoaJob`: um por documento (`uniqueId = "pessoa:{documento}"`).
- O "Sincronizar agora" da fatia 6 se apoia nisso para não disparar duas conciliações.

**D5.11 — Sem auditoria (`activity_log`) nos cadastros importados.** Quem altera
esses cadastros na E1 é a importação, e cada chamada já está em `integracao_logs`.
O trait `Auditavel` entra na E2, quando houver edição pela tela (fatia 3, "Fora desta fatia").

---

## 5.1 Mappers e resultado — 2 h

- `App\Integrations\Uau\Mappers\EmpresaMapper::atributos(EmpresaUau): array`,
  `ObraMapper::atributos(ObraUau, Empresa): array`,
  `PessoaMapper::fisica(PessoaUau): array` e `PessoaMapper::juridica(PessoaUau): array`, conforme o D5.6.
- Os mappers não acessam o banco: recebem o DTO (e a empresa, no caso da obra) e
  devolvem o array para `fill()`. `sincronizado_em` é preenchido pela Action.
- `App\Actions\Uau\ResultadoPessoa` (readonly): `resultado`, `pessoa`.
- `App\Actions\Uau\ResumoImportacao` (readonly): `criadas`, `atualizadas`,
  `reativadas`, `inativadas`, `ignoradas` (lista de códigos), com `__toString()`
  para o log da aplicação ("3 criadas, 0 atualizadas, 1 inativada, 1 ignorada: 7/OB09").
- `App\Actions\Uau\ImportacaoSuspensa` (exceção de domínio, D5.4).

**Testes** (`tests/Unit/Uau/MappersTest.php`, com os dados simulados da fatia 4):
- cada DTO vira os atributos da tabela do D5.6;
- endereço em texto único vai para `logradouro`;
- PJ não toca `regime_tributario`;
- pessoa inativa sai com `ativo = false`.

## 5.2 ImportarEmpresas e ImportarObras — 3 h

**`ImportarEmpresas::executar(): ResumoImportacao`**
1. `$uau->empresasAtivas()`, fora de transação (D5.2).
2. A trava do D5.4.
3. Numa transação: upsert de cada empresa (D5.3), contando criadas, atualizadas e reativadas.
4. As ativas fora da lista viram `ativo = false`.

**`ImportarObras::executar(): ResumoImportacao`**
1. `$uau->obrasAtivas()`.
2. Mapa local `uau_codigo → Empresa`, com `withTrashed()`.
3. A trava do D5.4.
4. Upsert por (`empresa_id`, `uau_codigo`). Obra de empresa desconhecida vai para `ignoradas` (D5.5).
5. As ativas que não vieram viram `ativo = false`, só nas empresas que vieram na lista.
   Assim uma empresa ignorada não tem as obras desativadas por engano.

**Testes** (`tests/Feature/Uau/ImportarEmpresasObrasTest.php`):
- **Primeira carga:** 3 empresas e 3 obras criadas; `OB09` ignorada; `OB01`
  existe nas empresas 1 e 2; CNPJ com zero à esquerda; `sincronizado_em` preenchido.
- **Repetir:** rodar duas vezes não duplica nada (contagens iguais, resumo "0 criadas").
- **Alteração:** uma empresa com outra razão social nos dados simulados é atualizada.
- **Inativação e volta:**
  - empresa que sumiu da lista vira inativa;
  - inativa que volta é reativada;
  - excluída que volta é restaurada.
- **Trava:** lista vazia com empresas ativas lança `ImportacaoSuspensa` e não muda
  nada; uma lista que desativaria mais da metade também.
- **Obra sem empresa:** a obra de empresa desconhecida não cria empresa e não
  impede as outras.
- **Banco intacto em falha do UAU:** `UauIndisponivel` simulado pelo `falharEm()`
  sobe para o job e não altera o banco.

## 5.3 ImportarPessoa — 3 h

**`ImportarPessoa::executar(string $documento): ResultadoPessoa`** (D5.7)
1. `Documento::digitos()`. Sem 11 nem 14 dígitos, devolve `invalida`.
2. Procura a pessoa local (PF por `cpf`, PJ por `cnpj`, com `withTrashed()`), para o contexto do log.
3. `$uau->comContexto(entidade: $local, chaveIdempotencia: "pessoa:{doc}", tentativa: ...)->pessoaPorDocumento($doc)`.
4. Se o UAU devolver `null`: `nao_encontrada`, e a local, se existir, vira inativa.
5. Se o documento devolvido for diferente do pedido: `UauRejeitou`.
6. Numa transação:
   - upsert na tabela do `tipo` (D5.3, com a reserva por `uau_codigo`);
   - resultado conforme o D5.7.
7. **Corrida:** dois processos podem criar o mesmo documento ao mesmo tempo.
   `UniqueConstraintViolationException` → busca de novo e atualiza (uma vez).

**Testes** (`tests/Feature/Uau/ImportarPessoaTest.php`):
- **Um caso por resultado da tabela D5.7,** com os documentos dos dados simulados:
  - `52998224725` → importada;
  - de novo → já existia;
  - `74620153826` → inativa;
  - `11144477735` → não encontrada;
  - `123` → inválida, sem chamada ao UAU (`FakeUauClient::chamadas()` vazio).
- **Endereço, telefone e documento:**
  - o endereço é o principal (tipo 0) e o telefone é o celular;
  - o CPF mascarado (`03174682940`) é gravado só com dígitos.
- **PJ** (`27543198000106`) vai para `pessoas_juridicas`.
- **Documento corrigido no UAU:** pessoa local com o mesmo `uau_codigo` e documento antigo é atualizada, não duplicada.
- **Pessoa que sumiu do UAU:** pessoa local que o UAU não encontra mais vira inativa.
- **Log:** a linha de `integracao_logs` aponta para a pessoa local (`entidade_type = pessoa-fisica`) e traz a chave `pessoa:<doc>`.

## 5.4 Jobs, fila e cadeia — 3 h

Em `app/Integrations/Uau/Jobs/`, todos com `ShouldQueue`, `ShouldBeUnique`,
`$queue = 'uau'`, `tries = 4`, `backoff = [60, 300, 900, 3600]`:

| Job | Chama | `uniqueId` | `timeout` |
| --- | --- | --- | --- |
| `ImportarEmpresasJob` | `ImportarEmpresas` | `empresas` | 300 s |
| `ImportarObrasJob` | `ImportarObras` | `obras` | 300 s |
| `ImportarPessoaJob($documento)` | `ImportarPessoa` | `pessoa:{documento}` | 120 s |
| `ConciliarPessoasJob` | despacha um `ImportarPessoaJob` por pessoa já importada (PF e PJ não excluídas), em lotes (`lazyById`) | `conciliar-pessoas` | 300 s |

- **Política de erro** (D5.8): um trait `TrataFalhaDoUau` envolve o `handle()`.
  `UauIndisponivel` é relançada; `UauRejeitou` e `ImportacaoSuspensa` chamam `$this->fail($e)`.
- **Resumo:** cada job grava o `ResumoImportacao` no log da aplicação
  (`Log::info`, canal padrão), com o nome do job.
- **Cadeia:** `App\Integrations\Uau\Conciliacao::cadeia()` devolve
  `Bus::chain([ImportarEmpresasJob, ImportarObrasJob, ConciliarPessoasJob])->onQueue('uau')`.
  A fatia 6 só agenda e despacha essa cadeia. Se um elo falhar, os seguintes não rodam:
  obras não são importadas sobre empresas desatualizadas.

**Testes** (`tests/Feature/Uau/JobsUauTest.php`, fila `sync` + `FakeUauClient::falharEm()`):
- **Retentativa:**
  - `UauIndisponivel` na primeira tentativa → o job é liberado para nova tentativa (`Queue::fake` + `assertReleased`);
  - `UauRejeitou` → falha sem retentar.
- **Fila e unicidade:** os jobs vão para a fila `uau`; despachar dois `ImportarEmpresasJob`
  seguidos enfileira um só (`ShouldBeUnique`).
- **Conciliação:** `ConciliarPessoasJob` despacha um `ImportarPessoaJob` por pessoa local e nenhum por pessoa excluída.
- **Cadeia:**
  - `Conciliacao::cadeia()` roda os três elos em ordem com o simulado e deixa empresas, obras e pessoas sincronizadas;
  - com o primeiro elo falhando, os outros não rodam.
- **Tentativa no log:** a tentativa do job aparece em `integracao_logs.tentativas`.

## 5.5 Simulado nos testes, Horizon e verificação — 2 h

- **Dados simulados alterados por teste:** trait `Tests\Concerns\UauSimulado` com
  `simularUau(array $sobrescritas = [])`. Ele copia `resources/fixtures/uau` para uma pasta
  temporária, aplica as sobrescritas (ex.: `['empresas-ativas.json' => [...]]`) e
  liga um `FakeUauClient` apontando para ela. Assim cada teste muda o "UAU" sem tocar nos dados versionados.
- **`config/horizon.php`:** supervisor `supervisor-uau` (fila `uau`, `maxProcesses = 1`,
  `timeout = 900`) em `defaults` e nos ambientes. O `timeout` do supervisor é maior
  que o maior `timeout` de job.
- **`config/uau.php`:** `limite_inativacao` (D5.4, padrão 0.5).
- **`docs/desenvolvimento.md`**, seção "Integração UAU":
  - como rodar a conciliação à mão (`php artisan tinker` → `Conciliacao::cadeia()->dispatch()`);
  - a trava da lista vazia;
  - a fila `uau`.
- **Verificação à mão no Docker, com o simulado:**
  - rodar a cadeia e conferir as tabelas e `integracao_logs`;
  - rodar de novo e conferir que nada duplica.

---

## Fora desta fatia

| Item | Onde |
| --- | --- |
| Agendamento às 05:00 e botão "Sincronizar agora" | Fatia 6 |
| Upload da planilha, rules `Cpf`/`Cnpj`, `vinculos_sugeridos` | Fatia 5b |
| Telas de empresas, obras e pessoas | Fatia 7 |
| Tela de `integracao_logs`, "Reprocessar" e indicador de falhas | Fatia 8 |
| "Última conciliação bem-sucedida" (onde guardar) | Fatia 6, com o agendamento |
| `uau_alterado_em` e detecção de conflito | E2 |

## Pronto quando

1. `vendor/bin/phpunit` verde, com os testes de 5.1 a 5.5.
2. Rodar `Conciliacao::cadeia()` duas vezes com o simulado deixa 3 empresas, 3 obras
   e as pessoas conciliadas, sem duplicar. A segunda rodada só atualiza `sincronizado_em`.
3. Uma lista vazia do UAU não desativa nenhum registro (teste).
4. `UauIndisponivel` é tentado de novo e `UauRejeitou` não (teste).
5. Os jobs rodam na fila `uau`, com o supervisor próprio no Horizon.

## Riscos

| Risco | Saída |
| --- | --- |
| A conciliação diária de pessoas cresce com o cadastro (uma chamada por pessoa) | Fila `uau` com 1 processo e lotes; centenas de prestadores cabem na janela da madrugada. Medir na homologação; se não couber, consultar só as alteradas (E2, `uau_alterado_em`) |
| O código da obra não é único por empresa nos dados reais | O índice único falha na primeira carga real; a fatia 4 já guarda o log. Ajustar com migration se acontecer |
| A trava da lista vazia bloqueia um caso legítimo (a ABL PRIME desativou mais da metade das obras de uma vez) | `limite_inativacao` configurável; com aval da ABL PRIME, rodar uma vez com o limite em 1.0 |
| Pessoa com documento trocado no UAU gera duplicata | Reserva por `uau_codigo` (D5.3) e teste dedicado |
| Job preso por `ShouldBeUnique` após uma queda do worker | `uniqueFor` limita a trava (3600 s); `php artisan cache:clear` libera em emergência |
