<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Requests;

use AndyDefer\AuthenticationKit\Google\Enums\GoogleEndpoint;
use AndyDefer\AuthenticationKit\Google\Structs\GoogleTokenExchangeBodyStruct;
use AndyDefer\PhpClient\Abstracts\Request;
use AndyDefer\PhpClient\Enums\ContentType;
use AndyDefer\PhpClient\Enums\HttpMethod;
use AndyDefer\PhpClient\ValueObjects\RequestBodyVO;
use AndyDefer\PhpClient\ValueObjects\UrlVO;

final class GoogleTokenExchangeRequest extends Request
{
    public function __construct(
        private readonly string $code,
        private readonly string $clientId,
        private readonly string $clientSecret,
        private readonly string $redirectUri,
        private readonly string $codeVerifier,
    ) {
        parent::__construct();
    }

    protected function setMethod(): HttpMethod
    {
        return HttpMethod::POST;
    }

    protected function setUrl(): UrlVO
    {
        return GoogleEndpoint::TOKEN->url();
    }

    protected function setBody(): RequestBodyVO
    {
        $struct = new GoogleTokenExchangeBodyStruct(
            code: $this->code,
            client_id: $this->clientId,
            client_secret: $this->clientSecret,
            redirect_uri: $this->redirectUri,
            grant_type: 'authorization_code',
            code_verifier: $this->codeVerifier,
        );

        return new RequestBodyVO($struct, ContentType::FORM);
    }
}
