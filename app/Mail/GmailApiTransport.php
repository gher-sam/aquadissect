<?php

namespace App\Mail;

use App\Services\GmailSender;
use RuntimeException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;

class GmailApiTransport extends AbstractTransport
{
    public function __construct(private GmailSender $sender)
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return 'gmail-api';
    }

    protected function doSend(SentMessage $message): void
    {
        $email = $message->getOriginalMessage();
        if (! $email instanceof Email || count($email->getFrom()) !== 1
            || strtolower($email->getFrom()[0]->getAddress()) !== strtolower((string) config('gmail-sender.email'))) {
            throw new RuntimeException('The sending address does not match the connected Google account.');
        }
        $this->sender->send($message->toString());
    }
}
