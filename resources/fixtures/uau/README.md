# Dados de exemplo do UAU

Usados pelo `FakeUauClient` (`UAU_FAKE=true`) em desenvolvimento, na
homologação sem UAU real e nos testes. Formato exato dos modelos do Swagger da
UAU API v1.0 (lido em 2026-10-17). **Todos os dados são fictícios**: CPFs e
CNPJs têm dígito verificador válido, mas não pertencem a ninguém. Nunca coloque
aqui dados reais de pessoas (LGPD).

| Arquivo | Endpoint | Casos cobertos |
| --- | --- | --- |
| `empresas-ativas.json` | `Empresa/ObterEmpresasAtivas` | 3 empresas; a 2 tem CNPJ começando em 0 |
| `obras-ativas.json` | `Obras/ObterObrasAtivas` | `OB01` nas empresas 1 e 2; `OB02` com data final vazia do .NET (`0001-01-01`); `OB09` de uma empresa (7) que não está em `empresas-ativas` |
| `pessoas/<cpf ou cnpj>.json` | `Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus` | um arquivo por documento, só dígitos; documento sem arquivo = pessoa não encontrada |
| `versao.json` | `ConfigGerais/RetornarVersaoWS` | versão simulada |

Pessoas:

| Documento | Caso |
| --- | --- |
| `52998224725` | PF ativa; endereço de cobrança antes do principal; telefone residencial e celular |
| `74620153826` | PF inativa (`pessoaAtiva = 1`) |
| `27543198000106` | PJ ativa com nome fantasia; par da PF `52998224725` na carga da planilha |
| `03174682940` | PF com `cpfCnpj` mascarado, zero à esquerda, e `senhaUauWeb`, `loguinUauWeb` e `dadosBancarios` preenchidos (devem sumir do DTO e do log) |
