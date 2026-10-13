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
    private string $code = '';

    private string $clientId = '';

    private string $clientSecret = '';

    private string $redirectUri = '';

    private string $codeVerifier = '';

    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
    }

    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function setClientSecret(string $clientSecret): self
    {
        $this->clientSecret = $clientSecret;

        return $this;
    }

    public function setRedirectUri(string $redirectUri): self
    {
        $this->redirectUri = $redirectUri;

        return $this;
    }

    public function setCodeVerifier(string $codeVerifier): self
    {
        $this->codeVerifier = $codeVerifier;

        return $this;
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
