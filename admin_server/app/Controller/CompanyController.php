<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\AppCompany;

class CompanyController extends AbstractController
{
    public function list()
    {
        $list = AppCompany::select(['company_id', 'company_name'])->get()->all();

        return $this->responseRecord($list);
    }
}