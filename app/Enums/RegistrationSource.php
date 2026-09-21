<?php

namespace App\Enums;

enum RegistrationSource: string
{
    case Web = 'web';
    case Api = 'api';
    case Oauth = 'oauth';
}
