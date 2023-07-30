<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Component\Mailer\Bridge\Scaleway\Transport;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

final class ScalewaySmtpTransport extends EsmtpTransport
{
    public function __construct(string $projetId, string $token, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        parent::__construct('smtp.tem.scw.cloud', 465, true, $dispatcher, $logger);

        $this->setUsername($projetId);
        $this->setPassword($token);
    }
}
