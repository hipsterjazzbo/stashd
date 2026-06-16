<?php

declare(strict_types=1);

namespace App\MediaServers\Http;

use App\MediaServers\MediaServerHttpClient;
use App\MediaServers\MediaServerHttpResponse;

final class CurlMediaServerHttpClient implements MediaServerHttpClient
{
    /** @param array<string, string> $headers */
    public function request(
        string $method,
        string $url,
        array $headers = [],
        ?string $body = null,
        int $timeoutSeconds = 15,
    ): MediaServerHttpResponse {
        $curl = curl_init($url);

        if ($curl === false) {
            return new MediaServerHttpResponse(0, '');
        }

        $headerLines = [];

        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }

        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $timeoutSeconds,
            CURLOPT_HTTPHEADER => $headerLines,
        ]);

        if ($body !== null) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        }

        $responseBody = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);

        return new MediaServerHttpResponse(
            status: $status,
            body: is_string($responseBody) ? $responseBody : '',
        );
    }
}
