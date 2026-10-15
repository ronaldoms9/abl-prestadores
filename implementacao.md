# Implementação — trabalho que não depende de chip

Plano de execução dos blocos que podem ser construídos e testados **sem um número
de WhatsApp conectado**. Cobre a Fase 1 inteira do plano e antecipa boa parte da
Fase 3, aproveitando o tempo em que o chip de demonstração ainda está aquecendo.

- **Total estimado:** ~63h (5 a 6 semanas a 12h/semana)
- **Registro de decisões:** https://claude.ai/code/artifact/56683175-38aa-4c47-83d7-0e0a8cce31a1
- **Base:** Laravel 12 / PHP 8.4, MySQL, Blade. Front ainda em Mix (migra só na Fase 4).

## Convenções

Seguindo o que o boilerplate já faz:

- **Classes, métodos e namespaces em inglês** (`User`, `Role`, `Permission`).
- **Colunas de banco e strings de UI em português** (`telefone`, `cpf`).
- Tudo que fala com o WAHA vive sob `App\WhatsApp\` e não vaza para o resto.

## Ordem

Os blocos 1 e 2 são independentes entre si. O 3 depende do 2 (precisa do container
no ar para fechar a assinatura do cliente HTTP), o 4 depende do 3, e o 5 depende do 4.
O bloco 0 não depende de nada e deve vir primeiro porque pode mudar uma decisão.

```
0 ──► 1 ──┐
          ├──► 3 ──► 4 ──► 5
     2 ───┘                6
```

---

# Bloco 0 · Matar as duas incógnitas

**3h · sem dependências · faça antes de escrever código**

Duas das três premissas marcadas para o ponto de corte de 08/11 não precisam de chip.
Resolvê-las agora evita construir em cima de uma escolha errada.

## 0.1 — O modelo escolhido tem endpoint com retenção zero?

A lista de endpoints ZDR é pública e atualizada automaticamente:

```bash
curl -s https://openrouter.ai/api/v1/endpoints/zdr \
  | jq '.data[] | select(.model_slug | test("gemini-2.5-flash-lite|gpt-5-nano|qwen3"))'
```

Cruzar com os candidatos: `google/gemini-2.5-flash-lite` (principal),
`openai/gpt-5-nano` e `qwen/qwen3-30b-a3b-instruct-2507` (reservas).

**Aceite:** o modelo principal e pelo menos um reserva aparecem na lista.
Se o principal não aparecer, trocar o principal **agora**, não depois.

## 0.2 — Áudio de WhatsApp é aceito direto na transcrição?

Áudio de WhatsApp é Opus em container OGG (`.oga`). Não é preciso chip nenhum:
mande um áudio para si mesmo no seu WhatsApp atual e exporte o arquivo.

```bash
curl https://openrouter.ai/api/v1/audio/transcriptions \
  -H "Authorization: Bearer $OPENROUTER_API_KEY" \
  -F file=@audio.oga \
  -F model=openai/whisper-large-v3-turbo
```

A API também aceita JSON com `input_audio: {data: "<base64>", format: "ogg"}`.
Testar as duas formas e anotar qual respondeu — a documentação lista `ogg` como
"OGG Vorbis", e não está confirmado que Opus passa.

**Aceite:** transcrição correta de um áudio em português, com o custo em `usage`.
Se falhar, testar `POST /api/{session}/media/convert/voice` do WAHA no Bloco 6
e anotar o codec de saída.

## 0.3 — Teto de gasto antes da primeira chamada

Criar um Guardrail na OpenRouter com limite em dólares com reset mensal, e
associá-lo à chave usada em desenvolvimento. É a trava que sobrevive a um laço
infinito no seu próprio código.

**Aceite:** a chave de desenvolvimento tem teto e não é a chave de produção.

---

# Bloco 1 · Fundação de dados

**8h · independente**

Introduz `Tenant` como entidade raiz. Hoje `User` é a raiz de tudo; depois desta
mudança, `Role` e `Permission` passam a ser controle do **seu time**, não do cliente.

## 1.1 — Limpeza

Remover `app/Models/Registro.php` e `app/Models/RegistroLog.php` — são stubs vazios
do boilerplate, sem migration nem uso.

## 1.2 — Migration `tenants`

| Coluna | Tipo | Nota |
|---|---|---|
| `id` | bigIncrements | |
| `nome` | string | |
| `slug` | string, unique | |
| `ativo_ate` | date, nullable | vencido = bot parado |
| `waha_instance` | string, default `waha-1` | existe desde já, mesmo com uma instância |
| `waha_session` | string, unique, nullable | nome da sessão no WAHA |
| `teto_mensagens_mes` | unsignedInteger, default 3000 | teto rígido, sem excedente |
| `mensagens_mes` | unsignedInteger, default 0 | zerado por job mensal |
| `timestamps` | | |

## 1.3 — Migration `add_tenant_id_to_users`

`tenant_id` bigInteger unsigned **nullable**, com índice e chave estrangeira.
Nulo identifica o seu time interno — é o que mantém o `Role`/`Permission` existente
funcionando sem reescrita.

## 1.4 — `App\Models\Tenant`

Relação `hasMany(User::class)` e `hasOne(BotConfig::class)`. Um método
`estaAtivo(): bool` comparando `ativo_ate` com hoje.

## 1.5 — `App\Models\Concerns\BelongsToTenant`

Trait com:

- `static::addGlobalScope` filtrando por `tenant_id` quando há tenant no contexto;
- `static::creating` preenchendo `tenant_id` automaticamente.

**Atenção — a armadilha desta trait:** dentro de um worker de fila não existe usuário
autenticado, então um escopo que dependa de `auth()` silenciosamente não filtra nada.
Resolver com um singleton explícito:

```php
// App\Tenancy\TenantContext
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void { $this->tenant = $tenant; }
    public function get(): ?Tenant { return $this->tenant; }
    public function id(): ?int { return $this->tenant?->id; }
}
```

O middleware HTTP preenche a partir do usuário logado; **todo job recebe o
`tenant_id` no construtor e chama `set()` no início do `handle()`**. Sem isso,
um job de um cliente enxerga dados de outro.

## 1.6 — Middleware `ResolveTenant`

Registrado no grupo `web`, após `auth`. Preenche o `TenantContext` a partir de
`auth()->user()->tenant`.

## 1.7 — O `can()` sobrescrito

`App\Models\User::can()` sobrescreve o método do Laravel e faz uma consulta ao banco
a cada chamada — inclusive dentro de laços em views. Com escopo de tenant entrando,
isso vira risco de vazamento entre contas além de problema de desempenho.

Decisão mínima deste bloco: **manter o comportamento, mas memoizar** as permissões
por requisição. Renomear ou trocar por Gates é trabalho da Fase 4.

## 1.8 — Seeders

Um tenant de demonstração com um usuário dono, e um usuário interno com
`tenant_id = null` e papel de administrador.

**Aceite do bloco:** `php artisan migrate:fresh --seed` sobe um tenant com dono; um
teste confirma que um usuário de um tenant não enxerga registros de outro.

---

# Bloco 2 · Infra local

**6h · independente**

## 2.1 — Redis e Horizon

Adicionar o serviço `redis` ao `docker-compose.yml` (rede `sail`, sem publicar porta),
instalar `laravel/horizon`, publicar a configuração e trocar no `.env.example`:

```
QUEUE_CONNECTION=redis
CACHE_DRIVER=redis
SESSION_DRIVER=redis
```

Uma fila dedicada `whatsapp` com prioridade acima da `default` — resposta a cliente
final não pode ficar atrás de envio de e-mail.

## 2.2 — WAHA no compose

```yaml
waha:
    image: 'devlikeapro/waha:noweb-2026.8.2'
    restart: unless-stopped
    environment:
        WHATSAPP_DEFAULT_ENGINE: NOWEB
        WAHA_API_KEY: '${WAHA_API_KEY}'
        WHATSAPP_HOOK_HMAC_KEY: '${WAHA_HMAC_KEY}'
        WHATSAPP_HOOK_RETRIES_POLICY: linear
        WHATSAPP_HOOK_RETRIES_ATTEMPTS: 3
        WHATSAPP_HOOK_RETRIES_DELAY_SECONDS: 5
        WAHA_EVENTS_DOWNLOAD_MEDIA: 'True'
    volumes:
        - 'waha-sessions:/app/.sessions'
        - 'waha-media:/app/.media'
    networks:
        - sail
```

Pontos que não são detalhe:

- **Sem `ports:`.** O WAHA fica acessível só pela rede interna do Docker. Publicar
  a porta é entregar a API do WhatsApp de todos os clientes para a internet.
- **Versão fixada** (`noweb-2026.8.2`), nunca `latest`.
- **Engine NOWEB**, sem navegador — 60 a 80 MB por sessão contra 250 a 400 do WEBJS.
- **Volume `waha-sessions` é crítico.** Perdê-lo desconecta todos os clientes ao
  mesmo tempo e exige QR novo de cada um. Backup diário entra já no Bloco 2.

## 2.3 — Configuração

`config/whatsapp.php` com `base_url`, `api_key`, `hmac_key`, `engine`, `default_instance`
e `webhook_url`. Nada de `env()` fora de arquivo de config.

**Aceite do bloco:** `docker compose up` sobe app, MySQL, Redis, Horizon e WAHA;
`GET /api/version` do WAHA responde de dentro da rede com a API key, e falha sem ela.

---

# Bloco 3 · Camada de provider

**14h · depende do Bloco 2 · é aqui que a integração começa**

A fronteira que sustenta três coisas ao mesmo tempo: testes sem celular, a saída
futura para a API Oficial, e o isolamento do formato do WAHA.

## 3.1 — Estrutura

```
app/WhatsApp/
├── Contracts/WhatsAppProvider.php
├── Data/
│   ├── IncomingMessage.php
│   ├── SessionSnapshot.php
│   └── MediaRef.php
├── Enums/
│   ├── MessageKind.php
│   └── SessionState.php
├── Waha/
│   ├── WahaProvider.php
│   ├── WahaWebhookTranslator.php
│   └── WahaException.php
├── Fake/FakeWhatsAppProvider.php
└── WhatsAppServiceProvider.php
```

## 3.2 — A interface

```php
namespace App\WhatsApp\Contracts;

interface WhatsAppProvider
{
    public function startSession(string $session): void;
    public function stopSession(string $session): void;
    public function getQr(string $session): string;          // PNG em base64
    public function getStatus(string $session): SessionSnapshot;
    public function sendSeen(string $session, string $chatId): void;
    public function startTyping(string $session, string $chatId): void;
    public function stopTyping(string $session, string $chatId): void;
    public function sendText(string $session, string $chatId, string $text): void;
    public function downloadMedia(string $url): string;      // caminho local
}
```

Só isso. Nada de "múltiplos providers simultâneos" nem roteamento por tenant —
isso seria construir para um futuro que talvez não chegue.

## 3.3 — Enums

`MessageKind`: `Texto`, `Audio`, `Imagem`, `Documento`, `Localizacao`, `Contato`,
`Figurinha`, `Reacao`, `Outro`.

`SessionState`: `Starting`, `ScanQrCode`, `Working`, `Failed`, `Stopped`.

Mapear os valores crus do WAHA para esses enums **dentro do tradutor**, nunca fora.

## 3.4 — `IncomingMessage`

Objeto imutável (`readonly`): `session`, `chatId`, `messageId`, `kind`, `texto`,
`media` (`MediaRef|null`), `fromMe`, `timestamp`.

## 3.5 — `WahaProvider`

Cliente HTTP com a `X-Api-Key` em todas as chamadas, timeout curto (5s) e
`WahaException` para erro. **Tratar 463 e 475 como exceções de categoria própria** —
não são falha de rede nem sessão caída:

- `463` — Reachout Timelock. Restrição silenciosa; a sessão continua `WORKING`.
  **Nunca reiniciar, deslogar ou reparear.** Passa sozinho.
- `475` — Message Capping. Cota de contatos novos por ciclo.
  **Reiniciar não zera a cota.** Estados `FIRST_WARNING` e `SECOND_WARNING` são
  o aviso prévio do banimento — o único sinal que existe antes de perder o número.

## 3.6 — `WahaWebhookTranslator`

`translate(array $payload): ?IncomingMessage`. Devolve `null` para tudo que não
vira conversa, e é aqui que os filtros moram:

- `from` terminando em `@g.us` → grupo, descartar;
- `@broadcast` ou status → descartar;
- `fromMe: true` → mensagem do próprio dono, descartar;
- `Figurinha` e `Reacao` → descartar em silêncio;
- `Imagem`, `Localizacao`, `Documento`, `Contato` → não descartar, marcar para
  escalonamento humano.

## 3.7 — `FakeWhatsAppProvider`

Implementa a interface guardando tudo em memória: fila de textos enviados, estados
de "digitando", sessões criadas. Métodos de asserção para os testes
(`assertSentText`, `assertTyped`, `sentMessages()`).

É esta classe que economiza semanas — sem ela, cada teste exigiria WhatsApp real.

## 3.8 — Binding

`WhatsAppServiceProvider` resolve `WhatsAppProvider` para `FakeWhatsAppProvider`
quando `config('whatsapp.driver') === 'fake'`, e para `WahaProvider` caso contrário.
`phpunit.xml` fixa `fake`.

**Aceite do bloco:** um teste troca o provider por fake e verifica texto enviado;
outro teste passa um payload real de grupo pelo tradutor e recebe `null`.

---

# Bloco 4 · Webhook

**8h · depende do Bloco 3**

## 4.1 — Rota

```php
// routes/api.php
Route::post('/webhooks/waha', WahaWebhookController::class)
    ->middleware(['waha.hmac', 'throttle:600,1'])
    ->name('webhooks.waha');
```

Endpoint único. O tenant é resolvido pelo campo `session` do payload — mais simples
de configurar no WAHA que uma URL por cliente, e evita URL secreta como segurança.

## 4.2 — Middleware `VerifyWahaHmac`

O WAHA assina o corpo bruto com SHA-512 e envia:

- `X-Webhook-Hmac` — a assinatura
- `X-Webhook-Hmac-Algorithm` — `sha512`
- `X-Webhook-Timestamp` — Unix em milissegundos
- `X-Webhook-Request-Id`

Validação:

```php
$esperado = hash_hmac('sha512', $request->getContent(), config('whatsapp.hmac_key'));

if (! hash_equals($esperado, (string) $request->header('X-Webhook-Hmac'))) {
    abort(401);
}
```

Usar `hash_equals`, nunca `===`. Rejeitar timestamp fora de uma janela de 5 minutos
para barrar reenvio. Rejeitar também se o algoritmo anunciado não for `sha512`.

## 4.3 — Controller

Fino de propósito: traduz, descarta `null`, resolve o tenant pela sessão, despacha
o job e devolve `200` imediatamente. **Nenhuma chamada a LLM no ciclo HTTP** — o WAHA
tem política de retentativa e um webhook lento vira mensagem duplicada.

## 4.4 — Idempotência

Guardar `X-Webhook-Request-Id` (ou o `messageId`) em cache por alguns minutos e
descartar repetido. A retentativa do WAHA é configurada em 3 tentativas; sem isso,
uma resposta lenta vira três respostas ao cliente final.

**Aceite do bloco:** teste posta um payload com HMAC válido e vê o job na fila; posta
com HMAC inválido e recebe 401; posta o mesmo `Request-Id` duas vezes e só um job sai.

---

# Bloco 5 · Motor de conversa, sem WhatsApp

**20h · depende do Bloco 4**

Toda esta fase roda contra o `FakeWhatsAppProvider`. A configuração do bot ainda
vem de arquivo — o painel é Fase 4.

## 5.1 — Migrations

**`bot_configs`** — os oito campos decididos:
`tenant_id`, `nome_negocio`, `descricao` (text), `tom` (enum: formal/neutro/informal),
`horarios` (json), `produtos` (json), `faqs` (json), `nunca_faca` (text),
`quando_humano` (text), `saudacao` (text).

`produtos` estruturado é deliberado: é o que permite verificar, depois da resposta,
que o modelo não inventou preço.

**`conversations`** — `tenant_id`, `chat_id`, `estado` (enum: `bot`/`humano`/`pausada`),
`pausada_ate`, `debounce_token` (uuid), `ultima_mensagem_at`, `expurgo_em`.

**`messages`** — `conversation_id`, `tenant_id`, `direcao`, `tipo`, `conteudo` (text),
`transcricao` (text, nullable), `tokens_entrada`, `tokens_saida`, `custo_usd`, `modelo`.

**`metrics_daily`** — `tenant_id`, `data`, `conversas`, `resolvidas`, `fora_horario`,
`tokens_entrada`, `tokens_saida`, `custo_usd`. **Sobrevive ao expurgo de 90 dias** —
métrica agregada não é dado pessoal.

## 5.2 — `ProcessIncomingMessage` (job)

1. Chama `TenantContext::set()` — primeira linha, sempre.
2. Se for áudio, **baixa a mídia imediatamente**. O arquivo local do WAHA expira em
   **180 segundos**; se o download ficar para depois do debounce, o áudio some.
3. Transcreve e guarda o texto em `transcricao`.
4. Persiste a mensagem, cria ou atualiza a conversa.
5. Gera um `debounce_token` novo e agenda `RespondToConversation` com 8s de atraso,
   passando o token.

## 5.3 — `RespondToConversation` (job)

Primeira linha do `handle()`: se o `debounce_token` da conversa mudou, **retorna sem
fazer nada**. É assim que "mensagem nova cancela a geração em curso" funciona sem
depender de cancelamento de job.

Depois: monta o prompt, chama o LLM, interpreta, decide, envia.

## 5.4 — `PromptBuilder`

Ordem obrigatória, e o motivo importa:

1. **Bloco fixo primeiro** — a configuração do negócio, idêntica em toda mensagem
   daquele cliente. É o que o provedor consegue cachear, a 0,1× do preço.
2. **Histórico depois** — últimas 10 mensagens ou 24h, o que vier primeiro.

Nada variável (data, nome do contato, hora) pode entrar no bloco fixo, ou o cache
nunca acerta e você paga integral em toda mensagem. Esta é a decisão de maior
retorno por esforço do projeto inteiro, e é irrecuperável depois sem refazer o
montador.

## 5.5 — `OpenRouterClient`

```php
public function chat(array $messages, array $models, int $timeoutSeconds = 20): ChatResult;
public function transcribe(string $caminhoArquivo): string;
```

Corpo da chamada de chat:

- `models` — lista ordenada de fallback (principal + reserva de outro provedor);
- `provider.zdr` — `true`, retenção zero;
- modelo com **versão fixada**, nunca alias flutuante `~...-latest`;
- timeout de 20s e 2 tentativas no principal.

Se tudo falhar: mensagem de escape ao cliente final, notificação ao dono, conversa
marcada como pendente de humano. **Silêncio é a única falha que o dono nunca perdoa**,
porque ele perde venda sem saber.

## 5.6 — `BotReply` e as travas

O modelo responde JSON:

```json
{ "resposta": "...", "confianca": 0.91, "precisa_humano": false, "motivo": null }
```

Duas verificações antes de enviar, ambas no seu código:

- **`PriceGuard`** — extrai números da `resposta` e confere contra a lista de
  `produtos`. Número que não está na lista bloqueia o envio e escala.
- **Confiança** abaixo do limiar, ou `precisa_humano` verdadeiro, escala.

JSON inválido conta como falha e cai no caminho do 5.5.

## 5.7 — `MessageSender`

Sequência recomendada pela própria documentação anti-banimento do WAHA:

```
sendSeen → startTyping → espera de 2 a 4s → stopTyping → sendText
```

A espera não é cosmética: resposta instantânea em WhatsApp grita "robô", e o dono
não quer que o cliente dele perceba.

## 5.8 — Handoff

Escalar significa: marcar a conversa como `humano`, silenciar o bot naquele chat por
X horas, e notificar o dono. **Não existe caixa de entrada** — ele responde pelo
WhatsApp do celular dele, onde as mensagens já estão.

## 5.9 — Expurgo

Comando agendado que apaga `messages` e `conversations` com `expurgo_em` vencido
(90 dias), preservando `metrics_daily`.

**Aceite do bloco:** um teste posta quatro fragmentos de webhook em sequência
("oi", "bom dia", "queria saber", "vocês atendem sábado?"), roda a fila e verifica
que o `FakeWhatsAppProvider` recebeu **uma** resposta, não quatro. Outro teste
injeta uma resposta com preço inventado e verifica que ela foi bloqueada e escalada.

---

# Bloco 6 · Verificar o WAHA sem parear

**4h · depende do Bloco 2**

Tudo que dá para provar antes do chip chegar.

- Subir o container e exercitar a API com a `X-Api-Key`; confirmar que sem a chave falha.
- Criar uma sessão: ela entra em `STARTING` e depois `SCAN_QR_CODE` **sem nenhum
  celular envolvido** — e esses eventos disparam webhooks reais, que validam o HMAC
  e o tradutor de verdade, não com payload inventado.
- Buscar o QR pela API e confirmar que volta uma imagem.
- Medir a RAM do container com uma sessão não pareada (referência publicada para
  NOWEB: 60 a 80 MB por sessão).
- Se o Bloco 0.2 tiver falhado, testar `POST /api/{session}/media/convert/voice` e
  anotar o codec de saída.

**Aceite do bloco:** um evento `session.status` emitido pelo WAHA real chega ao seu
endpoint, passa pela validação de HMAC e é traduzido corretamente.

---

# O que continua precisando de chip

Para não haver ilusão sobre o que os seis blocos entregam:

- Receber uma mensagem de verdade de um cliente final
- Enviar mensagem de verdade
- Áudio ponta a ponta vindo do WhatsApp real
- Comportamento de banimento, Timelock e Capping em condições reais
- RAM de uma sessão **pareada e ativa**, que é diferente de ociosa

Isso é o ponto de corte de **08/11/2026**. Se os seis blocos estiverem prontos e o
chip aquecido, essa semana vira confirmação em vez de descoberta.
