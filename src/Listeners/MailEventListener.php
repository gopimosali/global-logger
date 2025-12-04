<?php

namespace Gopimosali\GlobalLogger\Listeners;

use Gopimosali\GlobalLogger\GlobalLogger;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Mail\Events\MessageSent;

/**
 * Mail Event Listener
 *
 * Logs all mail events automatically
 */
class MailEventListener
{
    public function __construct(
        protected GlobalLogger $logger
    ) {}

    /**
     * Handle message sending event
     */
    public function handleMessageSending(MessageSending $event): void
    {
        if (!config('globallogger.features.mail.enabled', true)) {
            return;
        }

        if (!config('globallogger.features.mail.log_sending', false)) {
            return;
        }

        $message = $event->message;

        $this->logger->info('Email sending', [
            'log_type' => 'mail',
            'mail_status' => 'sending',
            'mail_subject' => $message->getSubject(),
            'mail_to' => $this->formatAddresses($message->getTo()),
            'mail_from' => $this->formatAddresses($message->getFrom()),
            'mail_cc' => $this->formatAddresses($message->getCc()),
            'mail_bcc' => $this->formatAddresses($message->getBcc()),
        ]);
    }

    /**
     * Handle message sent event
     */
    public function handleMessageSent(MessageSent $event): void
    {
        if (!config('globallogger.features.mail.enabled', true)) {
            return;
        }

        $message = $event->message;

        $this->logger->info('Email sent successfully', [
            'log_type' => 'mail',
            'mail_status' => 'sent',
            'mail_subject' => $message->getSubject(),
            'mail_to' => $this->formatAddresses($message->getTo()),
            'mail_from' => $this->formatAddresses($message->getFrom()),
            'mail_cc' => $this->formatAddresses($message->getCc()),
            'mail_bcc' => $this->formatAddresses($message->getBcc()),
            'mail_id' => $message->getId(),
        ]);
    }

    /**
     * Format email addresses for logging
     */
    protected function formatAddresses(?array $addresses): ?array
    {
        if (empty($addresses)) {
            return null;
        }

        return array_map(function ($address) {
            return $address->getAddress();
        }, $addresses);
    }

    /**
     * Register the listeners for the subscriber
     */
    public function subscribe($events): array
    {
        return [
            MessageSending::class => 'handleMessageSending',
            MessageSent::class => 'handleMessageSent',
        ];
    }
}
