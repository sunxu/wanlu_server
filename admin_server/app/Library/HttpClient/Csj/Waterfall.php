<?php

declare(strict_types=1);

namespace App\Library\HttpClient\Csj;

class Waterfall extends BaseClient
{
    public function batchAdslot(array $params)
    {
        return $this->post('/union_media/open/api/mediation/batch_adslot', $params);
    }
}