<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaService
{
    private string $host;
    private string $model;

    private const SYSTEM = <<<'TXT'
你是 CODE://MASTER，一位耐心的資深程式導師。請全程使用繁體中文（台灣用語）。
任務：1) 診斷程式碼錯誤；2) 把修正拆成循序漸進的小步驟，讓初學者能跟著做；3) 解釋錯誤背後的語法與觀念。
規則：
- errors.line 使用使用者提供的行號；找不到明確行號時填 0。
- steps 每一步只做一個小改動；before / after 只放受影響的程式碼片段，不要帶行號。
- fixed_code 必須是完整、可直接執行的修正後程式碼，不含行號。
- concepts 列出 1 到 3 個相關語法或觀念，example 提供最短範例。
- 若沒有錯誤，has_error 設為 false，並在 summary 說明可改進之處。
- 不確定的事不要編造。只輸出符合格式的 JSON。
- 先在 analysis 欄位逐行檢查全部程式碼，再填其他欄位。
- 檢查清單：語法錯誤、未定義或拼錯的名稱、型別錯誤、null 與空陣列、索引越界、非同步與回傳值遺漏、邏輯錯誤、安全問題。
- errors 必須列出所有問題，不可只列第一個；沒有把握的項目標題加上「可能」。
TXT;

    public function __construct()
    {
        $this->host = rtrim(config('codemaster.ollama_host'), '/');
        $this->model = config('codemaster.ollama_model');
    }

    public function status(): array
    {
        try {
            $res = Http::timeout(3)->get("{$this->host}/api/tags");
            $names = collect($res->json('models', []))->pluck('name')->all();

            return [
                'online' => $res->ok(),
                'model' => $this->model,
                'model_ready' => in_array($this->model, $names) || in_array($this->model . ':latest', $names),
            ];
        } catch (\Throwable $e) {
            return ['online' => false, 'model' => $this->model, 'model_ready' => false];
        }
    }

    public function diagnose(string $language, string $code, ?string $error): array
    {
        $numbered = collect(preg_split('/\R/', $code))
            ->map(fn ($line, $i) => ($i + 1) . '| ' . $line)
            ->implode("\n");
        $user = "程式語言：{$language}\n"
            . ($error ? "錯誤訊息：{$error}\n" : '')
            . "程式碼（左側為行號，不屬於程式碼）：\n{$numbered}";

        try {
            $res = Http::connectTimeout(5)
                ->timeout(config('codemaster.ollama_timeout'))
                ->post("{$this->host}/api/chat", [
                    'model' => $this->model,
                    'stream' => false,
                    'keep_alive' => '10m',
                    'format' => $this->schema(),
                    'options' => ['temperature' => 0, 'seed' => 42, 'num_ctx' => config('codemaster.ollama_num_ctx')],
                    'messages' => [
                        ['role' => 'system', 'content' => self::SYSTEM],
                        ['role' => 'user', 'content' => $user],
                    ],
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException('連不到 Ollama（未啟動或逾時）。請確認 ollama serve 正在執行。');
        }

        if ($res->failed()) {
            throw new RuntimeException('Ollama 回報錯誤：' . ($res->json('error') ?? $res->status()));
        }

        $data = json_decode($res->json('message.content', ''), true);
        if (!is_array($data)) {
            throw new RuntimeException('模型回傳的格式無法解析，請再試一次。');
        }

        return $data + ['summary' => '', 'has_error' => true, 'errors' => [], 'steps' => [], 'fixed_code' => '', 'concepts' => []];
    }

    private function obj(array $props): array
    {
        return [
            'type' => 'object',
            'properties' => array_map(fn ($t) => ['type' => $t], $props),
            'required' => array_keys($props),
        ];
    }

    private function schema(): array
    {
        $list = fn (array $p) => ['type' => 'array', 'items' => $this->obj($p)];

        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string'],
                'has_error' => ['type' => 'boolean'],
                'errors' => $list(['line' => 'integer', 'title' => 'string', 'cause' => 'string']),
                'steps' => $list(['title' => 'string', 'explanation' => 'string', 'before' => 'string', 'after' => 'string']),
                'fixed_code' => ['type' => 'string'],
                'concepts' => $list(['name' => 'string', 'explanation' => 'string', 'example' => 'string']),
            ],
            'required' => ['summary', 'has_error', 'errors', 'steps', 'fixed_code', 'concepts'],
        ];
    }
}
