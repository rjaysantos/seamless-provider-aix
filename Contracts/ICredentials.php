<?php

namespace Providers\Aix\Contracts;

use App\Contracts\V2\IWalletCredentials;

interface ICredentials extends IWalletCredentials
{
    public function getApiUrl(): string;
    public function getAgCode(): string;
    public function getAgToken(): string;
    public function getSecretKey(): string;
}
