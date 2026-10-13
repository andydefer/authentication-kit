<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Requests;

use AndyDefer\PhpClient\Abstracts\Request;
use AndyDefer\PhpClient\Enums\ContentType;
use AndyDefer\PhpClient\Enums\HttpMethod;
use AndyDefer\PhpClient\Utils\EmptyStruct;
use AndyDefer\PhpClient\ValueObjects\RequestBodyVO;
use AndyDefer\PhpClient\ValueObjects\UrlVO;

final class EmptyRequest extends Request
{
    public function __construct(
        private readonly string $targetUrl,
    ) {
        parent::__construct();
    }

    protected function setMethod(): HttpMethod
    {
        return HttpMethod::GET;
    }

    protected function setUrl(): UrlVO
    {
        return new UrlVO($this->targetUrl);
    }

    protected function setBody(): RequestBodyVO
    {
        return new RequestBodyVO(new EmptyStruct, ContentType::JSON);
    }
}
