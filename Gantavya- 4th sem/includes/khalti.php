<?php
declare(strict_types=1);

class KhaltiException extends RuntimeException
{
    private $responseData;

    public function __construct(string $message, int $code = 0, array $responseData = [])
    {
        parent::__construct($message, $code);
        $this->responseData = $responseData;
    }

    public function responseData(): array
    {
        return $this->responseData;
    }
}

function khalti_request(string $url, string $secretKey, array $payload, array $acceptedStatuses = [200]): array
{
    if ($secretKey === '') {
        throw new KhaltiException('Khalti is not configured. Add KHALTI_SECRET_KEY to .env.');
    }
    if (!function_exists('curl_init')) {
        throw new KhaltiException('The PHP cURL extension is required for Khalti payments.');
    }

    $curl = curl_init($url);
    if ($curl === false) {
        throw new KhaltiException('Unable to initialize a secure payment request.');
    }

    $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES);
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $encoded,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER => [
            'Authorization: Key ' . $secretKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $body = curl_exec($curl);
    $curlError = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($body === false) {
        throw new KhaltiException('Khalti could not be reached. Please try again.', 0, ['transport_error' => $curlError]);
    }

    $decoded = json_decode((string) $body, true);
    if (!is_array($decoded)) {
        throw new KhaltiException('Khalti returned an unreadable response.', $status);
    }

    if (!in_array($status, $acceptedStatuses, true)) {
        $message = $decoded['detail'] ?? $decoded['error_key'] ?? 'Khalti rejected the payment request.';
        if (is_array($message)) {
            $message = implode(' ', array_map('strval', $message));
        }
        throw new KhaltiException((string) $message, $status, $decoded);
    }

    return $decoded;
}

function khalti_initiate(array $payload): array
{
    global $config;
    return khalti_request(
        $config['khalti']['initiate_url'],
        $config['khalti']['secret_key'],
        $payload,
        [200]
    );
}

function khalti_lookup(string $pidx): array
{
    global $config;
    return khalti_request(
        $config['khalti']['lookup_url'],
        $config['khalti']['secret_key'],
        ['pidx' => $pidx],
        [200, 400]
    );
}
