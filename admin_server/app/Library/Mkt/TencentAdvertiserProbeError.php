<?php
declare(strict_types=1);

namespace App\Library\Mkt;

use RuntimeException;

/**
 * Only fixed diagnostic reasons and bounded numeric codes.
 * Never carries original Tencent response, request URI or credential values.
 */
final class TencentAdvertiserProbeError extends RuntimeException
{
    public const NETWORK_FAILED = 'NETWORK_FAILED';
    public const HTTP_FAILED = 'HTTP_FAILED';
    public const API_REJECTED = 'API_REJECTED';
    public const RESPONSE_INVALID = 'RESPONSE_INVALID';
    public const ACCOUNT_NOT_RETURNED = 'ACCOUNT_NOT_RETURNED';

    private $httpStatus;
    private $apiCode;

    public function __construct(string $reason, ?int $httpStatus = null, ?int $apiCode = null)
    {
        parent::__construct($reason);
        $this->httpStatus = $httpStatus !== null && $httpStatus >= 100 && $httpStatus <= 599
            ? $httpStatus : null;
        $this->apiCode = $apiCode !== null && $apiCode >= 0 && $apiCode <= 99999999
            ? $apiCode : null;
    }

    public function httpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function apiCode(): ?int
    {
        return $this->apiCode;
    }
}
