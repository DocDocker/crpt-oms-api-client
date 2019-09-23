<?php

declare(strict_types=1);

namespace Lamoda\OmsClient\V2;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\RequestOptions;
use Lamoda\OmsClient\Exception\OmsClientExceptionInterface;
use Lamoda\OmsClient\Exception\OmsGeneralErrorException;
use Lamoda\OmsClient\Exception\OmsRequestErrorException;
use Lamoda\OmsClient\Serializer\SerializerInterface;
use Lamoda\OmsClient\V2\Dto\CloseICArrayResponse;
use Lamoda\OmsClient\V2\Dto\GetICBufferStatusResponse;
use Lamoda\OmsClient\V2\Dto\GetICsFromOrderResponse;

final class OmsApi
{
    /**
     * @var ClientInterface
     */
    private $client;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct(ClientInterface $client, SerializerInterface $serializer)
    {
        $this->client = $client;
        $this->serializer = $serializer;
    }

    public function getICBufferStatus(
        string $token,
        string $omsId,
        string $orderId,
        string $gtin
    ): GetICBufferStatusResponse {
        $result = $this->request($token, 'GET', '/api/v2/buffer/status', [
            'omsId' => $omsId,
            'orderId' => $orderId,
            'gtin' => $gtin,
        ]);

        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->serializer->deserialize(GetICBufferStatusResponse::class, $result);
    }

    public function getICsFromOrder(
        string $token,
        string $omsId,
        string $orderId,
        string $gtin,
        int $quantity,
        string $lastBlockId = '0'
    ): GetICsFromOrderResponse {
        $result = $this->request($token, 'GET', '/api/v2/codes', [
            'omsId' => $omsId,
            'orderId' => $orderId,
            'gtin' => $gtin,
            'quantity' => $quantity,
            'lastBlockId' => $lastBlockId,
        ]);

        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->serializer->deserialize(GetICsFromOrderResponse::class, $result);
    }

    public function closeICArray(string $token, string $omsId, string $orderId, string $gtin): CloseICArrayResponse
    {
        $result = $this->request($token, 'GET', '/api/v2/buffer/close', [
            'omsId' => $omsId,
            'orderId' => $orderId,
            'gtin' => $gtin,
        ]);

        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->serializer->deserialize(CloseICArrayResponse::class, $result);
    }

    /**
     * @throws OmsRequestErrorException
     */
    private function request(string $token, string $method, string $uri, array $query = [], $body = null): string
    {
        $options = [
            RequestOptions::BODY => $body,
            RequestOptions::HEADERS => [
                'Content-Type' => 'application/json',
                'clientToken' => $token,
            ],
            RequestOptions::QUERY => $query,
            RequestOptions::HTTP_ERRORS => true,
        ];

        $uri = ltrim($uri, '/');

        try {
            $result = $this->client->request($method, $uri, $options);
        } catch (\Throwable $exception) {
            /* @noinspection PhpUnhandledExceptionInspection */
            throw $this->handleRequestException($exception);
        }

        return (string)$result->getBody();
    }

    private function handleRequestException(\Throwable $exception): OmsClientExceptionInterface
    {
        if ($exception instanceof BadResponseException) {
            $response = $exception->getResponse();
            $responseBody = $response ? (string)$response->getBody() : '';
            $responseCode = $response ? $response->getStatusCode() : 0;

            return OmsRequestErrorException::becauseOfError($responseCode, $responseBody, $exception);
        }

        return OmsGeneralErrorException::becauseOfError($exception);
    }
}