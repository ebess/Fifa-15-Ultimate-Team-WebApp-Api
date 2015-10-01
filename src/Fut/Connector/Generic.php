<?php

namespace Fut\Connector;

use Fut\Connector\CaptchaHandlerInterface;
use Fut\EAHashor;
use Fut\Request\Forge;
use GuzzleHttp\Client;

/**
 * Class Connector_Abstract
 */
abstract class Generic
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
    protected $sid;

    /**
     * @var string
     */
    protected $nucId;

    /**
     * @var string
     */
    protected $buildCl;

    /**
     * @var string
     */
    protected $phishingToken;

    /**
     * @var CaptchaHandlerInterface
     */
    protected $captchaHandler = null;

    /**
     * creates a connector with given credentials
     *
     * @param string $email
     * @param string $password
     * @param string $answer
     * @param string $platform
     */
    public function __construct($email, $password, $answer, $platform, $security_code)
    {
        $this->email = $email;
        $this->password = $password;
        $this->answer = $answer;
        $this->platform = $platform;
        $this->security_code = $security_code;
        $this->answerHash = EAHashor::getHash($answer);
    }

    /**
     * connects to the api
     *
     * @return $this
     */
    abstract public function connect();

    /**
     * exports the login data
     *
     * @return array
     */
    abstract public function exportLoginData();


    /**
     * handler for process the captcha request
     *
     * @param CaptchaHandler $handler
     * @return $this
     */
    public function setCaptchaHandler(CaptchaHandlerInterface $handler)
    {
        $this->captchaHandler = $handler;
        
        return $this;
    }

    /**
     * @param Client $client
     * @return $this
     */
    public function setClient($client)
    {
        $this->client = $client;

        return $this;
    }

    /**
     * initialize a request forge and returns it
     *
     * @param string $url
     * @param string $method
     * @return Forge
     */
    protected function getForge($url, $method)
    {
        return new Forge($this->client, $url, $method);
    }

    /**
     * define setter and getter
     *
     * @param string $method
     * @param array $args
     *
     * @return $this|string|void
     */
    public function __call($method, $args)
    {
        if (substr($method, 0, 3) === 'get') {
            $attr = substr($method, 3);
            if (property_exists(__CLASS__, $attr)) {
                return $attr;
            }
        } elseif (substr($method, 0, 3) === 'set') {
            $attr = substr($method, 3);
            if (property_exists(__CLASS__, $attr) && isset($args[0])) {
                $this->$attr = $args[0];

                return $this;
            }
        }

        return $this; // ?
    }
}