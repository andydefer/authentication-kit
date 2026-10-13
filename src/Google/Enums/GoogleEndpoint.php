<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Enums;

use AndyDefer\PhpClient\ValueObjects\UrlVO;

enum GoogleEndpoint: string
{
    case AUTHORIZE = 'https://accounts.google.com/o/oauth2/v2/auth';
    case TOKEN = 'https://oauth2.googleapis.com/token';

    public function url(): UrlVO
    {
        return new UrlVO($this->value);
    }
}
