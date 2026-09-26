<?php

/*
|--------------------------------------------------------------------------
| Caption Generator Interface
|--------------------------------------------------------------------------
|
| The contract SocialContentCaptionEngine calls -- it never talks to a
| concrete AI provider directly. Swapping providers (or adding a second
| one) means writing a new class that implements this interface and
| pointing includes/AI/CaptionGeneratorFactory.php at it; nothing in
| Caption Area itself changes.
|
*/

interface CaptionGeneratorInterface
{
    /**
     * @param array $context Structured content context -- clientName,
     *   platform, postingType, contentFormat, contentTitle, rawContent,
     *   contentDescription, contentNote. Any key may be missing/empty;
     *   implementations must handle that, never assume all are present.
     * @param string $adminPrompt The admin's own instruction text
     *   (e.g. "Create an engaging Instagram caption with CTA").
     * @return array{optionOne: string, optionTwo: string} Exactly two
     *   non-empty caption strings.
     * @throws Exception on any failure -- provider error, timeout,
     *   invalid/empty response. The message must be safe to show directly
     *   to the admin (no raw provider payloads, no stack traces).
     */
    public function generate(array $context, string $adminPrompt): array;

    /**
     * Health check for the AI Configuration page's "Test Connection"
     * button -- a lightweight, low/no-cost call (never a real caption
     * generation) that verifies credentials/reachability for the
     * currently configured provider. Unlike generate(), this never
     * throws: every failure (not configured, unreachable, auth error) is
     * reported through the return value so the page can always show a
     * clear result instead of a raw exception.
     *
     * @return array{success: bool, message: string} $message is always
     *   safe to show directly to the admin (no raw provider payloads, no
     *   secrets, no stack traces).
     */
    public function testConnection(): array;
}
