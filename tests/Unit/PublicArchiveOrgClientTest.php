<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use abromeit\archiveorgbackups\archiveorg\PublicArchiveOrgClient;
use abromeit\archiveorgbackups\archiveorg\exceptions\InvalidArchiveOrgResponseException;
use abromeit\archiveorgbackups\archiveorg\exceptions\QuotaExhaustedException;

final class PublicArchiveOrgClientTest extends TestCase
{
    public function testSubmitUrlDetectsQuotaOnHttpErrorResponses(): void
    {
        $client = $this->createClient([
            new Response(429, [], 'You cannot make more than (150) captures per day.'),
        ]);

        $service = new PublicArchiveOrgClient($client);

        $this->expectException(QuotaExhaustedException::class);
        $service->submitUrl('https://example.com/');
    }

    public function testGetSaveStatusFailsFastOnPermanentHttpErrors(): void
    {
        $client = $this->createClient([
            new Response(404, [], '{}'),
        ]);

        $service = new PublicArchiveOrgClient($client);

        $this->expectException(InvalidArchiveOrgResponseException::class);
        $service->getSaveStatus('job-123');
    }

    /**
     * @param Response[] $responses
     */
    private function createClient(array $responses): Client
    {
        $handler = HandlerStack::create(new MockHandler($responses));

        return new Client([
            'handler' => $handler,
            'http_errors' => false,
        ]);
    }
}
