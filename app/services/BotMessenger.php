<?php

/**
 * BotMessenger — the send surface a bot handler needs, so the same handler can
 * run over WhatsApp (WhatsAppCloudClient) or Telegram (TelegramClient).
 */
interface BotMessenger
{
    public function sendText(string $to, string $message, ?string $templateKey = null): bool;

    /** @param array<int,array{id:string,title:string,description?:string}> $rows */
    public function sendList(string $to, string $bodyText, string $buttonText, string $sectionTitle, array $rows, ?string $templateKey = null): bool;

    /** @param array<int,array{id:string,title:string}> $buttons */
    public function sendButtons(string $to, string $bodyText, array $buttons, ?string $templateKey = null): bool;

    public function markReadWithTyping(string $messageId): bool;
}
