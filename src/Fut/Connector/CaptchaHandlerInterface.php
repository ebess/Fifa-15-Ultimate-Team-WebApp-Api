<?php

namespace Fut\Connector;

/**
 * Interface CaptchaHandlerInterface
 */
interface CaptchaHandlerInterface
{
	/**
     * run this method after the captcha was triggered
     * $payload contains the sid
     *
     * @param mixed[] $payload
     */
    public function run($payload);
}