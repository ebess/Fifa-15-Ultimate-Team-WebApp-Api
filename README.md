Connector class for mobile endpoint of Fifa 15 Ultimate Team.
Also you can use composer to install the connectors

 composer.json
```json
    require {
        "fut/connectors": "1.0.*"
    }
```

Example: (also see example.php)
```php
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
         * there are two platforms at the moment
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
            'your@email.com',
            'your_password',
            'secret_answer',
            Forge::PLATFORM_PLAYSTATION,
            Forge::ENDPOINT_WEBAPP
        );

        $export = $connector
            ->connect()
            ->export();

    } catch(Exception $e) {
        die('login failed' . PHP_EOL);
    }

    // example for playstation accounts to get the credits
    // 3. parameter of the forge factory is the actual real http method
    // 4. parameter is the overridden method for the webapp headers
    $forge = Fut\Request\Forge::getForge($client, '/ut/game/fifa15/user/credits', 'post', 'get');
    $json = $forge
        ->setNucId($export['nucleusId'])
        ->setSid($export['sessionId'])
        ->setPhishing($export['phishingToken'])
        ->getJson();

    echo "you have " . $json['credits'] . " coins" . PHP_EOL;
```
