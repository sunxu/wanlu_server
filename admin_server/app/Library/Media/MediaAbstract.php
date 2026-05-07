<?php

declare(strict_types=1);

namespace App\Library\Media;

use App\Utils\Http;
use Exception;
use GuzzleHttp\Client;

abstract class MediaAbstract
{
    protected $baseUri;

    protected $userId;

    protected $secret;

    protected $conf;

    abstract protected function request(string $uri = '', string $method = 'GET', array $params = [], array $headers = []);

    abstract public function income(string $date);

    abstract public function adList();

    public function __construct(array $conf)
    {
        $this->conf = $conf;
        $this->setUser(strval($conf['user_id'] ?? ''), strval($conf['secret'] ?? ''));
    }

    /**
     * 设置应用信息
     * @param string $userId
     * @param string $secret
     * @return $this
     */
    protected function setUser(string $userId, string $secret)
    {
        $this->userId = $userId;
        $this->secret = $secret;

        return $this;
    }

    /**
     * @return array
     * @throws Exception
     */
    protected function getUser()
    {
        if (!$this->userId || !$this->secret) {
            throw new Exception('userId or secret is empty.');
        }

        return [
            'userId' => $this->userId,
            'secret' => $this->secret
        ];
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function getUserId()
    {
        if (!$this->userId) {
            throw new Exception('userId is empty.');
        }

        return $this->userId;
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function getSecret()
    {
        if (!$this->secret) {
            throw new Exception('secret is empty.');
        }

        return $this->secret;
    }

    /**
     * @return Client
     */
    protected function getClient()
    {
        return make(Http::class)->get([
            'base_uri' => $this->baseUri
        ]);
    }
}
