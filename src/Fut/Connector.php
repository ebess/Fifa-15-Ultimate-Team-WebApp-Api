<?php

namespace Fut;

use Fut\Request\Forge;
use Fut\Connector\WebApp;
use Fut\Connector\Mobile;
use Fut\ConnectorInterface;
use Fut\Connector\EndpointInterface;
use GuzzleHttp\Client;

/**
 * connector class wrapper
 *
 * Class Connector
 * @package Fut
 */
class Connector implements ConnectorInterface
{
    /**
     * @var string
     */
    protected $password;

    /**
     * @var string
     */
    protected $answerHash;

    /**
     * @var string
     */
    protected $email;

    /**
     * @var string
     */
    protected $platform;

    /**
     * @var string
     */
    protected $security_code;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var string
     */
    protected $answer;

    /**
     * @var string
     */
    protected $endpoint;

    /**
     * @var string[]
     */
    protected $endpoints = array(
        Forge::ENDPOINT_WEBAPP,
        Forge::ENDPOINT_MOBILE
    );

    /**
     * @var mixed
     */
    protected $payload = null;

    /**
     * @var null|EndpointInterface
     */
    protected $connector = null;

    /**
     * creates wrapper connector
     *
     * @param Client $client
     * @param string $email
     * @param string $password
     * @param string $answer
     * @param string $platform
     * @param string $security_code
     * @param string $endpoint
     */
    public function __construct($client, $email, $password, $answer, $platform, $security_code, $endpoint, $payload = null)
    {
        $this->client = $client;
        $this->email = $email;
        $this->password = $password;
        $this->answer = $answer;
        $this->platform = $platform;
        $this->security_code = $security_code;
        $this->endpoint = $endpoint;
        $this->payload = $payload;

        Forge::setPlatform($this->platform);
        Forge::setEndpoint($this->endpoint);
    }

    /**
     * connect with the appropriate connector
     *
     * @return $this
     */
    public function connect()
    {
        if (in_array($this->endpoint, $this->endpoints, true)) {

            // set forge endpoint
            Forge::setEndpoint($this->endpoint);

            switch($this->endpoint) {
                case Forge::ENDPOINT_WEBAPP:
                    $this->connector = new WebApp($this->email, $this->password, $this->answer, $this->platform, $this->security_code);
                    break;
                case Forge::ENDPOINT_MOBILE:
                    $this->connector = new Mobile($this->email, $this->password, $this->answer, $this->platform);
                    break;
            }

            // set the captcha handler if needed
            if ($this->payload !== null && isset($this->payload['captcha_handler'])) {
                $captchaHandler = $this->payload['captcha_handler'];
                $this->connector->setCaptchaHandler($captchaHandler);
            }

            $this->connector
                ->setClient($this->client);

            $this->connector->connect();
        }

        return $this;
    }

    /**
     * returns needed data for login again
     *
     * @return string[]
     */
    public function export()
    {
        return $this->connector->exportLoginData();
    }
}