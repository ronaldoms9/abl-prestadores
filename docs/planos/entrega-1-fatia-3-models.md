# E1 · Fatia 3 — Migrations e models · 8 h · semana 1

Detalha a fatia 3 do [plano da Entrega 1](entrega-1-base-uau-leitura.md).
Depende da fatia 1 (`$table->auditoria()` e `RegistraAutoria`) e da fatia 2
(`users.ativo` já existe). A fatia 4 depende desta: o `UauClient` grava em
`integracao_logs`.

Ao fim desta fatia:
- as nove tabelas da E1 existem, com índices, chaves estrangeiras e `down()` funcionando;
- cada tabela tem model em `app/Models/`, com relações, casts e factory;
- CPF e CNPJ são gravados só com dígitos, em um único ponto do código;
- nenhuma tela muda: telas são da fatia 7, importação das fatias 4 e 5.

Fonte dos campos: spec 3.1 e a tabela da fatia 3 no plano da E1. O que este
plano acrescenta ou decide está marcado como **decisão**.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 3.1 | Base de apoio (documento, endereço, enums, `Ativavel`, morph map) | 2 |
| 3.2 | Cadastros vindos do UAU: empresas, obras, pessoas, `users.pessoa_fisica_id` | 2,5 |
| 3.3 | Integração e carga: logs, mapeamentos, importações, vínculos | 2,5 |
| 3.4 | Verificação: migrate e rollback em SQLite e MySQL, documentação | 1 |
| | **Total** | **8** |

Branch `e1/fatia-3-models`, a partir de `e1/fatia-2-permissoes`. Os testes de
cada tarefa entram no mesmo commit. Uma migration por tabela.

---

## Decisões

**D3.1 — `ativo` e `deleted_at` não se confundem.**
- `ativo` espelha o UAU: a importação liga e desliga, nunca apaga (plano E1, fatia 5).
- `deleted_at` (de `auditoria()`) é só para cadastro feito por engano, pela tela, a partir da E2.
- Os índices únicos (`cpf`, `cnpj`, `uau_codigo`) valem também para linhas
  excluídas. Por isso a importação da fatia 5 busca com `withTrashed()`.
  Encontrou uma linha excluída: restaura e atualiza, em vez de criar outra.

**D3.2 — Documento só com dígitos, em `char`.**
- `cpf char(11)` e `cnpj char(14)`. Texto, não número, para manter o zero à esquerda.
- O cast `App\Casts\SomenteDigitos` limpa a máscara na gravação. Vale para
  model, factory, importação e telas, sem depender de quem grava.
- O cast não valida o dígito verificador: os dados do UAU entram como vieram.
  A validação é das rules `Cpf` e `Cnpj` da fatia 5b, no upload e nas telas.

**D3.3 — Endereço com formato fixo.**
- `endereco` json em empresas, obras e pessoas, com as chaves `logradouro`,
  `numero`, `complemento`, `bairro`, `cidade`, `uf` e `cep` (só dígitos).
- No plano da E1, `obras.endereco` não tem tipo. Fica json também, para os
  três cadastros terem o mesmo formato.
- O value object `App\Support\Endereco` com o cast `App\Casts\AsEndereco`
  garante as chaves. A tela (fatia 7) usa `formatado()`, e os mappers (fatia 5) usam `fromArray()`.

**D3.4 — Estados como enums do PHP, gravados como texto.**
- Colunas `string`, não `enum` do MySQL: incluir um valor não exige migration.
- Ficam em `app/Enums/`, com `rotulo()` para as telas:
  - `DirecaoIntegracao`: entrada, saida;
  - `StatusIntegracao`: sucesso, erro, pendente;
  - `StatusImportacao`: pendente, processando, concluida, falhou;
  - `ResultadoImportacao`: importada, ja_existia, nao_encontrada, inativa, invalida.

**D3.5 — Tabelas técnicas não usam `auditoria()`.**
- `integracao_logs` e `importacao_pessoa_linhas` não têm autor nem exclusão
  lógica: só `timestamps()`. Uma linha de log nunca é editada nem apagada pela
  tela, e a linha da carga herda o autor da importação a que pertence.
- As demais tabelas usam `$table->auditoria()`, como pede o D5 do README.

**D3.6 — Morph map.** `Relation::enforceMorphMap()` no `AppServiceProvider`:
```php
['usuario' => User::class, 'perfil' => Role::class, 'empresa' => Empresa::class,
 'obra' => Obra::class, 'pessoa-fisica' => PessoaFisica::class,
 'pessoa-juridica' => PessoaJuridica::class, 'importacao-pessoa' => ImportacaoPessoa::class]
```
- `integracao_logs.entidade_type` e `activity_log.subject_type`/`causer_type`
  passam a gravar o apelido, não o nome da classe. `App\Role` e
  `App\Permission` podem ir para `app/Models/` depois sem quebrar o histórico.
- `Auditoria::OBJETOS` (fatia 2) continua indexado pela classe. Para
  descrever o objeto, `descreverObjeto()` converte o apelido com
  `Relation::getMorphedModel()`.
- Bancos locais já semeados guardam `App\Models\User` em `activity_log`.
  Rodar `migrate:fresh --seed`; ainda não há dado real.

**D3.7 — Carga preguiçosa barrada fora de produção.**
`Model::preventLazyLoading(! app()->isProduction())` no `AppServiceProvider`.
As listas da fatia 7 (empresa da obra, PJs do vínculo) quebram no teste se
esquecerem o `with()`, em vez de fazerem N+1 consultas em produção.

---

## 3.1 Base de apoio — 2 h

**`App\Support\Documento`**
- `digitos(?string): ?string`: tira tudo que não é dígito; vazio vira `null`.
  Reaproveita o `filtraNumeros()` de `app/Http/helpers.php`.
- `formatar(?string): ?string`: 11 dígitos → `000.000.000-00`; 14 dígitos →
  `00.000.000/0000-00`; outro tamanho volta como está.

**Casts**
- `App\Casts\SomenteDigitos`: grava `Documento::digitos()` e lê sem mudança.
- `App\Casts\AsEndereco`:
  - grava a partir de `Endereco`, de array ou de `null`;
  - lê como `?Endereco`;
  - descarta chaves fora do formato;
  - normaliza `cep` para só dígitos e `uf` para maiúsculas.

**`App\Support\Endereco`** (readonly)
- `fromArray(array)`, `toArray()`.
- `formatado()`: "Rua X, 10 – Centro – Goiânia/GO – 74000-000", omitindo as partes vazias.
- `vazio()`: verdadeiro quando todas as chaves estão vazias. O cast grava `null` nesse caso.

**Enums** (D3.4) em `app/Enums/`, cada um com `rotulo(): string`.

**Trait `App\Models\Concerns\Ativavel`**
- `scopeAtivos()` e `scopeInativos()`.
- Usado pelos cadastros desta fatia e também pelo `User`. O filtro de situação
  do `UserController` (fatia 2) passa a usar os scopes.

**`AppServiceProvider::boot()`**: morph map (D3.6) e `preventLazyLoading` (D3.7).

**Testes**
- `tests/Unit/DocumentoTest.php`:
  - máscara removida;
  - zero à esquerda mantido (`012.345.678-90` → `01234567890`);
  - vazio e `null` → `null`;
  - formatação de CPF e de CNPJ.
- `tests/Unit/EnderecoTest.php`:
  - ida e volta pelo cast;
  - chave desconhecida descartada;
  - endereço vazio gravado como `null`;
  - `formatado()` sem partes vazias.
- Suíte inteira verde com o `preventLazyLoading` ligado. Corrigir o que aparecer.

## 3.2 Cadastros vindos do UAU — 2,5 h

Ordem das migrations: `empresas` → `obras` → `pessoas_fisicas` → `pessoas_juridicas` → `users`.

| Tabela | Colunas | Índices e chaves |
| --- | --- | --- |
| `empresas` | `uau_codigo` unsignedInteger, `cnpj` char(14) null, `razao_social`, `inscricao_estadual` null, `inscricao_municipal` null, `endereco` json null, `telefone` string(20) null, `ativo` bool default true, `sincronizado_em` timestamp null, `auditoria()` | único `uau_codigo`; índice `cnpj` |
| `obras` | `empresa_id` FK, `uau_codigo` string(20), `descricao`, `status_uau` smallint null, `tipo_uau` string(20) null, `endereco` json null, `data_inicio` date null, `data_fim` date null, `ativo`, `sincronizado_em`, `auditoria()` | único (`empresa_id`, `uau_codigo`); FK `restrictOnDelete` |
| `pessoas_fisicas` | `uau_codigo` unsignedInteger **null**, `cpf` char(11), `nome`, `nascimento` date null, `email` null, `telefone` string(20) null, `endereco` json null, `ativo`, `sincronizado_em`, `auditoria()` | único `cpf`; único `uau_codigo`; índice `nome` |
| `pessoas_juridicas` | `uau_codigo` unsignedInteger **null**, `cnpj` char(14), `razao_social`, `nome_fantasia` null, `regime_tributario` string(30) null, `inscricao_municipal` null, `inscricao_estadual` null, `email` null, `telefone` string(20) null, `endereco` json null, `ativo`, `sincronizado_em`, `auditoria()` | único `cnpj`; único `uau_codigo`; índice `razao_social` |
| `users` (alteração) | `pessoa_fisica_id` FK null, depois de `ativo` | único; `nullOnDelete` |

- **Decisão:** `uau_codigo` das pessoas é nullable. Na E2, uma pessoa
  cadastrada no sistema existe antes de ter código no UAU. O índice único
  aceita vários `NULL`, no MySQL e no SQLite.
- **Decisão:** `users.pessoa_fisica_id` é único: no portal (E5), um login
  pertence a uma pessoa física, e uma pessoa física tem no máximo um login.
- `obras.empresa_id` com `restrictOnDelete`: empresa com obra não sai do banco
  nem por exclusão física. Na prática ninguém exclui (D3.1).
- `status_uau` guarda o código cru (`Status_obr`). O rótulo depende da ABL
  PRIME (pendência externa) e entra na fatia 7.
- `regime_tributario` fica vazio na E1 e é preenchido na E2.

**Models** (`app/Models/`, com `RegistraAutoria`, `SoftDeletes`, `Ativavel`,
`HasFactory`, `$fillable` explícito e `casts()`):

| Model | `$table` | Casts | Relações |
| --- | --- | --- | --- |
| `Empresa` | `empresas` | `cnpj` SomenteDigitos, `endereco` AsEndereco, `ativo` bool, `sincronizado_em` datetime | `obras()` hasMany |
| `Obra` | `obras` | `endereco`, `ativo`, `data_inicio`/`data_fim` date, `sincronizado_em` | `empresa()` belongsTo |
| `PessoaFisica` | `pessoas_fisicas` | `cpf` SomenteDigitos, `nascimento` date, `endereco`, `ativo`, `sincronizado_em` | `usuario()` hasOne User; `vinculosSugeridos()` hasMany; `pessoasJuridicasSugeridas()` belongsToMany via `vinculos_sugeridos` |
| `PessoaJuridica` | `pessoas_juridicas` | `cnpj` SomenteDigitos, `endereco`, `ativo`, `sincronizado_em` | `vinculosSugeridos()`; `pessoasFisicasSugeridas()` |
| `User` (alteração) | — | — | `pessoaFisica()` belongsTo; `pessoa_fisica_id` em `$fillable` |

- Accessors `cpf_formatado` e `cnpj_formatado` (`Documento::formatar`), para a fatia 7.
- `$table` explícito em todos: o Laravel pluralizaria como `pessoa_fisicas`.

**Factories**
- CPF e CNPJ válidos (`fake('pt_BR')->cpf(false)`, `->cnpj(false)`), endereço completo.
- Estados: `inativo()`, `semCodigoUau()` (pessoas), `sincronizado()`.
- `ObraFactory` cria a empresa por padrão.

**Testes** (`tests/Feature/Models/CadastrosUauTest.php`)
- Cada factory cria um registro válido, e o `created_by` vem do usuário logado (`RegistraAutoria`).
- CPF e CNPJ gravados com máscara saem só com dígitos, inclusive com zero à esquerda.
- CPF repetido falha (`UniqueConstraintViolationException`), inclusive contra uma pessoa excluída (D3.1).
- O mesmo `uau_codigo` de obra em empresas diferentes é aceito; na mesma empresa, falha.
- Duas pessoas sem `uau_codigo` coexistem.
- Relações: `empresa->obras`, `obra->empresa`, `user->pessoaFisica`, `pessoaFisica->usuario`.
- Scopes `ativos()` e `inativos()`.
- Excluir empresa com obra falha na FK.

## 3.3 Integração e carga — 2,5 h

Ordem das migrations: `uau_mapeamentos` → `integracao_logs` → `importacoes_pessoas` → `importacao_pessoa_linhas` → `vinculos_sugeridos`.

| Tabela | Colunas | Índices e chaves |
| --- | --- | --- |
| `uau_mapeamentos` | `tipo` string(40), `chave_local` string(100), `codigo_uau` string(50), `auditoria()` | único (`tipo`, `chave_local`) |
| `integracao_logs` | `direcao` string(10), `nullableMorphs('entidade')`, `operacao` string(60), `rota` string(120), `chave_idempotencia` string(100) null, `payload` json null, `resposta` json null, `http_status` unsignedSmallInteger null, `status` string(10), `tentativas` unsignedTinyInteger default 1, `erro` text null, `duracao_ms` unsignedInteger null, `executado_em` timestamp(3), `timestamps()` | (`status`, `executado_em`); `executado_em`; `chave_idempotencia`; morph (automático) |
| `importacoes_pessoas` | `arquivo_path`, `nome_original`, `status` string(12) default `pendente`, `total_linhas` unsignedInteger default 0, `resumo` json null, **`erro` text null**, `iniciada_em` timestamp null, `concluida_em` timestamp null, `auditoria()` | (`status`, `created_at`) |
| `importacao_pessoa_linhas` | `importacao_pessoa_id` FK, `linha` unsignedInteger, `cpf` string(20) null, `cnpj` string(20) null, `resultado_pf` string(15) null, `resultado_pj` string(15) null, `mensagem` null, `timestamps()` | único (`importacao_pessoa_id`, `linha`); FK `cascadeOnDelete` |
| `vinculos_sugeridos` | `pessoa_fisica_id` FK, `pessoa_juridica_id` FK, `importacao_pessoa_id` FK null, `utilizado_em` timestamp null, `auditoria()` | único (`pessoa_fisica_id`, `pessoa_juridica_id`); FKs das pessoas `cascadeOnDelete`; FK da importação `nullOnDelete` |

- **Decisão:** `importacoes_pessoas.erro` (fora do plano da E1) guarda o motivo
  quando o status é `falhou`. Sem ele, a tela da fatia 5b não tem o que mostrar.
- **Decisão:** `cpf` e `cnpj` das linhas são `string(20)` e guardam o que veio
  na planilha, já sem máscara. Um documento inválido pode ter outro tamanho, e
  a linha precisa registrá-lo assim mesmo, com `resultado = invalida`.
- `resultado_pf`/`resultado_pj` ficam `null` quando a coluna da planilha está vazia.
- `executado_em` com milissegundos: ordena as chamadas de uma mesma cadeia.
- `resposta` é json. Uma resposta que não é JSON (o token do UAU é uma string
  pura, um erro 5xx pode vir em HTML) vai embrulhada como `{"bruto": "..."}`.
  Quem embrulha é o `UauClient` (fatia 4). Tirar senha e tokens do `payload`
  também é responsabilidade dele.
- `uau_mapeamentos`: o índice único também vale para linhas excluídas. A tela
  de de-para da E5 restaura a linha em vez de criar outra (D3.1).

**Models**

| Model | `$table` | Traits | Casts | Relações |
| --- | --- | --- | --- | --- |
| `UauMapeamento` | `uau_mapeamentos` | RegistraAutoria, SoftDeletes | — | — |
| `IntegracaoLog` | `integracao_logs` | — | `direcao` DirecaoIntegracao, `status` StatusIntegracao, `payload`/`resposta` array, `executado_em` datetime | `entidade()` morphTo |
| `ImportacaoPessoa` | `importacoes_pessoas` | RegistraAutoria, SoftDeletes | `status` StatusImportacao, `resumo` array, datas | `linhas()` hasMany; `vinculosSugeridos()` hasMany |
| `ImportacaoPessoaLinha` | `importacao_pessoa_linhas` | — | `resultado_pf`/`resultado_pj` ResultadoImportacao | `importacao()` belongsTo |
| `VinculoSugerido` | `vinculos_sugeridos` | RegistraAutoria, SoftDeletes | `utilizado_em` datetime | `pessoaFisica()`, `pessoaJuridica()`, `importacao()` belongsTo |

- `IntegracaoLog` sem `updated_at` relevante, mas com `timestamps()` para o
  `created_at`. Escopos `falhas()` e `noPeriodo($de, $ate)`, usados pela fatia 8
  e pelo indicador do topo.
- Os pivôs `pessoasJuridicasSugeridas()`/`pessoasFisicasSugeridas()` da 3.2
  usam `vinculos_sugeridos` com `->withPivot('utilizado_em', 'importacao_pessoa_id')->withTimestamps()`
  e `wherePivotNull('deleted_at')`.

**Factories** para os cinco models.
- `IntegracaoLogFactory` com os estados `sucesso()`, `erro()` e `paraEntidade($model)`.
- `ImportacaoPessoaFactory` com `concluida()` e `falhou()`.

**Testes** (`tests/Feature/Models/IntegracaoCargaTest.php`)
- Cada factory cria um registro válido.
- `IntegracaoLog`:
  - enums e json saem com o tipo certo;
  - `entidade_type` grava o apelido do morph map (`empresa`), e `entidade` volta o model;
  - os escopos `falhas()` e `noPeriodo()` funcionam.
- Excluir fisicamente uma importação apaga as linhas, e o vínculo fica com `importacao_pessoa_id = null`.
- O mesmo par PF + PJ não se repete no vínculo.
- `pessoaFisica->pessoasJuridicasSugeridas` traz a PJ da carga.

## 3.4 Verificação — 1 h

- `tests/Feature/MigrationsTest.php`: `migrate:reset` seguido de `migrate`
  roda sem erro. Isso prova que todo `down()` funciona, inclusive a remoção de
  FK do `users` no SQLite.
- À mão:
  - `php artisan migrate:fresh --seed` duas vezes seguidas, em SQLite e no
    MySQL do Docker, num banco temporário (não no de desenvolvimento);
  - `migrate:rollback --step=10` no MySQL;
  - `SHOW INDEX` nas tabelas novas.
- `docs/desenvolvimento.md`:
  - parágrafo "Modelo de dados": onde ficam casts, enums e o morph map;
  - a regra D3.1 (`ativo` × `deleted_at`).

## Fora desta fatia

| Item | Onde |
| --- | --- |
| Mappers, jobs e regras de upsert/inativação | Fatias 4 e 5 |
| Rules `Cpf`/`Cnpj` (dígito verificador) | Fatia 5b |
| Telas de consulta e rótulo do `status_uau` | Fatia 7 |
| Auditoria (`Auditavel`) nos cadastros: na E1 só a importação os altera, e ela já gera `integracao_logs` | E2, quando houver edição pela tela |
| `uau_alterado_em` nas pessoas (detecção de conflito) | E2 |
| Retenção de `integracao_logs` | E6, junto com a do `activity_log` |

## Pronto quando

1. `php artisan migrate:fresh --seed` roda duas vezes seguidas sem erro, em SQLite e em MySQL.
2. `migrate:reset` seguido de `migrate` funciona (teste automatizado).
3. Os nove models têm factory, e as factories geram dados válidos (CPF e CNPJ com dígito verificador correto).
4. CPF e CNPJ gravados com máscara saem do banco só com dígitos.
5. `vendor/bin/phpunit` verde, com o `preventLazyLoading` ligado.

## Riscos

| Risco | Saída |
| --- | --- |
| Tamanhos e tipos dos campos do UAU diferentes dos inferidos (ex.: código da obra numérico, razão social longa) | Tamanhos folgados aqui; a fatia 4 monta as fixtures a partir do Swagger e ajusta com uma migration nova, se preciso |
| O código da obra não ser único por empresa no UAU | O índice único quebra na primeira importação real; conferir no Swagger (`Obras/ObterObrasAtivas`) antes do commit da 3.2 |
| O morph map deixa registros antigos de `activity_log` (nome da classe) sem rótulo na tela | Só há dados locais; `migrate:fresh --seed`. O `getMorphedModel()` cai no nome da classe quando não acha o apelido |
| O `preventLazyLoading` quebra telas da fatia 2 | A suíte cobre as telas atuais; corrigir com `with()` no mesmo commit da 3.1 |
| Índice único de documento × exclusão lógica | Regra D3.1, repetida no plano da fatia 5 |
| `integracao_logs` cresce rápido (uma linha por chamada, conciliação diária) | Índices em `executado_em`; retenção decidida na E6 |
