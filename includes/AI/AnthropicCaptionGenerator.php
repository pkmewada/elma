<?php

require_once __DIR__ . '/CaptionGeneratorInterface.php';
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

use Anthropic\Client;
use Anthropic\RequestOptions;
use Anthropic\Core\Exceptions\APITimeoutException;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\RateLimitException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\AnthropicException;

/*
|--------------------------------------------------------------------------
| Anthropic Caption Generator
|--------------------------------------------------------------------------
|
| Concrete CaptionGeneratorInterface implementation backed by the official
| Anthropic PHP SDK (anthropic-ai/sdk, added via Composer -- this project
| already uses Composer for phpmailer/dompdf). Every caller reaches this
| class only through CaptionGeneratorFactory -- nothing outside includes/AI
| references it directly.
|
| Credentials/model: passed in by CaptionGeneratorFactory, which resolves
| them via includes/AiCaptionConfig.php -- the AI Configuration page's
| encrypted-at-rest database row if one exists, else the ANTHROPIC_API_KEY
| environment variable (same getenv() pattern includes/config.php already
| uses for MODLUS_BASE_URL) and the DEFAULT_MODEL below. This class itself
| never touches the database or getenv('ANTHROPIC_API_KEY') beyond that
| constructor fallback (kept for direct/manual instantiation outside the
| factory) -- never hardcoded, and the plaintext key is never persisted
| anywhere by this class.
|
| Default model: claude-opus-5 at a low effort level -- caption writing is
| a short, low-complexity creative task, not a reasoning-heavy one, so a
| low effort budget keeps latency/cost down without changing model tier.
|
*/

class AnthropicCaptionGenerator implements CaptionGeneratorInterface
{
    private const DEFAULT_MODEL = 'claude-opus-5';
    private const MAX_TOKENS = 1024;

    private string $apiKey;
    private string $model;

    // $model is configurable (AI Configuration page) -- unlike $apiKey,
    // there is no dedicated env var for it; falling back to
    // self::DEFAULT_MODEL when neither the caller nor the database
    // supplies one is the only fallback tier, matching the scope this
    // was built to (no new env var invented).
    public function __construct(?string $apiKey = null, ?string $model = null)
    {
        $this->apiKey = $apiKey ?? (string) getenv('ANTHROPIC_API_KEY');
        $this->model = $model !== null && trim($model) !== '' ? trim($model) : self::DEFAULT_MODEL;
    }

    public function generate(array $context, string $adminPrompt): array
    {
        if (trim($this->apiKey) === '') {
            throw new Exception('AI caption generation is not configured. Set the API key in AI Configuration (or the ANTHROPIC_API_KEY environment variable).');
        }

        $adminPrompt = trim($adminPrompt);
        if ($adminPrompt === '') {
            throw new Exception('Enter a prompt before generating a caption.');
        }

        $client = $this->buildClient();

        try {
            $message = $client->messages->create(
                model: $this->model,
                maxTokens: self::MAX_TOKENS,
                system: $this->systemPrompt(),
                messages: [['role' => 'user', 'content' => $this->userPrompt($context, $adminPrompt)]],
                outputConfig: ['effort' => 'low'],
            );
        } catch (APITimeoutException $e) {
            $this->logFailure('timeout', $e->getMessage());
            throw new Exception('The AI caption service timed out. Please try again.');
        } catch (APIConnectionException $e) {
            $this->logFailure('connection', $e->getMessage());
            throw new Exception('Could not reach the AI caption service. Please try again.');
        } catch (RateLimitException $e) {
            $this->logFailure('rate_limit', $e->getMessage());
            throw new Exception('The AI caption service is busy right now. Please try again in a moment.');
        } catch (APIStatusException $e) {
            // Covers auth/bad-request/server-side provider errors alike --
            // the admin never needs to know which; ->type is what goes to
            // the log, not the user-facing message.
            $this->logFailure('provider_error', $e->getMessage());
            throw new Exception('The AI caption service returned an error. Please try again.');
        } catch (AnthropicException $e) {
            $this->logFailure('sdk_error', $e->getMessage());
            throw new Exception('Caption generation failed. Please try again.');
        }

        $text = $this->extractText($message);
        $options = $this->parseOptions($text);

        if ($options === null) {
            // Deliberately does not log $text -- the caption content
            // itself is not logged, only that parsing failed.
            $this->logFailure('invalid_response', 'Response did not contain two parseable caption options.');
            throw new Exception('The AI did not return a usable caption. Please try again or adjust your prompt.');
        }

        return $options;
    }

    // Read-only accessors for the AI Configuration page -- never expose
    // $this->apiKey itself, only whether it's set.
    public function getModel(): string
    {
        return $this->model;
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /**
     * Lightweight health check: lists at most one model, which validates
     * the API key/reachability without spending any generation tokens and
     * without touching the caption prompt/parsing path at all.
     */
    public function testConnection(): array
    {
        if (trim($this->apiKey) === '') {
            return ['success' => false, 'message' => 'ANTHROPIC_API_KEY is not configured.'];
        }

        try {
            $this->buildClient()->models->list(limit: 1);
        } catch (APITimeoutException $e) {
            $this->logFailure('timeout', $e->getMessage());
            return ['success' => false, 'message' => 'The Anthropic API timed out.'];
        } catch (APIConnectionException $e) {
            $this->logFailure('connection', $e->getMessage());
            return ['success' => false, 'message' => 'Could not reach the Anthropic API.'];
        } catch (RateLimitException $e) {
            $this->logFailure('rate_limit', $e->getMessage());
            return ['success' => false, 'message' => 'The Anthropic API is rate-limiting this key right now, but it is valid.'];
        } catch (APIStatusException $e) {
            // Covers an invalid/revoked key (401) alike every other
            // provider-side error -- the admin never needs the raw status,
            // just that the key/connection isn't working.
            $this->logFailure('provider_error', $e->getMessage());
            return ['success' => false, 'message' => 'The Anthropic API rejected the request (check the API key).'];
        } catch (AnthropicException $e) {
            $this->logFailure('sdk_error', $e->getMessage());
            return ['success' => false, 'message' => 'Could not verify the Anthropic connection.'];
        } catch (\Throwable $e) {
            $this->logFailure('unexpected', $e->getMessage());
            return ['success' => false, 'message' => 'Could not verify the Anthropic connection.'];
        }

        return ['success' => true, 'message' => 'Connected to Anthropic successfully (model: ' . $this->model . ').'];
    }

    private function buildClient(): Client
    {
        // Guzzle with an explicit CA bundle, not the SDK's auto-discovered
        // default HTTP client as-is -- this environment has no
        // curl.cainfo configured, so an unconfigured client fails TLS
        // verification on every outbound HTTPS call. Safe to keep on a
        // properly configured server too (it just points at a valid
        // bundle); scoped to this one client instance, nothing global
        // (php.ini, other integrations) is touched.
        $caBundle = class_exists(\Composer\CaBundle\CaBundle::class)
            ? \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath()
            : null;
        $guzzleOptions = ['timeout' => 30];
        if ($caBundle) {
            $guzzleOptions['verify'] = $caBundle;
        }
        $guzzle = new \GuzzleHttp\Client($guzzleOptions);

        return new Client(
            apiKey: $this->apiKey,
            requestOptions: RequestOptions::with(transporter: $guzzle, streamingTransporter: $guzzle),
        );
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

Respond in exactly this format and nothing else:

Option 1:
<caption text>

Option 2:
<caption text>
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

    private function extractText($message): string
    {
        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return trim($text);
    }

    // Expects "Option 1: ... Option 2: ..." (case-insensitive, whitespace-
    // tolerant). Returns null rather than guessing when the shape doesn't
    // match -- the caller turns that into a clean "try again" error
    // instead of saving a malformed option.
    private function parseOptions(string $text): ?array
    {
        if (!preg_match('/option\s*1\s*:\s*(.+?)\s*option\s*2\s*:\s*(.+)/is', $text, $matches)) {
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
    // admin prompt, never the API key).
    private function logFailure(string $category, string $detail): void
    {
        error_log('[CaptionArea][AI][' . $category . '] ' . $detail);
    }
}
