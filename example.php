<?php
require_once __DIR__ . "/vendor/autoload.php";

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Subscriber\Cookie as CookieSubscriber;
use Fut\Connector;
use Fut\Request\Forge;

/**
 * the connector will not export your cookie jar anymore
 * keep a reference on this object somewhere to inject it on reconnecting
 */

$client = new Client();
$cookieJar = new CookieJar();
$cookieSubscriber = new CookieSubscriber($cookieJar);
$client->getEmitter()->attach($cookieSubscriber);

try {

    /**
     * there are two platforms at the the moment
     *
     * playstation: Forge::PLATFORM_PLAYSTATION
     * xbox: Forge::PLATFORM_XBOX
     *
     * also you can set different endpoints
     *
     * webapp: Forge::ENDPOINT_WEBAPP
     *
     */
    $connector = new Connector(
        $client,
        '', //email
        '', //password
        '', //secret answer
        Forge::PLATFORM_XBOX,
        '', //2FA code
        Forge::ENDPOINT_WEBAPP
    );

    $export = $connector
        ->connect()
        ->export();

} catch(Exception $e) {
    die($e->getMessage() . PHP_EOL);
}

// example for playstation accounts to get the credits
// 3. parameter of the forge factory is the actual real http method
// 4. parameter is the overridden method for the webapp headers
$forge = Fut\Request\Forge::getForge($client, '/ut/game/fifa16/user/credits', 'post', 'get');
$json = $forge
    ->setNucId($export['nucleusId'])
    ->setSid($export['sessionId'])
    ->setPhishing($export['phishingToken'])
    ->getJson();

echo "you have " . $json['credits'] . " coins" . PHP_EOL;