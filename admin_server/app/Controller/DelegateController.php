<?php

declare(strict_types=1);

namespace App\Controller;

use App\Constants\ErrorCode;
use App\Utils\Http;

class DelegateController extends AbstractController
{
    public function mis()
    {
        $config = config("httpClient.clients.mis");
        $client = make(Http::class)->get($config);
        $uri = substr($this->request->getRequestUri(), 7);
        try {
            $response = $client->request(strtoupper($this->request->getMethod()), $uri, ['json' => $this->request->all()]);
        }catch (\Exception $exception){
            return $this->responseError();
        }
        $responseData = json_decode($response->getBody()->getContents(), true);
        if(data_get($responseData, 'code') !== 0){
            return $this->responseError(ErrorCode::SERVER_ERROR, data_get($responseData, 'msg'));
        }
        return $this->responseSuccess(data_get($responseData, 'data'));
    }
}