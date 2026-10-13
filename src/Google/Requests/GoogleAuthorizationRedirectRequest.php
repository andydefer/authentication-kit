<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Requests;

use AndyDefer\AuthenticationKit\Google\Enums\GoogleEndpoint;
use AndyDefer\PhpClient\Abstracts\Request;
use AndyDefer\PhpClient\Enums\ContentType;
use AndyDefer\PhpClient\Enums\HttpMethod;
use AndyDefer\PhpClient\Utils\EmptyStruct;
use AndyDefer\PhpClient\ValueObjects\RequestBodyVO;
use AndyDefer\PhpClient\ValueObjects\UrlQueryVO;
use AndyDefer\PhpClient\ValueObjects\UrlVO;

final class GoogleAuthorizationRedirectRequest extends Request
{
    private string $clientId = '';

    private string $redirectUri = '';

    private string $state = '';

    private string $codeChallenge = '';

    private string $nonce = '';

    private array $scopes = ['openid', 'email', 'profile'];

    public function setClientId(string $clientId): self
    {
        $this->clientId = $clientId;

        return $this;
    }

    public function setRedirectUri(string $redirectUri): self
    {
        $this->redirectUri = $redirectUri;

        return $this;
    }

    public function setState(string $state): self
    {
        $this->state = $state;

        return $this;
    }

    public function setCodeChallenge(string $codeChallenge): self
    {
        $this->codeChallenge = $codeChallenge;

        return $this;
    }

    public function setNonce(string $nonce): self
    {
        $this->nonce = $nonce;

        return $this;
    }

    /**
     * @param  array<int, string>  $scopes
     */
    public function setScopes(array $scopes): self
    {
        $this->scopes = $scopes;

        return $this;
    }

    protected function setMethod(): HttpMethod
    {
        return HttpMethod::GET;
    }

    protected function setUrl(): UrlVO
    {
        $query = (new UrlQueryVO)
            ->withParameter('client_id', $this->clientId)
            ->withParameter('redirect_uri', $this->redirectUri)
            ->withParameter('response_type', 'code')
            ->withParameter('scope', implode(' ', $this->scopes))
            ->withParameter('state', $this->state)
            ->withParameter('code_challenge', $this->codeChallenge)
            ->withParameter('code_challenge_method', 'S256')
            ->withParameter('nonce', $this->nonce);

        return GoogleEndpoint::AUTHORIZE->url()->withQuery($query);
    }

    protected function setBody(): RequestBodyVO
    {
        return new RequestBodyVO(new EmptyStruct, ContentType::JSON);
    }
}
