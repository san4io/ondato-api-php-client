<?php
declare(strict_types=1);

namespace Velser\OndatoApiClient;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use LogicException;
use Velser\OndatoApiClient\Exception\WrongFieldsDataException;
use Velser\OndatoApiClient\Kyc\Entity\GetDataRequest;
use Velser\OndatoApiClient\Kyc\Entity\GetStatusRequest;
use Velser\OndatoApiClient\Kyc\Entity\GetStatusResponse;
use Velser\OndatoApiClient\Kyc\Entity\StartSessionRequest;
use Velser\OndatoApiClient\Kyc\Entity\StartSessionResponse;
use Velser\OndatoApiClient\Kyc\Mapper\GetDataMapper;
use Velser\OndatoApiClient\Kyc\Mapper\GetStatusMapper;
use Velser\OndatoApiClient\Kyc\Mapper\ParsedDocumentDataMapper;
use Velser\OndatoApiClient\Kyc\Mapper\SessionDataMapper;
use Velser\OndatoApiClient\Kyc\Mapper\StartSessionMapper;

class KycApiClient
{
    private $client;
    private $apiKey;

    public function __construct(ClientInterface $client, string $apiKey)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
    }

    public function startSession(StartSessionRequest $startSessionRequest): StartSessionResponse
    {
        if ($startSessionRequest->getSessionData() === null) {
            throw new LogicException('Start session request must have session data');
        }

        $startSessionRequest->setApiKey($this->apiKey);

        $startSessionMapper = new StartSessionMapper(new SessionDataMapper());

        try {
            $response = $this->client->request(
                'POST',
                '/kyc/start-session',
                [
                    'json' => $startSessionMapper->mapFromEntity($startSessionRequest),
                ]
            );

            return $startSessionMapper->mapToEntity(json_decode($response->getBody()->getContents(), true));
        } catch (ClientException $exception) {
            $this->handleClientException($exception);

            throw $exception;
        }
    }

    public function getData(string $token)
    {
        $getDataRequest = (new GetDataRequest())
            ->setApiKey($this->apiKey)
            ->setToken($token)
        ;

        $getDataMapper = new GetDataMapper(new SessionDataMapper(), new ParsedDocumentDataMapper());

        try {
            $response = $this->client->request(
                'POST',
                '/kyc/get-data',
                [
                    'json' => $getDataMapper->mapFromEntity($getDataRequest),
                ]
            );

            return $getDataMapper->mapToEntity(json_decode($response->getBody()->getContents(), true));
        } catch (ClientException $exception) {
            $this->handleClientException($exception);

            throw $exception;
        }
    }

    public function getStatus(string $email): GetStatusResponse
    {
        $getStatusRequest = (new GetStatusRequest())
            ->setApiKey($this->apiKey)
            ->setEmail($email)
        ;

        $getStatusMapper = new GetStatusMapper();

        try {
            $response = $this->client->request(
                'POST',
                '/kyc/get-status',
                [
                    'json' => $getStatusMapper->mapFromEntity($getStatusRequest),
                ]
            );

            return $getStatusMapper->mapToEntity(json_decode($response->getBody()->getContents(), true));
        } catch (ClientException $exception) {
            $this->handleClientException($exception);

            throw $exception;
        }
    }

    private function handleClientException(ClientException $exception)
    {
        if ($exception->getCode() === 400) {
            throw new WrongFieldsDataException(
                json_decode($exception->getResponse()->getBody()->getContents(), true)
            );
        }
    }
}