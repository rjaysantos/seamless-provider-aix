<?php

namespace Providers\Aix;

use Providers\Aix\Contracts\ICredentials;
use Providers\Aix\Credentials\Staging;

class AixCredentials
{
    public function getCredentialsByCurrency($currency): ICredentials
    {
        return new Staging;
    }
}