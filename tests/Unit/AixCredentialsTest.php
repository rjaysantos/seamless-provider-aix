<?php

use Providers\Aix\AixCredentials;
use Providers\Aix\Credentials\Staging;
use Tests\TestCase;

class AixCredentialsTest extends TestCase
{

    private function makeCredentials()
    {
        return new AixCredentials;
    }

    public function test_getCredentialsByCurrency_givenData_expected()
    {
        // add env setting here for staging and prod
        $currency = 'IDR';

        $expected = new Staging;

        $credentials = $this->makeCredentials();
        $result = $credentials->getCredentialsByCurrency($currency);

        $this->assertEquals($expected, $result);
    }
}
