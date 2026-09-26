<?php

require_once __DIR__ . '/CaptionGeneratorInterface.php';
require_once __DIR__ . '/AnthropicCaptionGenerator.php';
require_once __DIR__ . '/OllamaCaptionGenerator.php';
require_once __DIR__ . '/../AiCaptionConfig.php';

/*
|--------------------------------------------------------------------------
| Caption Generator Factory
|--------------------------------------------------------------------------
|
| The one place that knows which concrete CaptionGeneratorInterface
| implementation is active. SocialContentCaptionEngine only ever calls
| CaptionGeneratorFactory::make() -- it never references
| AnthropicCaptionGenerator/OllamaCaptionGenerator (or any future provider)
| by name, and its own call site is completely unchanged by this file.
|
| Provider selection now goes through includes/AiCaptionConfig.php's
| getAiCaptionConfig() (DB-backed AI Configuration page, falling back to
| MODLUS_AI_CAPTION_PROVIDER/ANTHROPIC_API_KEY/MODLUS_OLLAMA_HOST/
| MODLUS_OLLAMA_MODEL when nothing is configured in the database) instead
| of reading MODLUS_AI_CAPTION_PROVIDER directly -- this is the only
| change. Adding a second provider later still means writing a new class
| that implements CaptionGeneratorInterface and adding one case here --
| nothing in Caption Area changes.
|
| 'ollama' (local, no API key -- includes/AI/OllamaCaptionGenerator.php)
| was added alongside 'anthropic', not in place of it -- both remain
| selectable via this same config, and neither falls back to the other.
|
*/

class CaptionGeneratorFactory
{
    public static function make(): CaptionGeneratorInterface
    {
        $config = getAiCaptionConfig();

        switch ($config['provider']) {
            case 'anthropic':
                return new AnthropicCaptionGenerator($config['anthropicApiKey'], $config['anthropicModel']);
            case 'ollama':
                return new OllamaCaptionGenerator($config['ollamaHost'], $config['ollamaModel']);
            default:
                throw new Exception('Unknown AI caption provider configured: ' . $config['provider']);
        }
    }
}
