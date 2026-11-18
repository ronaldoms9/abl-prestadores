# Roteiro de validação da Entrega 5 — Notas fiscais e portal (rascunho)

Formato do [roteiro da Entrega 1](roteiro-validacao.md); critérios do [plano da entrega](../planos/entrega-5-notas-contas-pagar.md). Os critérios 3 e 4 (título no UAU) só entram quando a fatia 6 for feita.

**Antes de começar:** a ABL PRIME informa os municípios emissores e entrega 2 a 3 XMLs reais de cada um; define a tolerância de valor e o dia limite de envio; o RH liga cada usuário do portal à pessoa física (Configurações › Usuários).

## Critério 1 — O colaborador envia a nota pelo portal e os dados lidos batem com o XML

**Quem faz:** um colaborador real, com uma competência aprovada.
1. Entre no portal e abra Minhas notas.
2. Envie o PDF e o XML; confira os dados que o sistema mostra.
3. Envie.

**Esperado:** número, data, CNPJs, valor e retenções iguais ao XML; situação "Enviada" se tudo confere.

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 2 — Nota com problema vira divergente; recusada, o colaborador reenvia

1. Envie uma nota com o CNPJ do emissor de outra empresa.
2. Como financeiro, abra Notas fiscais e recuse com um motivo.
3. Como colaborador, reenvie a nota correta.

**Esperado:** a nota fica "Divergente" com a explicação; a recusa chega por e-mail; o reenvio aproveita a mesma linha e volta a "Enviada".

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Critério 5 — Férias pelo portal chegam aos gestores

1. Como colaborador de uma equipe com gestor, solicite férias em Minhas férias.

**Esperado:** o gestor da equipe recebe o e-mail e vê o pedido em "Aguardando a minha decisão".

| Resultado | Observação |
| --- | --- |
| ☐ ok ☐ não ok | |

## Alertas e download em lote

Depois do dia limite, quem não enviou recebe lembrete e o financeiro recebe o resumo. Em Notas fiscais, "Baixar ZIP" gera o arquivo e envia o link por e-mail (vale 24 horas).

## O que não faz parte desta entrega (ainda)

Título de contas a pagar no UAU e leitores de XML de layouts municipais próprios.

## Aprovação

| Nome | Cargo | Data | Assinatura |
| --- | --- | --- | --- |
| | | | |
