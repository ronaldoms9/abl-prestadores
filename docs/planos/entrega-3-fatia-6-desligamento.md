# E3 · Fatia 6 — Desligamento · 14 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 6 do [plano da Entrega 3](entrega-3-contratos-beneficios.md).

## O que foi feito

- **Desligar** (`DesligarColaborador`), pela ficha do colaborador: último dia de trabalho, motivo e observação. Na mesma transação gera o distrato, encerra remuneração, benefícios e equipes abertos (e apaga o que estava agendado depois da data), encerra o contrato, cancela rascunhos pendentes e passa o status a `desligado`. Tudo ou nada.
- **Distrato** pelo modelo ativo de distrato (escolhido na tela quando há mais de um). Opcional na tela; se pedido e faltar dado ou modelo, nada muda e a mensagem diz o que falta. Sem contrato gerado ou assinado, desliga e avisa que não houve distrato.
- **Prévia dos valores finais** (`PreviaDesligamento`), recalculada ao mudar a data: remuneração proporcional mais benefícios fixos. **Limite decidido:** a prévia não inclui benefícios por dia trabalhado, faltas nem feriados, que entram no fechamento do mês (E4). A tela mostra o aviso, e ele consta do roteiro de homologação.
- **`RemuneracaoProporcional`**: remuneração vigente em cada dia, mês a mês, em centavos. É o mesmo cálculo que a `CalculadoraFechamento` da E4 vai chamar. Base `corridos` (valor ÷ 30 × dias ativos, mês inteiro vale o cheio, limitado ao valor mensal) ou `dias_uteis` (segunda a sexta), em `configuracoes[calculo.base_proporcional]`.
- **Bloqueio:** desligado não recebe remuneração, benefício, contrato novo nem aditivo (as ações recusam pelo status). Fica de fora do reajuste em lote e dos aditivos automáticos. O distrato é o único documento que sai.
- Permissão nova: `colaborador-desligar`.

## Decisões

- **D6.1 — A data é o último dia de trabalho (inclusive).** O que estava aberto fecha no dia seguinte (fim exclusivo). Isso ajustou `VincularBeneficio`: o benefício não pode começar *depois* dessa data.
- **D6.2 — Só até hoje.** Desligamento futuro não é agendado nesta versão. Em competência fechada, recusa.
- **D6.3 — Quem está em pré-admissão pode ser "desligado"** (admissão cancelada): libera a pessoa para um novo cadastro, sem valores a pagar.
- **D6.4 — Benefício fixo proporcional por padrão** (spec 4.2, "a confirmar"), em `configuracoes[calculo.beneficio_fixo_proporcional]`.

## Pendente (perguntas à ABL PRIME)

- Base da proporcionalidade: dias corridos ou úteis (spec 4.3). Padrão atual: corridos.
- Benefício fixo é proporcional na admissão e no desligamento (spec 4.2). Padrão atual: sim.
