<?php

namespace Providers\Aix\Credentials;

use Providers\Aix\Contracts\ICredentials;

class Staging implements ICredentials
{
    public function getGrpcHost(): string
    {
        return '12.0.129.253';
    }
    public function getGrpcPort(): string
    {
        return '3939';
    }
    public function getGrpcToken(): string
    {
        return 'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiJBSVgiLCJjYXQiOiJDT01NT04iLCJpYXQiOjE3NDY2ODU0MzcsImlzcyI6IlNlYW1sZXNzV2FsbGV0IiwianRpIjoiODE3MWFhODgyYWFjM2Y2ZmJmN2I5NDgyZTJjMTBkMjAiLCJzdWIiOiJBdWRTeXMifQ.qhXMPvGFFnx8Aj0VppWAFowGRrlmmLUPXbTHB8bfWJukXuXUPogMTCGMQ92z6eDZxL1TNMk-ASEhxOUwj1zPcI58kjn0re-WlqBOaJHqGSeMZf_E57F4GFSvjTg_1sNedtOw4dNHAjDEmWV6baj3uV3vcWqTyUWp7ltMdpchE-YWVvg0Zn6MrC4yRUDervjFyIjg_TWbPGx964AMjk-BsaGoufe7_NKW8Ozoj14qIGwoCXbZfYY48pMfvGh-AkR1sbbL0QazVFlHSJVi-PNebKIXON4nFiw5tfU9cdR5x2jR9rodkeLzSNZ7lS4Xg1NfdonqphC2OAYt1aAn7Yg5sQ';
    }
    public function getGrpcSignature(): string
    {
        return '20f40986dd1007958d075974ab0d66f2';
    }
    public function getProviderCode(): string
    {
        return 'AIX';
    }

    public function getApiUrl(): string
    {
        return 'https://stg-games-api.ais-le.com/api/v1';
    }

    public function getAgCode(): string
    {
        return 'ais';
    }

    public function getAgToken(): string
    {
        return 'ag-token';
    }

    public function getSecretKey(): string
    {
        return 'ais-secret-key';
    }
}
