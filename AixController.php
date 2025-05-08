<?php
namespace Providers\Aix;

use App\Http\Controllers\AbstractCasinoController;

class AixController extends AbstractCasinoController
{
    public function __construct(protected AixService $service, protected AixResponse $response){}
}