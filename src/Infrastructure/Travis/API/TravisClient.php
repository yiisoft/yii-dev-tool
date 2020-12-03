<?php

declare(strict_types=1);

namespace Yiisoft\YiiDevTool\Infrastructure\Travis\API;

use InvalidArgumentException;
use RuntimeException;

class TravisClient
{
    private const COM_ENDPOINT = 'https://api.travis-ci.com';
    private const ORG_ENDPOINT = 'https://api.travis-ci.org';

    private const GET = 'get';
    private const POST = 'post';
    private const DELETE = 'delete';

    private string $endpoint;
    private string $token;

    public function __construct(string $APIAccessToken, $useOrg = false)
    {
        if (empty($APIAccessToken)) {
            throw new InvalidArgumentException('Token cannot be empty.');
        }

        $this->endpoint = $useOrg ? self::ORG_ENDPOINT : self::COM_ENDPOINT;
        $this->token = (string) $APIAccessToken;
    }

    public function get(string $path): array
    {
        return $this->request($path);
    }

    public function post(string $path, array $data): array
    {
        return $this->request($path, self::POST, $data);
    }

    public function delete(string $path): void
    {
        $this->request($path, self::DELETE);
    }

    private function request(string $path, string $type = self::GET, $dataToSend = null): ?array
    {
        $url = rtrim($this->endpoint, '/') . '/' . ltrim($path, '/');

        $headers = [
            "Travis-API-Version: 3",
            "Authorization: token {$this->token}",
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        if ($type === self::POST) {
            curl_setopt($ch, CURLOPT_POST, true);
            $headers[] = 'Content-Type: application/json';

            if ($dataToSend !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataToSend));
            }
        } elseif ($type === self::DELETE) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $curlResult = curl_exec($ch);
        curl_close($ch);

        if ($curlResult === false) {
            throw new RuntimeException("Failed to get a response from $url");
        }

        if ($type === self::DELETE) {
            return null;
        }

        $resultData = json_decode($curlResult, true);
        if ($resultData === null) {
            throw new RuntimeException("Failed to decode the response received from $url");
        }

        if (!array_key_exists('@type', $resultData)) {
            throw new RuntimeException("Travis response does not contain field '@type'.");
        }

        if ($resultData['@type'] === 'error') {
            throw new RuntimeException("Travis error occurred: " . $resultData['error_message']);
        }

        return $resultData;
    }
}
