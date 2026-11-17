# E3 · Fatia 3 — Geração de contrato, aditivo e distrato · 20 h

Implementada na branch `e3/fatia-1-models`. Detalha a fatia 3 do [plano da Entrega 3](entrega-3-contratos-beneficios.md).

## O que foi feito

- **Rascunho** (`CriarContrato`): o tipo vem do modelo. Contrato novo exige não haver outro gerado ou assinado em vigor nem outro rascunho; aditivo e distrato exigem o contrato pai do mesmo colaborador (gerado ou assinado); só um distrato por contrato; colaborador desligado só recebe distrato.
- **Pré-visualização** em modal, antes de gerar, com o aviso do que falta no cadastro.
- **Geração** (`GerarContrato`): numera, preenche o modelo com os dados vigentes na data de início e grava o texto. Se faltar dado para alguma variável do modelo, recusa e diz quais; nada é numerado nesse caso.
- **Numeração** (`ProximoNumeroContrato`): `AAAA/NNNN`; aditivos `AAAA/NNNN-A01`, `-A02`; distrato `AAAA/NNNN-D`. O contador do ano fica em `configuracoes` e é lido com lock na transação; o índice único do número é a segunda barreira. Prefixo e reinício anual em `configuracoes[contratos.numeracao]` (padrão: sem prefixo, reinicia todo ano).
- **PDF** em fila (`GerarPdfContrato`), por trás da interface `GeradorPdf`. A implementação com dompdf (`DompdfGerador`) é usada quando a biblioteca está instalada; sem ela, `PdfIndisponivel` falha com mensagem clara, o contrato continua gerado e a tela mostra "PDF pendente" com "gerar de novo".
- **Arquivo assinado** (`AnexarContratoAssinado`): PDF, JPG ou PNG de até 20 MB, em disco privado; leva o contrato a `assinado`.
- **Imutabilidade:** o model barra alteração dos campos do contrato depois do rascunho e a exclusão de contrato gerado. Só arquivos, assinatura e encerramento mexem.
- **Linha do tempo** na ficha do colaborador (contrato, aditivos e distrato, com download) e downloads por rota autorizada. O gestor só alcança contratos dos colaboradores das equipes que gere.
- Permissões novas: `contrato-list`, `contrato-gerar` e `contrato-assinar`.

## Decisões

- **D3.1 — O fim do contrato é inclusivo** (último dia de vigência). Diferente das linhas de remuneração e benefício, ele é uma data de contrato, e é assim que o RH a digita.
- **D3.2 — Cancelar só rascunho.** Contrato gerado é corrigido por aditivo.
- **D3.3 — Falha de PDF não desfaz o contrato.** O texto e o número já valem; só o arquivo fica pendente.

## Pendente

- **Instalar o dompdf:** `composer require dompdf/dompdf` (ou `barryvdh/laravel-dompdf`). A instalação foi negada no ambiente de desenvolvimento e não foi feita. Sem ele nenhum PDF é gerado. Com a biblioteca presente a ligação é automática (`AppServiceProvider`).
- Se o dompdf não reproduzir fielmente os modelos reais da ABL PRIME, o plano prevê Browsershot (+6 h).
