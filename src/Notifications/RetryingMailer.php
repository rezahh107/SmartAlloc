<?php

declare(strict_types=1);

namespace SmartAlloc\Notifications;

use SmartAlloc\Logging\Logger;

final class RetryingMailer implements MailerInterface
{
    /** @var callable(array):bool */
    private $mailFn;

    /** @var callable(int,string,array):bool */
    private $scheduleFn;

    private Logger $logger;

    private int $maxAttempts;

    private int $baseDelay;

    private static ?self $singleton = null;

    public function __construct(callable $mailFn, callable $scheduleFn, ?Logger $logger = null, int $maxAttempts = 3, int $baseDelay = 60)
    {
        $this->mailFn     = $mailFn;
        $this->scheduleFn = $scheduleFn;
        $this->logger     = $logger ?? new Logger();
        $this->maxAttempts = max(1, $maxAttempts);
        $this->baseDelay   = max(15, $baseDelay);
        self::$singleton   = $this;
    }

    public function register(): void
    {
        if (function_exists('add_action')) {
            add_action('smartalloc_mail_retry', [__CLASS__, 'retryAction'], 10, 1);
        }
    }

    /** @param array{payload:array,attempt:int} $args */
    public static function retryAction(array $args): void
    {
        if (!self::$singleton) {
            return;
        }
        $payload = $args['payload'] ?? [];
        $attempt = (int) ($args['attempt'] ?? 1);
        self::$singleton->send($payload, $attempt);
    }

    public function send(array $payload, int $attempt = 1): bool
    {
        $ok = (bool) call_user_func($this->mailFn, [
            'to'          => $payload['to'] ?? '',
            'subject'     => $payload['subject'] ?? '',
            'message'     => $payload['message'] ?? '',
            'headers'     => $payload['headers'] ?? [],
            'attachments' => $payload['attachments'] ?? [],
        ]);

        $context = ['attempt' => $attempt, 'corr' => $payload['corr'] ?? null];

        if ($ok) {
            $this->logger->info('mail ok', $context);
            return true;
        }

        if ($attempt >= $this->maxAttempts) {
            $this->logger->error('mail fail final', $context);
            return false;
        }

        $delay = (int) min(900, $this->baseDelay * (1 << ($attempt - 1)));
        $ts    = time() + $delay; // UTC
        $args  = [['payload' => $payload, 'attempt' => $attempt + 1]];
        $scheduled = (bool) call_user_func($this->scheduleFn, $ts, 'smartalloc_mail_retry', $args);
        $context['delay'] = $delay;
        $this->logger->info($scheduled ? 'mail scheduled' : 'schedule failed', $context);

        return $scheduled;
    }
}
