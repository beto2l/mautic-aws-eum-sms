<?php

declare(strict_types=1);

namespace MauticPlugin\AwsEndUserMessagingSmsBundle\Security;

final class AwsRequestException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly ?string $awsCode = null,
        private readonly ?string $awsType = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getAwsCode(): ?string
    {
        return $this->awsCode;
    }

    public function getAwsType(): ?string
    {
        return $this->awsType;
    }
}
