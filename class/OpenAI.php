<?php

use GuzzleHttp\Client;

/**
 * Клас для роботи з OpenAI API (ChatGPT).
 */
class OpenAI
{
    private string $apiKey;
    private string $model;
    private Client $client;

    public function __construct(string $model = 'gpt-4o-mini')
    {
        $this->apiKey = defined('OPENAI_API_KEY') ? OPENAI_API_KEY : '';
        $this->model  = $model;
        $this->client = new Client(['timeout' => 30.0]);
    }

    /**
     * Генерує короткий і повний опис товару для сайту.
     *
     * @param  string $productName Назва товару
     * @return array{short: string, full: string}
     */
    public function generateProductDescription(string $productName, string $rawDescription = ''): array
    {
        if (empty($this->apiKey) || empty($productName)) {
            return ['short' => '', 'full' => ''];
        }

        $system = 'Ти професійний копірайтер інтернет-магазину одягу. Твоє завдання — писати стильні, живі та зрозумілі описи українською мовою на основі назви та технічних характеристик. Відповідай виключно у форматі JSON.';

        $userMessage = <<<MSG
Оброби дані товару та сформуй JSON з двома полями: "short" та "full".

Назва товару: {$productName}
Технічний опис (заміри, склад, тканина): 
{$rawDescription}

Вимоги до поля "full" (Повний опис):
1. Обов'язково почни з назви товару: <p><strong>{$productName}</strong></p>
2. Напиши рівно 3 речення привабливого маркетингового опису. Кожен абзац тексту обов'язково обгортай у теги <p>...</p>.
3. Після опису обов'язково додай блок "Матеріал та Догляд:", використовуючи дані зі складу та тканини.
Формат має бути рівно такий (склад у тезі <p>, а правила догляду у нумерованому списку <ol> з елементами <li> без вказання цифр всередині тегу):
<p><strong>Матеріал та Догляд:</strong> 60% поліестер, 30% віскоза, 10% еластан</p>
<ol>
<li>Прати вручну або на делікатному режимі при температурі до 30°C.</li>
<li>Використовувати м’який засіб без відбілювачів.</li>
<li>Не викручувати та не віджимати на високих обертах.</li>
<li>Сушити у розкладеному вигляді на горизонтальній поверхні.</li>
<li>Прасувати при низькій температурі або використовувати делікатне відпарювання.</li>
</ol>
(Адаптуй склад згідно технічного опису, а правила догляду підбирай універсально або згідно тканини).

Вимоги до поля "short" (Короткий опис):
1. Напиши заголовок саме так: <p><strong>Заміри виробу:</strong></p>
2. Гарно і чітко відформатуй заміри для кожного розміру. Увесь блок із замірами обов'язково обгортай у тег <p>...</p>, використовуючи <br> для перенесення рядків. 
Приклад формату:
<p><strong>Заміри виробу:</strong></p>
<p>XS/S<br>
Півобхват грудей: 58 см<br>
Півобхват талії: 58 см<br>
Довжина рукава: 46 см<br>
...
</p>
MSG;

        $result = $this->chat(
            $system,
            $userMessage,
            1500,
            0.7,
            true
        );

        if (!is_array($result)) {
            return ['short' => '', 'full' => ''];
        }

        return [
            'short' => trim($result['short'] ?? ''),
            'full'  => trim($result['full']  ?? ''),
        ];
    }

    /**
     * Універсальний метод для запиту до Chat Completions API.
     * Повертає розпарсений масив з відповіді або null при помилці.
     *
     * @param  string $system      System prompt
     * @param  string $user        User prompt
     * @param  int    $maxTokens   Максимум токенів у відповіді
     * @param  float  $temperature Температура (0.0 – 2.0)
     * @param  bool   $jsonMode    Примусовий JSON mode
     * @return array|null
     */
    public function chat(
        string $system,
        string $user,
        int    $maxTokens = 500,
        float  $temperature = 0.7,
        bool   $jsonMode = true
    ) {
        if (empty($this->apiKey)) {
            error_log('[OpenAI] API ключ не налаштовано.');
            return null;
        }

        try {
            $payload = [
                'model'       => $this->model,
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
                'max_tokens'  => $maxTokens,
                'temperature' => $temperature,
            ];

            if ($jsonMode) {
                $payload['response_format'] = ['type' => 'json_object'];
            }

            $response = $this->client->request('POST', 'https://api.openai.com/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ],
                'json' => $payload,
            ]);

            $body    = json_decode((string)$response->getBody(), true);
            $content = $body['choices'][0]['message']['content'] ?? '{}';

            if (!$jsonMode) {
                return $content; // повертаємо чистий текст
            }

            return json_decode($content, true);

        } catch (\Exception $e) {
            echo "\nAPI ERROR: " . $e->getMessage() . "\n";
            error_log('[OpenAI] Помилка запиту: ' . $e->getMessage());
            return null;
        }
    }
}
