<?php

namespace App\Modules\Assistant\Gateway;

use RuntimeException;

/**
 * Provider failure with a stable error code stored on the turn. The message
 * is for logs only and never contains the provider body or credentials.
 */
final class GatewayException extends RuntimeException
{
    public const UNAVAILABLE = 'ai_unavailable';

    public const TIMEOUT = 'ai_timeout';

    public const RATE_LIMITED = 'ai_rate_limited';

    public const BAD_REQUEST = 'ai_bad_request';

    public const BAD_RESPONSE = 'ai_bad_response';

    public function __construct(public readonly string $errorCode, string $message = '')
    {
        parent::__construct($message !== '' ? $message : $errorCode);
    }
}
