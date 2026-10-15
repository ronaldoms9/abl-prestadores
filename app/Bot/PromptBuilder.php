<?php

namespace App\Bot;

use App\Models\BotConfig;
use App\Models\Conversation;
use App\Models\Message;

/**
 * Monta o prompt na ordem que o cache do provedor consegue aproveitar.
 *
 * Bloco fixo primeiro (a configuração do negócio, idêntica em toda mensagem
 * daquele cliente, cacheável a 0,1x do preço), histórico depois. Nada variável
 * — data, hora, nome do contato — pode entrar no bloco fixo, ou o cache nunca
 * acerta e se paga integral em toda mensagem.
 */
class PromptBuilder
{
    public const HISTORICO_MAX_MENSAGENS = 10;
    public const HISTORICO_MAX_HORAS = 24;

    /** @return array<int, array{role: string, content: string}> */
    public function build(BotConfig $config, Conversation $conversa, string $mensagemAtual): array
    {
        $mensagens = [
            ['role' => 'system', 'content' => $this->blocoFixo($config)],
        ];

        foreach ($this->historico($conversa) as $mensagem) {
            $mensagens[] = [
                'role' => $mensagem->direcao === Message::ENTRADA ? 'user' : 'assistant',
                'content' => (string) $mensagem->textoUtil(),
            ];
        }

        $mensagens[] = ['role' => 'user', 'content' => $mensagemAtual];

        return $mensagens;
    }

    /** Idêntico entre chamadas do mesmo cliente — é o que o provedor cacheia. */
    public function blocoFixo(BotConfig $config): string
    {
        $partes = [];

        $partes[] = "Você é o atendente virtual de {$config->nome_negocio}.";
        $partes[] = 'Responda sempre em português do Brasil, em no máximo 3 frases curtas.';
        $partes[] = 'Tom de voz: '.$this->tom($config->tom).'.';

        if ($config->descricao) {
            $partes[] = "Sobre o negócio:\n{$config->descricao}";
        }

        if ($config->saudacao) {
            $partes[] = "Saudação padrão:\n{$config->saudacao}";
        }

        if ($horarios = $this->horarios($config)) {
            $partes[] = "Horários de atendimento:\n{$horarios}";
        }

        if ($produtos = $this->produtos($config)) {
            $partes[] = "Produtos e serviços (use APENAS estes preços; nunca invente, calcule ou estime valores):\n{$produtos}";
        }

        if ($faqs = $this->faqs($config)) {
            $partes[] = "Perguntas frequentes:\n{$faqs}";
        }

        if ($config->nunca_faca) {
            $partes[] = "Nunca faça:\n{$config->nunca_faca}";
        }

        if ($config->quando_humano) {
            $partes[] = "Passe para um humano quando:\n{$config->quando_humano}";
        }

        $partes[] = <<<'REGRA'
        Se a informação não estiver acima, não invente: marque precisa_humano.
        Responda SOMENTE com um objeto JSON válido, sem cercas de código, no formato:
        {"resposta": "texto para o cliente", "confianca": 0.0 a 1.0, "precisa_humano": true/false, "motivo": "texto ou null"}
        REGRA;

        return implode("\n\n", $partes);
    }

    /** @return \Illuminate\Support\Collection<int, Message> */
    private function historico(Conversation $conversa)
    {
        return $conversa->messages()
            ->where('created_at', '>=', now()->subHours(self::HISTORICO_MAX_HORAS))
            // Áudio entra pela transcrição: o conteúdo original vem vazio.
            ->where(fn ($q) => $q->whereNotNull('conteudo')->orWhereNotNull('transcricao'))
            ->latest('id')
            ->limit(self::HISTORICO_MAX_MENSAGENS)
            ->get()
            ->reverse()
            ->values();
    }

    private function tom(?string $tom): string
    {
        return match ($tom) {
            'formal' => 'formal e respeitoso, sem gírias',
            'informal' => 'informal e próximo, pode usar linguagem do dia a dia',
            default => 'neutro e cordial',
        };
    }

    private function horarios(BotConfig $config): string
    {
        $linhas = [];

        foreach ($config->horarios ?? [] as $dia => $faixa) {
            $linhas[] = is_array($faixa)
                ? sprintf('- %s: %s às %s', $dia, $faixa['abre'] ?? '?', $faixa['fecha'] ?? '?')
                : sprintf('- %s: %s', $dia, $faixa);
        }

        return implode("\n", $linhas);
    }

    private function produtos(BotConfig $config): string
    {
        $linhas = [];

        foreach ($config->produtosNormalizados() as $produto) {
            $linha = '- '.$produto['nome'];

            if ($produto['preco'] !== null) {
                $linha .= ': R$ '.number_format($produto['preco'], 2, ',', '.');
            }

            if ($produto['descricao']) {
                $linha .= ' — '.$produto['descricao'];
            }

            $linhas[] = $linha;
        }

        return implode("\n", $linhas);
    }

    private function faqs(BotConfig $config): string
    {
        $linhas = [];

        foreach ($config->faqs ?? [] as $faq) {
            $linhas[] = sprintf('- P: %s
  R: %s', $faq['pergunta'] ?? '', $faq['resposta'] ?? '');
        }

        return implode("\n", $linhas);
    }
}
