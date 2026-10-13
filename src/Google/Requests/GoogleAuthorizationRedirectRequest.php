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
    /**
     * @param  array<int, string>  $scopes
     */
    public function __construct(
        private readonly string $clientId,
        private readonly string $redirectUri,
        private readonly string $state,
        private readonly string $codeChallenge,
        private readonly string $nonce,
        private readonly array $scopes = ['openid', 'email', 'profile'],
    ) {
        parent::__construct();
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
