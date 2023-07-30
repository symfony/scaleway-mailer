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
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\Exception\HttpTransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractApiTransport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ScalewayApiTransport extends AbstractApiTransport
{
    private const HOST = 'api.scaleway.com';

    private string $projectId;
    private string $token;
    private ?string $region;

    public function __construct(string $projectId, string $token, string $region = null, HttpClientInterface $client = null, EventDispatcherInterface $dispatcher = null, LoggerInterface $logger = null)
    {
        $this->projectId = $projectId;
        $this->token = $token;
        $this->region = $region;

        parent::__construct($client, $dispatcher, $logger);
    }

    public function __toString(): string
    {
        $region = $this->region ? '?region='.$this->region : '';

        return sprintf('scaleway+api://%s@%s%s', $this->getEndpoint(), $this->projectId, $region);
    }

    protected function doSendApi(SentMessage $sentMessage, Email $email, Envelope $envelope): ResponseInterface
    {
        $region = $this->region ?? 'fr-par';
        $path = sprintf('/transactional-email/v1alpha1/regions/%s/emails', $region);

        $response = $this->client->request('POST', 'https://'.$this->getEndpoint().$path, [
            'json' => $this->getPayload($email, $envelope),
            'headers' => [
                'X-Auth-Token' => $this->token,
            ],
        ]);

        try {
            $statusCode = $response->getStatusCode();
            $result = $response->toArray(false);
        } catch (DecodingExceptionInterface $e) {
            throw new HttpTransportException('Unable to send an email: '.$response->getContent(false).sprintf(' (code %d).', $statusCode), $response);
        } catch (TransportExceptionInterface $e) {
            throw new HttpTransportException('Could not reach the remote Scaleway server.', $response, 0, $e);
        }

        if (200 !== $statusCode) {
            throw new HttpTransportException('Unable to send an email: '.$result['message'].sprintf(' (code %d).', $statusCode), $response);
        }

        $sentMessage->setMessageId($result['emails'][0]['message_id']);

        return $response;
    }

    private function getPayload(Email $email, Envelope $envelope): array
    {
        $payload = [
            'from' => $this->formatAddress($envelope->getSender()),
            'to' => $this->formatAddresses($this->getRecipients($email, $envelope)),
            'subject' => $email->getSubject(),
            'project_id' => $this->projectId,
        ];
        if ($emails = $email->getCc()) {
            $payload['cc'] = $this->formatAddresses($emails);
        }
        if ($emails = $email->getBcc()) {
            $payload['bcc'] = $this->formatAddresses($emails);
        }
        if ($email->getTextBody()) {
            $payload['text'] = $email->getTextBody();
        }
        if ($email->getHtmlBody()) {
            $payload['html'] = $email->getHtmlBody();
        }
        if ($attachements = $this->prepareAttachments($email)) {
            $payload['attachment'] = $attachements;
        }

        return $payload;
    }

    private function prepareAttachments(Email $email): array
    {
        $attachments = [];
        foreach ($email->getAttachments() as $attachment) {
            $headers = $attachment->getPreparedHeaders();
            $filename = $headers->getHeaderParameter('Content-Disposition', 'filename');

            $attachments[] = [
                'name' => $filename,
                'type' => $headers->get('Content-Type')->getBody(),
                'content' => base64_encode($attachment->bodyToString()),
            ];
        }

        return $attachments;
    }

    private function formatAddress(Address $address): array
    {
        $array = ['email' => $address->getAddress()];

        if ($address->getName()) {
            $array['name'] = $address->getName();
        }

        return $array;
    }

    protected function formatAddresses(array $addresses): array
    {
        return array_map(function (Address $address) {
            return $this->formatAddress($address);
        }, $addresses);
    }

    private function getEndpoint(): ?string
    {
        return ($this->host ?: self::HOST).($this->port ? ':'.$this->port : '');
    }
}
