# Verificações — Blocos 0 e 6

Resultados medidos, com data. O que não pôde ser medido está marcado como
pendente, com o motivo.

## Bloco 0.1 — Endpoints ZDR · ✅ 14/09/2026

Consulta a `https://openrouter.ai/api/v1/endpoints/zdr`. **O campo é `model_id`,
não `model_slug`** — o comando do plano não retornaria nada.

```bash
curl -s https://openrouter.ai/api/v1/endpoints/zdr \
  | jq -r '.data[] | select(.model_id=="google/gemini-2.5-flash-lite")'
```

| Modelo | Papel | Provedor ZDR | Prompt (US$/tok) | Saída | Leitura de cache |
|---|---|---|---|---|---|
| `google/gemini-2.5-flash-lite` | principal | Google | 1,0e-7 | 4,0e-7 | 1,0e-8 |
| `openai/gpt-5-nano` | reserva | Azure | 5,0e-8 | 4,0e-7 | 1,0e-8 |
| `qwen/qwen3-30b-a3b-instruct-2507` | reserva 2 | SiliconFlow | 9,0e-8 | 3,0e-7 | — |
| `openai/whisper-large-v3-turbo` | transcrição | DeepInfra | 3,33e-6 | — | — |

**Aceite atendido:** o principal e dois reservas têm endpoint de retenção zero.
O principal fica como está.

Achado que vale dinheiro: a leitura de cache do principal é **0,1× do preço de
prompt** — exatamente a premissa em que o `PromptBuilder` se apoia (bloco fixo
primeiro, nada variável dentro dele).

## Bloco 0.2 — Áudio `.oga` na transcrição · ⏸ pendente

Não executável aqui: exige `OPENROUTER_API_KEY` e um arquivo de áudio exportado
do WhatsApp. Nenhum dos dois existe nesta máquina.

O código já cobre os dois desfechos: `OpenRouterClient::transcribe()` manda
`multipart` com o arquivo, e a falha de transcrição não silencia o cliente —
cai em handoff com a mensagem de escape (`ProcessIncomingMessage`).

Para executar:

```bash
curl https://openrouter.ai/api/v1/audio/transcriptions \
  -H "Authorization: Bearer $OPENROUTER_API_KEY" \
  -F file=@audio.oga -F model=openai/whisper-large-v3-turbo
```

## Bloco 0.3 — Guardrail de gasto · ⏸ pendente

Ação no painel da OpenRouter, com a conta do dono. Nada a fazer no código.

## Bloco 6 — WAHA sem parear · ✅ 14/09/2026

Container `devlikeapro/waha:noweb-2026.8.2`, sem `ports:`, exercitado de dentro
da rede `sail`.

| Verificação | Resultado |
|---|---|
| `GET /api/version` sem `X-Api-Key` | **401** |
| `GET /api/version` com a chave | 200 · `{"version":"2026.8.2","engine":"NOWEB","tier":"CORE"}` |
| Criar sessão pelo `WahaProvider` | sessão criada, webhook configurado pelo próprio provider |
| Ciclo de vida sem celular | `STARTING` → `SCAN_QR_CODE` |
| Webhook real chegando à aplicação | 2 eventos `session.status`, ambos **200** |
| HMAC | POST sem assinatura → **401**; eventos assinados pelo WAHA → 200 |
| Tradução do evento real | `{"session":"bloco6","estado":"STARTING"}` e `...SCAN_QR_CODE` |
| QR pela API | PNG de 5.425 bytes, 292×292 |

**Aceite atendido:** evento emitido pelo WAHA real chegou ao endpoint, passou
pelo HMAC e foi traduzido corretamente.

### Dois achados

1. **`format=raw` não devolve imagem.** Devolve `{"value":"https://wa.me/settings/linked_devices#2@..."}`,
   a string de pareamento. `getQr()` foi corrigido para `format=image` e a
   diferença virou teste.
2. **A tag `noweb-2026.8.2` não publica `arm64`.** Em Mac Apple Silicon ela roda
   emulada; o compose fixa `platform: linux/amd64`.

### RAM — medição inconclusiva nesta máquina

| Estado | Memória do container |
|---|---|
| 1 sessão em `SCAN_QR_CODE` | 384 MiB |
| Sessão parada | 393 MiB |

A referência publicada para NOWEB é 60–80 MB por sessão. O que foi medido é o
container inteiro sob emulação amd64, e a diferença entre "com sessão" e "sem
sessão" ficou dentro do ruído. **Refazer em host amd64 nativo antes de usar para
dimensionar servidor.**

### `media/convert/voice` · ⏸ pendente

Fica com o 0.2: sem `ffmpeg` nesta máquina e sem áudio de origem, não dá para
gerar um arquivo de teste honesto.
