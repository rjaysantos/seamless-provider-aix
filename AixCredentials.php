<?php

namespace Providers\Aix;

use Providers\Aix\Contracts\ICredentials;
use Providers\Aix\Credentials\Staging;

class AixCredentials
{
    public function getCredentialsByCurrency(string $currency): ICredentials
    {
        return new Staging;
    }
}
