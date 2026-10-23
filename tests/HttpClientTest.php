<?php

declare(strict_types = 1);

use KirbyTools\Licensing\ActivationException;
use KirbyTools\Licensing\ActivationOutcome;
use KirbyTools\Licensing\Http\KirbyHttpClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(KirbyHttpClient::class)]
final class HttpClientTest extends LicenseTestCase
{
    private static mixed $server = null;
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$baseUrl = "http://127.0.0.1:{$port}";
        self::$server = proc_open(
            [PHP_BINARY, '-S', "127.0.0.1:{$port}", __DIR__ . '/fixtures/licensing-api.php'],
            [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
            $pipes
        );

        for ($attempt = 0; $attempt < 50; $attempt++) {
            if ($connection = @fsockopen('127.0.0.1', $port)) {
                fclose($connection);
                return;
            }

            usleep(100_000);
        }

        self::fail('The fake licensing API did not start.');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
        }
    }

    public static function failedRequests(): array
    {
        return [
            'a 400' => [400],
            'a 404' => [404],
            'a 500' => [500],
            'a 503' => [503]
        ];
    }

    #[Test]
    public function request_reports_invalid_credentials_for_a_401(): void
    {
        try {
            (new KirbyHttpClient())->request(self::$baseUrl . '/status/401');
        } catch (ActivationException $e) {
            $this->assertSame(ActivationOutcome::InvalidCredentials, $e->outcome);
            return;
        }

        $this->fail('The request did not fail.');
    }

    #[Test]
    #[DataProvider('failedRequests')]
    public function request_reports_a_failed_request_with_its_status_for_any_other_error(int $status): void
    {
        try {
            (new KirbyHttpClient())->request(self::$baseUrl . '/status/' . $status);
        } catch (ActivationException $e) {
            $this->assertSame(ActivationOutcome::RequestFailed, $e->outcome);
            $this->assertSame(['status' => $status], $e->getDetails());
            return;
        }

        $this->fail('The request did not fail.');
    }

    #[Test]
    public function request_returns_the_decoded_body_of_a_2xx_response(): void
    {
        $this->assertSame(
            ['packageName' => 'test/package', 'licenseCompatibility' => '^1.0.0'],
            (new KirbyHttpClient())->request(self::$baseUrl . '/status/200')
        );
    }
}
