# E2 · Fatia 3 — Validação de CPF/CNPJ e consulta de CNPJ · 6 h

Entregue em 2026-11-18, na branch `e2/fatia-3-consulta-cnpj`. Detalha a fatia 3 do [plano da Entrega 2](entrega-2-cadastros.md).

## O que foi feito

- **Validação:** as rules `Cpf` e `Cnpj` da E1 já valem nos formulários de PF e PJ (fatia 2), com documento normalizado para dígitos antes da checagem de unicidade, e a chave PIX por tipo (CPF, CNPJ, e-mail, telefone ou aleatória).
- **Consulta de CNPJ:** botão "Buscar CNPJ" no formulário da PJ. `App\Integrations\ConsultaCnpj\ConsultaCnpj` consulta a BrasilAPI (`/api/cnpj/v1/{cnpj}`) e `DadosCnpj` converte a resposta em razão social, nome fantasia, e-mail, telefone, regime, situação cadastral e endereço. O botão preenche os campos e avisa em vermelho quando a situação não é ATIVA.
- **Cache de 24 h** para resposta e para "não encontrado". Erro da API e falha de rede **não** entram no cache.
- **Falha não bloqueia o cadastro:** sem resposta, CNPJ não encontrado ou CNPJ inválido, a tela só avisa para preencher à mão; nada é gravado pela consulta.
- Configuração em `config/cadastros.php` (`CONSULTA_CNPJ_URL`, `CONSULTA_CNPJ_TIMEOUT`); nada a mudar no `.env` para usar a BrasilAPI.

## Decisões

- **D3.1 — Regime tributário.** `opcao_pelo_mei` vira MEI, `opcao_pelo_simples` vira Simples Nacional, e senão vale a forma de tributação mais recente do histórico (Lucro Real ou Presumido). Sem informação, o campo fica como está.
- **D3.2 — Preenche por cima.** O clique no botão é um pedido explícito, então os campos retornados substituem os digitados. Campos que a consulta não traz (e-mail, complemento) não são apagados.
- **D3.3 — Nomes de campo conferidos.** Os nomes da BrasilAPI foram conferidos numa consulta real, em 2026-11-18. Os testes usam uma resposta no mesmo formato; se a BrasilAPI mudar o formato, só `DadosCnpj::daBrasilApi` muda.

## Risco

A BrasilAPI é gratuita e sem acordo de nível de serviço. Se a ABL PRIME preferir um serviço pago, basta trocar `ConsultaCnpj`; o resto da tela não muda.
