<?php

declare(strict_types = 1);

namespace KirbyTools\Licensing\Http;

use Kirby\Cms\App;
use Kirby\Http\Remote;
use Kirby\Toolkit\A;
use KirbyTools\Licensing\ActivationException;
use KirbyTools\Licensing\ActivationOutcome;

final class KirbyHttpClient implements HttpClientInterface
{
    public function request(string $url, array $options = []): array
    {
        $response = new Remote($url, A::merge([
            'headers' => [
                'X-App-Url' => App::instance()->url()
            ]
        ], $options));

        $status = $response->code();

        // The licensing API answers 401 for unknown credentials and license
        // keys alike; the status code is the contract, the body is not read.
        if ($status === 401) {
            throw new ActivationException(ActivationOutcome::InvalidCredentials);
        }

        if ($status === null || $status < 200 || $status >= 300) {
            throw new ActivationException(ActivationOutcome::RequestFailed, ['status' => $status]);
        }

        return $response->json();
    }
}
