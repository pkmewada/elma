<?php

require_once __DIR__ . '/CaptionGeneratorInterface.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Ollama Caption Generator
|--------------------------------------------------------------------------
|
| Concrete CaptionGeneratorInterface implementation backed by a local
| Ollama server (https://github.com/ollama/ollama) -- no API key, no
| external service. Every caller reaches this class only through
| CaptionGeneratorFactory, exactly like AnthropicCaptionGenerator; nothing
| outside includes/AI references it directly, and nothing in
| SocialContentCaptionEngine changes to support it.
|
| Endpoint/model: MODLUS_OLLAMA_HOST / MODLUS_OLLAMA_MODEL env vars,
| defaulting to http://localhost:11434 / llama3.2:3b (the values this
| phase was scoped to) -- same optional-override-with-default convention
| includes/db.php already uses for MODLUS_DB_HOST etc.
|
| Structured output: requests Ollama's JSON-schema "format" option
| (/api/chat, stream:false) so the model returns {"caption_1":...,
| "caption_2":...} directly. Falls back to the same "Option 1: ... Option
| 2: ..." text parsing AnthropicCaptionGenerator uses if the model doesn't
| comply with the schema (small local models don't always follow it
| reliably) -- no engine-level change needed either way.
|
| Deliberately no automatic fallback to Anthropic: if this provider is
| selected and Ollama is unavailable, generate() throws and the admin sees
| a clear error, per this phase's explicit scope.
|
*/

class OllamaCaptionGenerator implements CaptionGeneratorInterface
{
    private const DEFAULT_HOST = 'http://localhost:11434';
    private const DEFAULT_MODEL = 'llama3.2:3b';
    private const CONNECT_TIMEOUT = 5;
    private const REQUEST_TIMEOUT = 60;

    private string $host;
    private string $model;

    public function __construct(?string $host = null, ?string $model = null)
    {
        $envHost = trim((string) getenv('MODLUS_OLLAMA_HOST'));
        $envModel = trim((string) getenv('MODLUS_OLLAMA_MODEL'));

        $this->host = rtrim($host ?? ($envHost !== '' ? $envHost : self::DEFAULT_HOST), '/');
        $this->model = $model ?? ($envModel !== '' ? $envModel : self::DEFAULT_MODEL);
    }

    public function generate(array $context, string $adminPrompt): array
    {
        $adminPrompt = trim($adminPrompt);
        if ($adminPrompt === '') {
            throw new Exception('Enter a prompt before generating a caption.');
        }

        $client = new \GuzzleHttp\Client([
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::REQUEST_TIMEOUT,
        ]);

        $payload = [
            'model' => $this->model,
            'stream' => false,
            'messages' => [
                ['role' => 'system', 'content' => $this->systemPrompt()],
                ['role' => 'user', 'content' => $this->userPrompt($context, $adminPrompt)],
            ],
            'format' => [
                'type' => 'object',
                'properties' => [
                    'caption_1' => ['type' => 'string'],
                    'caption_2' => ['type' => 'string'],
                ],
                'required' => ['caption_1', 'caption_2'],
            ],
        ];

        try {
            $response = $client->post($this->host . '/api/chat', ['json' => $payload]);
        } catch (\GuzzleHttp\Exception\NetworkException $e) {
            // Covers ConnectException too (connection refused/DNS/connect
            // timeout) -- Ollama isn't running or isn't reachable at $host.
            // Guzzle 8's NetworkException never has a response to inspect.
            $this->logFailure('connection', $e->getMessage());
            throw new Exception('Could not reach the local AI caption service (Ollama). Make sure Ollama is running.');
        } catch (\GuzzleHttp\Exception\ResponseException $e) {
            // ClientException (4xx)/ServerException (5xx) -- both guarantee
            // a real response, unlike the base RequestException below.
            $status = $e->getResponse()->getStatusCode();
            $body = (string) $e->getResponse()->getBody();
            $this->logFailure('provider_error', 'HTTP ' . $status . ': ' . $body);

            if ($status === 404 || stripos($body, 'not found') !== false) {
                throw new Exception('The configured AI model (' . $this->model . ') is not available on this server. Pull it with Ollama and try again.');
            }

            throw new Exception('The AI caption service returned an error. Please try again.');
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            // No guaranteed response on the base class -- reached for a
            // read timeout (request sent, response never completed) or any
            // other pre-response transfer failure.
            $this->logFailure('timeout', $e->getMessage());
            throw new Exception('The AI caption service timed out. Please try again.');
        } catch (\Throwable $e) {
            $this->logFailure('sdk_error', $e->getMessage());
            throw new Exception('Caption generation failed. Please try again.');
        }

        $content = $this->extractContent($response);
        $options = $this->parseJsonOptions($content) ?? $this->parseTextOptions($content);

        if ($options === null) {
            // Deliberately does not log $content -- the caption content
            // itself is not logged, only that parsing failed.
            $this->logFailure('invalid_response', 'Response did not contain two parseable caption options.');
            throw new Exception('The AI did not return a usable caption. Please try again or adjust your prompt.');
        }

        return $options;
    }

    // Read-only accessors for the AI Configuration page.
    public function getHost(): string
    {
        return $this->host;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Lightweight health check: GET /api/tags (Ollama's own "list local
     * models" endpoint) rather than a real /api/chat generation -- confirms
     * both that the server is reachable and whether the configured model
     * is actually pulled, at no generation cost.
     */
    public function testConnection(): array
    {
        $client = new \GuzzleHttp\Client([
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'timeout' => self::CONNECT_TIMEOUT,
        ]);

        try {
            $response = $client->get($this->host . '/api/tags');
        } catch (\GuzzleHttp\Exception\NetworkException $e) {
            $this->logFailure('connection', $e->getMessage());
            return ['success' => false, 'message' => 'Could not reach Ollama at ' . $this->host . '. Make sure it is running.'];
        } catch (\Throwable $e) {
            $this->logFailure('unexpected', $e->getMessage());
            return ['success' => false, 'message' => 'Could not reach Ollama at ' . $this->host . '.'];
        }

        $decoded = json_decode((string) $response->getBody(), true);
        $models = is_array($decoded['models'] ?? null) ? $decoded['models'] : [];
        $installedNames = array_map(static fn($m) => (string) ($m['name'] ?? ''), $models);

        if (in_array($this->model, $installedNames, true)) {
            return ['success' => true, 'message' => 'Connected to Ollama at ' . $this->host . ' (model "' . $this->model . '" is installed).'];
        }

        return [
            'success' => false,
            'message' => 'Ollama is reachable at ' . $this->host . ', but model "' . $this->model . '" is not installed. Run: ollama pull ' . $this->model,
        ];
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You are a social media caption writer for a marketing agency. You are given
structured details about one piece of approved content and an admin's own
instruction for the caption.

Write exactly two distinct caption options for this content. Match the
platform and posting type, follow the admin's instruction closely, and keep
each caption realistic for actual use (no meta-commentary, no explanations,
no markdown formatting, no quotation marks around the caption text).

Respond with ONLY a JSON object in exactly this shape and nothing else:
{"caption_1": "<caption text>", "caption_2": "<caption text>"}
PROMPT;
    }

    // Structured, sectioned context (Brand / Content / Social / Admin
    // Prompt) instead of one flat "Content Context" block -- same
    // $context keys SocialContentCaptionEngine::buildAiContext() already
    // builds, just organized so the AI can weigh brand identity, the
    // actual content, and the platform/format separately. Any field still
    // missing/empty (e.g. no business category on record) is simply
    // omitted, exactly as before.
    private function userPrompt(array $context, string $adminPrompt): string
    {
        $sections = [
            $this->promptSection('Brand Information', [
                'Brand Name' => $context['clientName'] ?? '',
                'Business Category' => $context['businessCategory'] ?? '',
            ]),
            $this->promptSection('Content Information', [
                'Title' => $context['contentTitle'] ?? '',
                'Description' => $context['contentDescription'] ?? '',
                'Raw Content' => $context['rawContent'] ?? '',
                'Notes' => $context['contentNote'] ?? '',
                'Reference' => $context['reference'] ?? '',
            ]),
            $this->promptSection('Social Context', [
                'Platform' => $context['platform'] ?? '',
                'Posting Type' => $context['postingType'] ?? '',
                'Content Format' => $context['contentFormat'] ?? '',
                'Content Purpose' => $context['contentPurpose'] ?? '',
            ]),
        ];

        $lines = array_values(array_filter($sections, static fn($s) => $s !== ''));
        $lines[] = "Admin Prompt:\n- " . $adminPrompt;

        return implode("\n\n", $lines);
    }

    // Renders one labeled section, or '' if every field in it is blank
    // (never emits an empty heading).
    private function promptSection(string $heading, array $fields): string
    {
        $lines = [];
        foreach ($fields as $label => $value) {
            $value = trim((string) $value);
            if ($value !== '') {
                $lines[] = "- $label: $value";
            }
        }

        return $lines ? "$heading:\n" . implode("\n", $lines) : '';
    }

    private function extractContent($response): string
    {
        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        return trim((string) ($decoded['message']['content'] ?? ''));
    }

    // Expects {"caption_1": "...", "caption_2": "..."}, optionally wrapped
    // in a ```json fence (small local models sometimes add one even when
    // asked not to). Returns null rather than guessing when the shape
    // doesn't match -- parseTextOptions() gets a chance next.
    private function parseJsonOptions(string $content): ?array
    {
        $stripped = trim(preg_replace('/^```(?:json)?\s*|\s*```$/i', '', trim($content)));
        $decoded = json_decode($stripped, true);

        if (!is_array($decoded)) {
            return null;
        }

        $optionOne = trim((string) ($decoded['caption_1'] ?? ''));
        $optionTwo = trim((string) ($decoded['caption_2'] ?? ''));
        if ($optionOne === '' || $optionTwo === '') {
            return null;
        }

        return ['optionOne' => $optionOne, 'optionTwo' => $optionTwo];
    }

    // Same fallback shape AnthropicCaptionGenerator::parseOptions() uses --
    // "Option 1: ... Option 2: ..." (case-insensitive, whitespace-tolerant).
    private function parseTextOptions(string $content): ?array
    {
        if (!preg_match('/option\s*1\s*:\s*(.+?)\s*option\s*2\s*:\s*(.+)/is', $content, $matches)) {
            return null;
        }

        $optionOne = trim($matches[1]);
        $optionTwo = trim($matches[2]);
        if ($optionOne === '' || $optionTwo === '') {
            return null;
        }

        return ['optionOne' => $optionOne, 'optionTwo' => $optionTwo];
    }

    // Minimal logging only -- which failure category and the provider's
    // own error message (never the generated caption content, never the
    // admin prompt).
    private function logFailure(string $category, string $detail): void
    {
        error_log('[CaptionArea][AI][ollama][' . $category . '] ' . $detail);
    }
}
