<?php

/**
 * DeepSeekClient — multi-tenant AI support replies.
 * Ported from hiddenxcel/kuzapanel-bot; the API key is the tenant's own key
 * (from tenant_ai), and the system prompt is branded with the tenant's name.
 */
class DeepSeekClient
{
    private const API_URL = 'https://api.deepseek.com/chat/completions';

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a customer-support assistant for {BUSINESS}, a social media marketing (SMM) reseller offering services such as followers, likes and views for Instagram, TikTok, Facebook and YouTube.

Your replies:
- {LANGUAGE_INSTRUCTION}
- Do not quote specific prices — they change often. Ask the customer to place an order to see current services and pricing.
- Do not invent account-specific data (balances, someone's order history) that you don't have. If asked, tell them to check via their order status.
- If a question is outside your scope or the customer clearly needs a human, say so and offer to connect them to a human agent.
- Never make up facts. When unsure, escalate to a human rather than guessing.
- Be concise, friendly and helpful.
PROMPT;

    private const LANGUAGE_INSTRUCTIONS = [
        'en' => 'Reply in English only, briefly and in a friendly tone.',
        'fr' => 'Répondez uniquement en français, brièvement et avec un ton amical.',
        'sw' => 'Jibu kwa Kiswahili pekee, kwa ufupi na kirafiki.',
    ];

    private string $apiKey;
    private string $business;

    public function __construct(string $apiKey, string $business = 'our store')
    {
        $this->apiKey = $apiKey;
        $this->business = $business;
    }

    /**
     * @param array<int, array{role:string,content:string}> $history oldest first
     * @return array{success:bool, reply?:string, message?:string}
     */
    public function reply(array $history, string $userMessage, string $lang = 'en'): array
    {
        if ($this->apiKey === '') {
            return ['success' => false, 'message' => 'No AI key configured'];
        }

        $languageInstruction = self::LANGUAGE_INSTRUCTIONS[$lang] ?? self::LANGUAGE_INSTRUCTIONS['en'];
        $systemPrompt = str_replace(
            ['{LANGUAGE_INSTRUCTION}', '{BUSINESS}'],
            [$languageInstruction, $this->business],
            self::SYSTEM_PROMPT
        );

        $messages = array_merge(
            [['role' => 'system', 'content' => $systemPrompt]],
            $history,
            [['role' => 'user', 'content' => $userMessage]]
        );

        $payload = json_encode([
            'model' => 'deepseek-chat',
            'messages' => $messages,
            'max_tokens' => 400,
            'temperature' => 0.4,
        ]);

        $ch = curl_init(self::API_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $responseRaw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($err) {
            error_log("[DeepSeekClient] cURL error: {$err}");

            return ['success' => false, 'message' => 'cURL error'];
        }

        $result = json_decode($responseRaw, true);

        if ($httpCode !== 200 || !isset($result['choices'][0]['message']['content'])) {
            error_log("[DeepSeekClient] Unexpected response ({$httpCode}): {$responseRaw}");

            return ['success' => false, 'message' => 'Unexpected DeepSeek response'];
        }

        return ['success' => true, 'reply' => trim($result['choices'][0]['message']['content'])];
    }
}
