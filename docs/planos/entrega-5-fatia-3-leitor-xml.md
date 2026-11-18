# E5 · Fatia 3 — Leitor de XML (parte que não depende da ABL) · 16 h

Detalha a fatia 3 do [plano da Entrega 5](entrega-5-notas-contas-pagar.md). **Entregue só a infraestrutura e os dois padrões públicos; os layouts municipais reais e as amostras da ABL PRIME continuam pendentes** (município emissor e 2 a 3 XMLs de cada um).

## O que existe

- `LeitorNfse` (`suporta(DOMDocument)` e `ler(DOMDocument): NotaLida`) e o DTO `NotaLida` (número, série, chave, emissão, CNPJs, valores em centavos, retenções por tributo e o nome do layout).
- `LeitorXmlNfse`: porta de entrada. Recusa XML grande demais, com `DOCTYPE` ou `ENTITY` (entidade externa ou expansão), usa `LIBXML_NONET`, detecta o layout e devolve `NotaLida`. Layout desconhecido ou XML inválido vira `XmlNaoLido`, e a tela cai no preenchimento manual.
- `LeitorNfseNacional` (padrão nacional, ADN) e `LeitorNfseAbrasf` (ABRASF 2.x, base da maioria dos municípios). Procuram as tags pelo nome local, sem depender do namespace.

## Limites conhecidos

- Os dois leitores seguem a documentação pública dos padrões; os testes usam XML sintético escrito a partir dela. **Não foram validados com nota real.** Cada município que mudar tags ou usar layout próprio precisa de um leitor novo, com fixtures reais anonimizadas (orçamento: até 2 layouts).
- Cada novo leitor entra na lista de `LeitorXmlNfse::LEITORES`; nada mais muda.

## Decisão

- **D3.1 — Falha de leitura nunca bloqueia o envio.** O prestador (ou o RH) informa os campos à mão e a conferência roda do mesmo jeito.
