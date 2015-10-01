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
        "site"          => "https://www.easports.com",
        "main"          => "https://www.easports.com/fifa/ultimate-team/web-app",
        "config"        => "https://www.easports.com/iframe/fut16/bundles/futweb/web/flash/xml/site_config.xml",
        "isLoggedIn"    => "https://www.easports.com/fifa/api/isUserLoggedIn",
        "keepAlive"     => "https://www.easports.com/fifa/api/keepalive",
        "host"          => array(
            'pc'        => 'https://utas.s2.fut.ea.com:443',
            'ps3'       => 'https://utas.s2.fut.ea.com:443',
            'ps4'       => 'https://utas.s2.fut.ea.com:443',
            'xbox'      => 'https://utas.s3.fut.ea.com:443',
            'xbox360'   => 'https://utas.s3.fut.ea.com:443',
            'ios'       => 'https://utas.fut.ea.com:443',
            'and'       => 'https://utas.fut.ea.com:443'
        ),
        "nucleus"       => "https://www.easports.com/iframe/fut16/?baseShowoffUrl=https%3A%2F%2Fwww.easports.com%2Ffifa%2Fultimate-team%2Fweb-app%2Fshow-off&guest_app_uri=http%3A%2F%2Fwww.easports.com%2Ffifa%2Fultimate-team%2Fweb-app&locale=en_US",
        "shards"        => "https://www.easports.com/iframe/fut16/p/ut/shards?_=",
        "accounts"      => "https://www.easports.com/iframe/fut16/p/ut/game/fifa16/user/accountinfo?sku=FUT16WEB&_=",
        "sid"           => "https://www.easports.com/iframe/fut16/p/ut/auth",
        "validate"      => "https://www.easports.com/iframe/fut16/p/ut/game/fifa16/phishing/validate",
        "phishing"      => "https://www.easports.com/iframe/fut16/p/ut/game/fifa16/phishing/question?_="
    );

    /**
     * @param string $email
     * @param string $password
     * @param string $answer
     * @param string $platform
     * @param string $security_code
     */
    public function __construct($email, $password, $answer, $platform, $security_code)
    {
        parent::__construct($email, $password, $answer, $platform, $security_code);
    }

    /**
     * connects to the endpoint
     */
    public function connect()
    {
        $url = $this->getMainPage();
        $this
            ->login($url)
            ->launchWebApp()
            ->getUrls()
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
     * checks if user is logged in
     *
     * @return bool
     */
    private function isLoggedIn()
    {
        $forge = $this->getForge($this->urls['isLoggedIn'], 'get');
        $data = $forge
            ->removeEndpointHeaders()
            ->sendRequest();

        $json = $data['response']->json();

        return $json['isLoggedIn'] === true ? true : false;
    }

    /**
     * keep session alive
     *
     * @return bool
     */
    private function keepAlive()
    {
        $forge = $this->getForge($this->urls['keepAlive'], 'get');
        $json = $forge
            ->removeEndpointHeaders()
            ->getJson();

        return $json['isLoggedIn'] === true ? true : false;
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
        $data = $forge
            ->addHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->removeEndpointHeaders()
            ->setBody(array(
                "email" => $this->email,
                "password" => $this->password,
                "_rememberMe" => "on",
                "rememberMe" => "on",
                "_eventId" => "submit"
            ))
            ->sendRequest();


        if (preg_match("/Login Verification/", $data['response'], $matches)) {
            $url = $data['response']->getEffectiveUrl();
            //$url = preg_replace('/(e\\d+)s2/', '\1s3', $url);
            $this->twoWayVerification($url);
        }

        if (!$this->isLoggedIn()) {
            throw new \Exception('Login failed.');
        }

        return $this;
    }

    private function twoWayVerification($url)
    {
        $forge = $this->getForge($url, 'post');
        $data = $forge
            ->addHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->removeEndpointHeaders()
            ->setBody(array(
                "twofactorCode" => $this->security_code,
                "_trustThisDevice" => "on",
                "trustThisDevice" => "on",
                "_eventId" => "submit"
            ))
            ->sendRequest();

        // Two way authentication needed
        if (preg_match("/Set Up an App Authenticator/", $data['response'], $matches)) {
            //$url = $data['response']->getEffectiveUrl();
            $url = preg_replace('/(e\\d+)s2/', '\1s3', $url);
            $this->appAuthenticator($url);
        }

        // Security code incorrect
        if (preg_match("/Login Verification/", $data['response'], $matches)) {
            throw new \Exception('Security code incorrect');
        }

        return;
    }

    private function appAuthenticator($url)
    {
        $forge = $this->getForge($url, 'post');
        $data = $forge
            ->addHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->removeEndpointHeaders()
            ->setBody(array(
                "_eventId" => "cancel",
                "appDevice" => "IPHONE"
            ))
            ->sendRequest();

        if(!preg_match('/<title>FIFA Football/', $data['response'], $matches)) {
            throw new \Exception('Security code incorrect');
        }

        return;
    }

    private function launchWebApp()
    {
        $forge = $this->getForge($this->urls['nucleus'], 'get');
        $body = $forge
            ->removeEndpointHeaders()
            ->getBody();

        if (!preg_match("/var EASW_ID = '(\\d+)';/", $body, $matches)) {
            throw new \Exception('Launching WebApp failed.');
        }
        $this->nucId = $matches[1];

        if (!preg_match("/var BUILD_CL = '(\\d+)';/", $body, $matches)) {
            throw new \Exception('Launching WebApp failed.');
        }
        $this->buildCl = $matches[1];

        return $this;
    }

    /**
     * get webapp urls
     *
     * @return $this
     */
    private function getUrls()
    {
        $url = $this->urls['config'] . '?c1=' . $this->buildCl;
        $forge = $this->getForge($url, 'get');
        $data = $forge
            ->removeEndpointHeaders()
            ->sendRequest();

        $xml = $data['response']->xml();

        $services = $xml->services->prod;
        $path = $this->urls['host'][$this->platform] . $xml->directHttpServiceDestination . 'game/fifa16/';
        $path_auth = $this->urls['site'] . '/iframe/fifa16' . $xml->httpServiceDestination;

        foreach ($services->children() as $key => $value) {
            if ((string)$key == 'authentication') {
                $this->urls['fut'][(string)$key] = $path_auth . (string)$value;
            } else {
                $this->urls['fut'][(string)$key] = $path . (string)$value;
            }
        }

        return $this;
    }

//    /**
//     * get shards request
//     *
//     * @return $this
//     */
//    private function getShards()
//    {
//        $forge = $this->getForge($this->urls['shards'], 'get');
//        $forge
//            ->setNucId($this->nucId)
//            ->setRoute()
//            ->sendRequest();
//
//        return $this;
//    }

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
            ->addHeader('origin', 'https://www.easports.com')
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
            'sku' => 'FUT16WEB',
            'clientVersion' => 1,
//            'nuc' => $this->nucId,
            'nucleusPersonaId' => $personaId,
            'nucleusPersonaDisplayName' => $personaName,
            'gameSku' => 'FFA16XBO',
            'nucleusPersonaPlatform' => $platform,
            'locale' => 'en-GB',
            'method' => 'authcode',
            'priorityLevel' => 4,
            'identification' => array(
                'authCode' => ''
            )
        );

        $forge = $this->getForge($this->urls['fut']['authentication'], 'post');
        $json = $forge
//            ->setNucId($this->nucId)
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

        // if captcha triggered call the captcha handler
        if (isset($json['code']) && $json['code'] == 459 && $json['string'] == 'Captcha Triggered') {

            // if proper handler
            if ($this->captchaHandler !== null) {
                $this->captchaHandler->run([
                    'sid' => $this->sid,
                    'nucleusId' => $this->nucId
                ]);
            }

        }

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
