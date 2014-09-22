<?php

namespace Fut\Connector;

use Fut\Connector\EndpointInterface;
use Fut\Connector\Generic;

/**
 * connector used to connect as a browser web app
 *
 * Class Connector_WebApp
 */
class WebApp extends Generic implements EndpointInterface
{
    /**
     * @var array
     */
    protected $userAccounts;

    /**
     * @var array
     */
    protected $questionStatus;

    /**
     * @var string[]
     */
    protected $urls = array(
        "main"          => "https://www.easports.com/fifa/ultimate-team/web-app",
        "nucleus"       => "https://www.easports.com/iframe/fut15/?baseShowoffUrl=https%3A%2F%2Fwww.easports.com%2Ffifa%2Fultimate-team%2Fweb-app%2Fshow-off&guest_app_uri=http%3A%2F%2Fwww.easports.com%2Ffifa%2Fultimate-team%2Fweb-app&locale=en_US",
        "shards"        => "https://www.easports.com/iframe/fut15/p/ut/shards?_=",
        "accounts"      => "https://www.easports.com/iframe/fut15/p/ut/game/fifa15/user/accountinfo?sku=FUT15WEB&_=",
        "sid"           => "https://www.easports.com/iframe/fut15/p/ut/auth",
        "validate"      => "https://www.easports.com/iframe/fut15/p/ut/game/fifa15/phishing/validate",
        "phishing"      => "https://www.easports.com/iframe/fut15/p/ut/game/fifa14/phishing/question?_="
    );

    /**
     * @param string $email
     * @param string $password
     * @param string $answer
     * @param string $platform
     */
    public function __construct($email, $password, $answer, $platform)
    {
        parent::__construct($email, $password, $answer, $platform);
    }

    /**
     * connects to the endpoint
     */
    public function connect()
    {
        $url = $this->getMainPage();
        $this
            ->login($url)
            ->getNucleusId()
            ->getShards()
            ->getUserAccounts()
            ->getSessionId()
            ->getPhishing()
            ->validate();

        return $this;
    }

    /**
     * exports needed data to reconnect again with actually login
     *
     * @return string[]
     */
    public function exportLoginData()
    {
        return array(
            'nucleusId' => $this->nucId,
            'sessionId' => $this->sid,
            'phishingToken' => $this->phishingToken,
        );
    }

    /**
     * gets the url to send login request to
     *
     * @return string
     */
    private function getMainPage()
    {
        $forge = $this->getForge($this->urls['main'], 'get');
        $data = $forge
            ->removeEndpointHeaders()
            ->sendRequest();

        return $data['response']->getEffectiveUrl();
    }

    /**
     * login request
     *
     * @param string $url
     * @return $this
     */
    private function login($url)
    {
        $forge = $this->getForge($url, 'post');
        $forge
            ->addHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->removeEndpointHeaders()
            ->setBody(array(
                "email" => $this->email,
                "password" => $this->password,
                "_rememberMe" => "on",
                "rememberMe" => "on",
                "_eventId" => "submit",
                "facebookAuth" => ""
            ))
            ->sendRequest();

        return $this;
    }

    /**
     * get nucleus id request
     *
     * @return $this
     */
    private function getNucleusId()
    {
        $forge = $this->getForge($this->urls['nucleus'], 'get');
        $body = $forge
            ->removeEndpointHeaders()
            ->getBody();

        if (!preg_match("/var\ EASW_ID = '(\d*)';/", $body, $matches)) {
            throw new \Exception('Login failed.');
        }

        $this->nucId = $matches[1];

        return $this;
    }

    /**
     * get shards request
     *
     * @return $this
     */
    private function getShards()
    {
        $forge = $this->getForge($this->urls['shards'], 'get');
        $forge
            ->setNucId($this->nucId)
            ->setRoute()
            ->sendRequest();

        return $this;
    }

    /**
     * gets user account data
     *
     * @return $this
     */
    private function getUserAccounts()
    {
        $forge = $this->getForge($this->urls['accounts'], 'get');
        $json = $forge
            ->setNucId($this->nucId)
            ->setRoute()
            ->getJson();

        $this->userAccounts = $json;

        return $this;
    }

    /**
     * get session id
     *
     * @return $this
     */
    private function getSessionId()
    {
        $personaId = $this->userAccounts['userAccountInfo']['personas'][0]['personaId'];
        $personaName = $this->userAccounts['userAccountInfo']['personas'][0]['personaName'];
        $platform = $this->getNucleusPlatform($this->platform);
        $data = array(
            'isReadOnly' => false,
            'sku' => 'FUT15WEB',
            'clientVersion' => 1,
            'nuc' => $this->nucId,
            'nucleusPersonaId' => $personaId,
            'nucleusPersonaDisplayName' => $personaName,
            'nucleusPersonaPlatform' => $platform,
            'locale' => 'en-GB',
            'method' => 'authcode',
            'priorityLevel' => 4,
            'identification' => array(
                'authCode' => ''
            )
        );

        $forge = $this->getForge($this->urls['sid'], 'post');
		$json = $forge
            ->setNucId($this->nucId)
            ->setRoute()
            ->setBody($data, true)
            ->getJson();


        $this->sid = isset($json['sid']) ? $json['sid'] : null;

        return $this;
    }

    /**
     * request to get phishing token
     *
     * @return $this
     */
    private function getPhishing()
    {
        $forge = $this->getForge($this->urls['phishing'], 'get');
        $json = $forge
            ->setNucId($this->nucId)
            ->setSid($this->sid)
            ->setRoute()
            ->getJson();

        $this->questionStatus = $json;

        return $this;

    }

    /**
     * validate the answer if needed
     *
     * @return $this
     */
    private function validate()
    {
        // if not needed to validate
        if (isset($this->questionStatus['debug']) && $this->questionStatus['debug'] == "Already answered question.") {
            $this->phishingToken = $this->questionStatus['token'];

        // need to validate
        } else {
            $forge = $this->getForge($this->urls['validate'], 'post');
            $json = $forge
                ->setSid($this->sid)
                ->setNucId($this->nucId)
                ->setRoute()
                ->addHeader('Content-Type', 'application/x-www-form-urlencoded')
                ->setBody(array(
                    'answer' => $this->answerHash
                ))
                ->getJson();

            $this->phishingToken = $json['token'];
        }

        return $this;
    }

    /**
     * transform the different platform terms to ea needed platform
     *
     * @param string $platform
     * @return string
     */
    private function getNucleusPlatform($platform)
    {
        switch ($platform) {
            case 'ps':
            case 'ps4':
            case 'ps3':
                return 'ps3';
            case 'xboxone':
            case 'xbox360':
            case 'xbox':
                return '360';
            case 'pc':
                return 'pc';
            default:
                return '360';
        }
    }


}
