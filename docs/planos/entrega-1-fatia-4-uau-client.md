# E1 · Fatia 4 — UauClient + fake · 16 h · semana 2

Detalha a fatia 4 do [plano da Entrega 1](entrega-1-base-uau-leitura.md), na
ordem dos [próximos passos](entrega-1-proximos-passos.md). Depende da fatia 3
(`integracao_logs`, enums, `Documento`, `Endereco`). As fatias 5 e 8 dependem desta.

Ao fim desta fatia:
- o sistema fala com o UAU por uma interface única, `UauGateway`, com duas
  implementações: `UauClient` (HTTP real) e `FakeUauClient` (dados de exemplo);
- toda chamada, real ou simulada, grava uma linha em `integracao_logs`, sem senha, token ou dado bancário;
- falhas saem como duas exceções que os jobs da fatia 5 sabem tratar:
  `UauIndisponivel` (tentar de novo) e `UauRejeitou` (não tentar);
- `php artisan uau:ping` valida as credenciais no servidor;
- nenhum job, tela ou importação: isso é das fatias 5 a 8.

Branch `e1/fatia-4-uau-client`, a partir de `develop`. Um commit por tarefa, com os testes no mesmo commit.

## Tarefas e horas

| # | Tarefa | Horas |
| --- | --- | --- |
| 4.1 | Contrato: `UauGateway`, DTOs, exceções, enum de operações | 3 |
| 4.2 | Registro das chamadas em `integracao_logs`, com remoção de segredos | 3 |
| 4.3 | `UauClient`: autenticação, token em cache, 401, classificação de erros | 5 |
| 4.4 | `FakeUauClient`, dados de exemplo, ligação por `UAU_FAKE` | 3 |
| 4.5 | `uau:ping`, configuração e documentação | 2 |
| | **Total** | **16** |

---

## O contrato no Swagger (lido em 2026-10-17)

Fonte: `http://187.32.118.169:9556/uauAPI/1.0/swagger` (Swagger 2.0, "Globaltec
UAUWeb API v1.0", 431 endpoints). Lida só a documentação; nenhum endpoint foi chamado.

| Operação | Rota (relativa a `UAU_BASE_URL`) | Corpo | Headers | 200 |
| --- | --- | --- | --- | --- |
| Autenticar | `Autenticador/AutenticarUsuario` | `{Login, Senha}` | só `X-INTEGRATION-Authorization` | o token, como string JSON |
| Versão | `ConfigGerais/RetornarVersaoWS` | — | `X-INTEGRATION-Authorization` (o `Authorization` aparece como opcional) | string |
| Empresas | `Empresa/ObterEmpresasAtivas` | — | os dois | lista de `Empresa` |
| Obras | `Obras/ObterObrasAtivas` | — | os dois | lista de `Obra` (todas as empresas) |
| Pessoa | `Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus` | `{cpf_cnpj, status}`: só números; status 0 ativas, 1 inativas, 2 ambas | os dois | **um** objeto `Pessoa` |

- Todas as rotas são `POST`. Os erros 400 e 500 seguem o `ResponseHandler`:
  `{Mensagem, Descricao, Detalhe}`, sendo `Detalhe` o stack trace do servidor.
- O Swagger declara o host sem a porta. A porta vai na `UAU_BASE_URL`, como já está no `.env.example`.
- O Swagger não documenta 401, nem o que acontece quando a pessoa não existe
  ("Valida se encontrou a pessoa"). As duas regras abaixo valem até o primeiro acesso real (ver 4.3 e Riscos).

**Campos usados**

| Modelo | Campos |
| --- | --- |
| `Empresa` | `Codigo_emp` int, `Desc_emp`, `CGC_emp` (CNPJ, texto), `IE_emp`, `InscrMunic_emp`, `Endereco_emp` (**texto único**), `Fone_emp` |
| `Obra` | `Cod_obr` texto, `Empresa_obr` int, `Descr_obr`, `Status_obr` int, `TipoObra_obr` int, `Ender_obr` (**texto único**), `DtIni_obr`, `Dtfim_obr`, `DataAlt_obr` |
| `Pessoa` | `codigoPessoa` int, `tipo` (0 PF, 1 PJ), `cpfCnpj` texto, `nomePessoa`, `nomeFantasia`, `dataDeNascimento`, `email`, `pessoaAtiva` (0 ativa, 1 inativa), `inscricaoMunicipal`, `inscricaoEstadual`, `dataDeAlteracao`, `enderecos[]` (`tipoEndereco` 0 = principal, `endereco`, `numeroEndereco`, `complementoEndereco`, `bairro`, `cidade`, `uf`, `cep`), `telefones[]` (`ddd`, `numero`, `tipo` 2 = celular) |

**O que isso muda nas fatias 3 e 5** (nenhuma migration nova)
- **Endereço:** empresa e obra mandam o endereço num texto só. O mapper da
  fatia 5 grava esse texto em `endereco.logradouro`. A pessoa manda o endereço
  separado, e ele encaixa nas chaves do `Endereco`.
- **Código e tipo da obra:** `Cod_obr` é texto e vem junto com `Empresa_obr`,
  o que confirma o índice (`empresa_id`, `uau_codigo`). `TipoObra_obr` é inteiro e
  cabe em `tipo_uau` como texto. A unicidade real se confirma com os dados da homologação.
- **Pessoa:** vem um objeto só, não uma lista. O risco "mais de uma pessoa para
  o mesmo documento", do plano da E1, não existe na API documentada.
- **CPF/CNPJ:** vêm como texto, então o zero à esquerda é preservado. Mesmo
  assim, o DTO completa com zeros conforme o `tipo` (decisão D4.3), e a fatia 5 já recebe o documento pronto.
- **`dataDeAlteracao`:** o DTO já a carrega, para a detecção de conflito da E2 (`uau_alterado_em`).
- **Dados sensíveis na resposta:** a pessoa traz `senhaUauWeb`, `loguinUauWeb` e
  `dadosBancarios`. Nenhum dos três entra no DTO nem no log (D4.2).

---

## Decisões

**D4.1 — Contexto por chamada, sem estado no cliente.** O cliente é singleton,
e o worker do Horizon reaproveita a instância entre jobs. O job informa a que se
refere a chamada com
`$uau->comContexto(entidade: $pessoa, tentativa: $this->attempts())->pessoaPorDocumento(...)`.
O `comContexto()` devolve uma cópia; o singleton nunca guarda entidade nem tentativa.

**D4.2 — O log nunca guarda segredo nem dado bancário.**
- Os campos `Senha`, `senhaUauWeb`, `loguinUauWeb`, `dadosBancarios`, `token`,
  `Authorization` e `X-INTEGRATION-Authorization` são trocados por `"[oculto]"`
  em qualquer nível do `payload` e da `resposta`, sem diferenciar maiúsculas.
- A lista fica em `config('uau.campos_ocultos')`.
- Headers não são gravados.
- A resposta da autenticação (o token) é gravada como `"[oculto]"`.
- O `Detalhe` (stack trace do servidor) vai para `erro`, cortado em 2.000 caracteres.

**D4.3 — Os DTOs normalizam; os mappers da fatia 5 só traduzem.**
- `PessoaUau::fromArray()` limpa o documento (`Documento::digitos`) e completa
  com zeros: 11 dígitos se `tipo = 0`, 14 se `tipo = 1`.
- Datas `0001-01-01T00:00:00` (o "vazio" do .NET) viram `null`.
- Texto vazio vira `null`.
- O endereço principal da pessoa é o de `tipoEndereco = 0`, ou o primeiro da lista.

**D4.4 — Exceções**
- `UauRejeitou`: resposta 4xx, inclusive o segundo 401 seguido. Não adianta tentar de novo.
- `UauIndisponivel`: rede, timeout, 5xx e 429. O job tenta de novo com o backoff da fatia 5.
- `UauConfiguracaoInvalida`, lançada ao montar o cliente real, quando:
  - falta URL, login, senha ou token;
  - o login tem mais de 8 caracteres (autenticaria como cliente, segundo o Swagger);
  - a URL é `http://` fora do ambiente local, a menos que `UAU_EXIGIR_HTTPS=false`.
- As três estendem `UauException`, que carrega `mensagem` (do `ResponseHandler`), `http_status` e o id do log.

**D4.5 — "Pessoa não encontrada" é `null`, não erro.**
- `pessoaPorDocumento()` devolve `null` quando:
  - a resposta é 200 com corpo vazio ou `null`;
  - ou é 400 cuja `Mensagem` combina com `/n[ãa]o (foi )?encontrad|nenhum/i`.
- Qualquer outro 400 é `UauRejeitou`.
- A regra é provisória: confirmar com `uau:ping --documento=` no primeiro acesso real e ajustar o padrão.

**D4.6 — O fake também grava `integracao_logs`.** Assim a homologação e o
desenvolvimento com `UAU_FAKE=true` mostram dados na tela de monitoramento
(fatia 8). As linhas do fake usam a rota com prefixo `fake:`, para não se misturarem às reais.

**D4.7 — Os dados de exemplo ficam em `resources/fixtures/uau/`, não em `tests/Fixtures/`.**
- O fake roda em desenvolvimento e na homologação, então os dados fazem parte da aplicação, não só dos testes.
- O caminho fica em `config('uau.fake_fixtures')`.
- Os dados são fictícios, montados a partir dos modelos do Swagger; nenhum dado real de pessoa (LGPD).

**D4.8 — Prefixo do `Authorization` configurável.** O token vai sem "Bearer",
como inferido. Se o primeiro acesso real recusar, `UAU_AUTHORIZATION_PREFIXO="Bearer "` resolve sem mudar código.

---

## 4.1 Contrato — 3 h

Em `app/Integrations/Uau/`, conforme o D4 do README:

```
Integrations/Uau/
  UauGateway.php              # interface
  UauClient.php               # 4.3
  FakeUauClient.php           # 4.4
  RegistroIntegracao.php      # 4.2
  OperacaoUau.php             # enum
  Data/EmpresaUau.php, ObraUau.php, PessoaUau.php, EnderecoUau.php, TelefoneUau.php
  Exceptions/UauException.php, UauIndisponivel.php, UauRejeitou.php, UauConfiguracaoInvalida.php
```

**`UauGateway`**
```php
public function empresasAtivas(): array;                         // list<EmpresaUau>
public function obrasAtivas(): array;                            // list<ObraUau>
public function pessoaPorDocumento(string $documento): ?PessoaUau; // status 2: ativas e inativas
public function versao(): string;                                // usado pelo uau:ping
public function comContexto(?Model $entidade = null, ?string $chaveIdempotencia = null, int $tentativa = 1): static;
```

**Enum `OperacaoUau`** (backed, gravado em `integracao_logs.operacao`, com `rotulo()` para a fatia 8):
`autenticar`, `versao`, `empresas-ativas`, `obras-ativas`, `pessoa-por-documento`.
A E2 e a E5 acrescentam as operações de escrita.

**DTOs** (`readonly`, `fromArray(array $uau): self`; campos com nomes em português):

| DTO | Campos |
| --- | --- |
| `EmpresaUau` | `codigo` int, `razaoSocial`, `cnpj` ?string, `inscricaoEstadual`, `inscricaoMunicipal`, `endereco` ?string, `telefone` |
| `ObraUau` | `codigo` string, `empresaCodigo` int, `descricao`, `status` ?int, `tipo` ?int, `endereco` ?string, `dataInicio`/`dataFim` ?CarbonImmutable, `alteradaEm` |
| `PessoaUau` | `codigo` int, `tipo` (enum `TipoPessoaUau`: Fisica, Juridica), `documento`, `nome`, `nomeFantasia`, `nascimento`, `email`, `ativa` bool, `inscricaoMunicipal`, `inscricaoEstadual`, `alteradaEm`, `enderecos` list<EnderecoUau>, `telefones` list<TelefoneUau>; `enderecoPrincipal(): ?EnderecoUau`, `telefonePrincipal(): ?string` (celular, senão o primeiro) |
| `EnderecoUau` | `tipo`, `logradouro`, `numero`, `complemento`, `bairro`, `cidade`, `uf`, `cep`; `toEndereco(): App\Support\Endereco` |

**Testes** (`tests/Unit/Uau/DtosTest.php`, com os dados de exemplo da 4.4):
- tipos convertidos;
- data .NET vazia vira `null`;
- documento com máscara ou sem zero à esquerda sai com 11 ou 14 dígitos;
- `senhaUauWeb` e `dadosBancarios` não aparecem em nenhuma propriedade;
- endereço principal escolhido pelo `tipoEndereco`.

## 4.2 Registro das chamadas — 3 h

**`RegistroIntegracao::registrar(...)`**: grava uma linha de `IntegracaoLog` por chamada.
- `direcao = entrada`, `operacao`, `rota`, `payload` e `resposta` limpos (D4.2).
- `http_status`, `status` (`sucesso`/`erro`), `erro`, `duracao_ms`,
  `executado_em` (com milissegundos), e `entidade`, `chave_idempotencia` e
  `tentativas` vindos do contexto (D4.1).
- Resposta que não é JSON vai como `{"bruto": "<até 2.000 caracteres>"}`.
- Gravar no log nunca derruba a chamada: falha ao gravar → `report($e)`, e a exceção original segue.
- A linha é gravada fora de transação. **Regra para a fatia 5:** o job não
  envolve a chamada ao UAU em `DB::transaction`, senão o rollback apaga o log da falha.

**`Sanitizador::limpar(array): array`**: recursivo; troca os campos de `uau.campos_ocultos` por `"[oculto]"`, sem diferenciar maiúsculas.

**Testes** (`tests/Unit/Uau/SanitizadorTest.php`, `tests/Feature/Uau/RegistroIntegracaoTest.php`):
- campo oculto em qualquer nível, inclusive dentro de listas;
- resposta não JSON vira `bruto`;
- milissegundos gravados;
- entidade gravada com o apelido do mapa polimórfico;
- falha ao gravar não esconde a exceção original.

## 4.3 UauClient — 5 h

Sobre o `Illuminate\Http\Client\Factory`, injetado (testável com `Http::fake`).

- **Base:**
  - `baseUrl(config('uau.base_url'))`, `timeout(config('uau.timeout'))`, `acceptJson()` e `asJson()`;
  - todas as chamadas por `POST`;
  - sem retentativa no cliente: quem tenta de novo é o job.
- **Autenticação:**
  - `Autenticador/AutenticarUsuario` com `{Login, Senha}` e só o header `X-INTEGRATION-Authorization`;
  - a resposta é uma string JSON (o token entre aspas): decodificar e tirar as aspas;
  - token em `Cache::remember('uau.token', config('uau.token_ttl'))`.
- **Demais chamadas:** `X-INTEGRATION-Authorization` + `Authorization: {prefixo}{token}` (D4.8).
- **401:** apaga o token do cache, autentica de novo e repete a chamada uma vez.
  Um segundo 401 vira `UauRejeitou`. As duas tentativas ficam no log.
- **Classificação** (D4.4):
  - `ConnectionException` (rede e timeout), 5xx e 429 → `UauIndisponivel`;
  - outros 4xx → `UauRejeitou`, com `Mensagem` e `Descricao` do `ResponseHandler`.
- **Pessoa:** corpo `{cpf_cnpj: Documento::digitos($documento), status: 2}`; "não encontrada" vira `null` (D4.5).
- **Configuração** validada no construtor (D4.4). O fake não valida, porque não usa credencial.

**Testes** (`tests/Feature/Uau/UauClientTest.php`, `Http::fake` + `Http::assertSent`):
- a autenticação manda `Login`/`Senha` e o header de integração, sem `Authorization`;
- as demais chamadas mandam os dois headers, e o token sem "Bearer" por padrão;
- duas chamadas seguidas fazem uma autenticação só (token em cache);
- 401 → nova autenticação e repetição; 401 duas vezes → `UauRejeitou`;
- 400 → `UauRejeitou` com a `Mensagem`;
- 500, 429 e `ConnectionException` → `UauIndisponivel`;
- pessoa 200 vazia e 400 "não encontrada" → `null`;
- cada chamada, inclusive a autenticação, gera uma linha de log, sem `Senha`,
  token nem `senhaUauWeb` em `payload` e `resposta`;
- URL `http://` em produção, login com 9 caracteres e token vazio → `UauConfiguracaoInvalida`.

## 4.4 FakeUauClient — 3 h

**Dados de exemplo** em `resources/fixtures/uau/`, no formato exato do Swagger:

| Arquivo | Conteúdo, pensado para os testes das fatias 5 a 7 |
| --- | --- |
| `empresas-ativas.json` | 3 empresas; uma com CNPJ começando em 0 |
| `obras-ativas.json` | 4 obras; o mesmo `Cod_obr` em duas empresas; uma com `Dtfim_obr` vazio no padrão .NET; uma de empresa que não está em `empresas-ativas` (caso da fatia 5) |
| `pessoas/<documento>.json` | PF ativa com endereço e celular; PF inativa (`pessoaAtiva = 1`); PJ ativa com nome fantasia; uma pessoa com `cpfCnpj` mascarado e `senhaUauWeb` e `dadosBancarios` preenchidos |

**`FakeUauClient`**
- Monta os DTOs com os mesmos `fromArray()`, então os dados de exemplo testam a leitura de verdade.
- Documento sem arquivo → `null` (não encontrada).
- Grava `integracao_logs` com rota `fake:<rota>` (D4.6).
- Guarda as chamadas em memória: `chamadas(): list<array{operacao, argumentos, contexto}>`, para as asserções da fatia 5.
- `falharEm(OperacaoUau $operacao, UauException $erro, int $vezes = 1)`: simula falhas, para testar a retentativa da fatia 5.

**Ligação** em `AppServiceProvider::register()`:
```php
$this->app->singleton(UauGateway::class, fn ($app) => config('uau.fake')
    ? $app->make(FakeUauClient::class)
    : $app->make(UauClient::class));
```

**Testes** (`tests/Feature/Uau/FakeUauClientTest.php`):
- devolve os dados de exemplo;
- registra as chamadas e grava o log;
- `falharEm` lança o erro na quantidade de vezes pedida e depois volta ao normal;
- `UAU_FAKE=true` resolve o fake, e `false` resolve o `UauClient`.

## 4.5 uau:ping, configuração e documentação — 2 h

**`app/Console/Commands/UauPing.php`** (`php artisan uau:ping {--documento=}`):
- Mostra o modo (fake ou real) e a URL base, sem credenciais.
- Valida a configuração, autentica, chama `versao()` e mostra a versão e o tempo de resposta.
- Com `--documento`: consulta a pessoa e mostra só "encontrada (PF, código X,
  ativa)" ou "não encontrada", sem nome nem dados pessoais. Serve para confirmar o D4.5 no primeiro acesso real.
- Código de saída: 0 em sucesso, 1 em falha, com a `Mensagem` do UAU.

**`config/uau.php`**, chaves novas:
- `authorization_prefixo` (`UAU_AUTHORIZATION_PREFIXO`, padrão vazio);
- `exigir_https` (`UAU_EXIGIR_HTTPS`, padrão `true`);
- `campos_ocultos`;
- `fake_fixtures`.

`.env.example`: as duas variáveis novas, comentadas.

**`docs/desenvolvimento.md`**, seção "Integração UAU":
- fake × real;
- como rodar `uau:ping`;
- regra do log sem segredos;
- regra de não chamar o UAU dentro de transação.

**Testes** (`tests/Feature/Uau/UauPingTest.php`):
- contra o fake: sai com 0 e mostra a versão;
- `--documento` desconhecido: "não encontrada";
- cliente real com configuração inválida: sai com 1 e explica o motivo.

---

## Fora desta fatia

| Item | Onde |
| --- | --- |
| Jobs, backoff, `ShouldBeUnique`, mappers DTO → model | Fatia 5 |
| Tela de monitoramento e rótulos de `OperacaoUau` na tela | Fatia 8 |
| Primeiro acesso real: confirmar o D4.5 (pessoa não encontrada) e o D4.8 (prefixo) | Quando houver HTTPS e token |
| `regime_tributario` a partir de `dadosPessoaJuridica.optanteSimples` | E2 |
| Escrita no UAU (`Pessoas/GravarPessoa`, `ProcessoPagamento/GerarProcesso`) | E2 e E5 |

## Pronto quando

1. `vendor/bin/phpunit` verde, com os testes de 4.1 a 4.5.
2. `php artisan uau:ping` sai com 0 contra o fake.
3. Com `UAU_FAKE=false` e configuração inválida, o `uau:ping` explica o motivo e sai com 1, sem tentar a rede.
4. Nenhuma linha de `integracao_logs` gerada pelos testes contém `Senha`, token, `senhaUauWeb` ou `dadosBancarios`. Há um teste varrendo a tabela no fim de cada caso de cliente.
5. Os dados de exemplo cobrem os casos que as fatias 5 a 7 vão precisar (tabela da 4.4).

## Riscos

| Risco | Saída |
| --- | --- |
| A API real responde diferente do Swagger (formato do token, 401, pessoa não encontrada) | Prefixo e padrão de "não encontrada" isolados (D4.5, D4.8); `uau:ping --documento` confirma em minutos no primeiro acesso |
| A API continua só em HTTP | O cliente real recusa `http://` fora do ambiente local (D4.4). A homologação segue com o fake |
| Log de pessoa guarda dado pessoal (nome, nascimento, filiação) | Senhas e dados bancários ocultos (D4.2); a tela da fatia 8 exige `integracao-list`; a retenção é decidida na E6. Avisar a ABL PRIME no roteiro de homologação (LGPD) |
| Token expira no meio da conciliação | 401 renova uma vez e repete (4.3) |
| Duas autenticações simultâneas no worker (corrida no cache) | Aceitável na E1: no pior caso, uma autenticação a mais. Se o UAU limitar sessões, usar `Cache::lock` |
| O Swagger muda (versão nova do UAU) | Os dados de exemplo e os DTOs são o retrato de 2026-10-17; a seção "O contrato no Swagger" diz a fonte e a data |

---

## Implementação (2026-10-17)

Pronta na branch `e1/fatia-4-uau-client`: 5 commits, um por tarefa, cada um com
a suíte verde (184 testes no fim). `php artisan uau:ping` sai com 0 contra o
simulado no Docker. O ciclo de migrations foi testado no MySQL.

**Acréscimos ao plano, vindos da integração do SGTR com o mesmo UAU** (`sgtr/app/Services/UauService.php`)

| Acréscimo | Por quê |
| --- | --- |
| `RespostaUau` lê lista simples e DataSet do .NET (`[{"Tabela": [...]}]`), descartando a linha de esquema (`"System.Int32"`) | Endpoints do UAU respondem assim na prática, apesar do Swagger |
| Campos lidos sem diferenciar maiúsculas | A API mistura `cod_pes` e `CodPessoa` |
| Token aceito como string ou objeto (`token`/`Token`/`access_token`) | Visto no SGTR |
| `Authorization` sem "Bearer" confirmado como padrão | O cURL que funciona no SGTR usa o token puro |
| Header `X-Correlation-ID` em toda chamada, gravado na coluna nova `integracao_logs.correlacao` (migration `2026_10_17_140001`) | Localizar uma chamada junto ao suporte da Globaltec |
| CNPJ da empresa também completado com zeros no DTO | Mesma regra da pessoa |

**Outras decisões tomadas na implementação**
- Uma resposta de lista que não é lista vira `UauRejeitou`, nunca lista vazia.
- O token em cache tem chave por URL + login: trocar a credencial não reaproveita o token antigo.
- O 401 na própria autenticação é credencial recusada, não "token expirado".
- A autenticação é registrada sem a entidade do job (não é sobre ela), só com a tentativa.

**Para a fatia 5**
- Uma lista vazia legítima do UAU (`[]`) chega como lista vazia. O job de
  importação não deve inativar tudo nesse caso: tratar zero empresas ou obras ativas como suspeito.
- Nos testes de retentativa, use `FakeUauClient::falharEm()`.

**Ainda pendente:** confirmar o D4.5 (pessoa não encontrada) e o D4.8 (prefixo)
no primeiro acesso real, com `uau:ping --documento=`.

