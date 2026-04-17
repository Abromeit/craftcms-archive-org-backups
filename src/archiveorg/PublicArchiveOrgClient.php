<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\archiveorg;

use Craft;
use DateTimeImmutable;
use DateTimeZone;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use abromeit\archiveorgbackups\archiveorg\exceptions\InvalidArchiveOrgResponseException;
use abromeit\archiveorgbackups\archiveorg\exceptions\QuotaExhaustedException;
use abromeit\archiveorgbackups\archiveorg\exceptions\TemporaryArchiveOrgException;
use abromeit\archiveorgbackups\helpers\ArchiveOrgParser;
use yii\helpers\Json;

final class PublicArchiveOrgClient implements ArchiveOrgClientInterface
{
    private readonly Client $client;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            RequestOptions::TIMEOUT => 20,
            RequestOptions::CONNECT_TIMEOUT => 10,
            RequestOptions::HTTP_ERRORS => false,
            RequestOptions::HEADERS => [
                'User-Agent' => 'Archive.org Backups for Craft CMS',
                'Accept' => 'application/json, text/html;q=0.9',
            ],
        ]);
    }

    public function submitUrl(string $url): array
    {
        try {
            $response = $this->client->post(ArchiveOrgEndpoints::saveUrl(), [
                RequestOptions::FORM_PARAMS => ['url' => rtrim($url, '/')],
                RequestOptions::HEADERS => [
                    'Accept' => 'text/html,application/xhtml+xml',
                ],
            ]);
        } catch (GuzzleException $exception) {
            throw new TemporaryArchiveOrgException($exception->getMessage(), 0, $exception);
        }

        $status = $response->getStatusCode();
        $body = (string) $response->getBody();
        $observedLimit = ArchiveOrgParser::detectDailyLimit($body);

        if ($observedLimit !== null) {
            throw new QuotaExhaustedException($observedLimit);
        }

        if ($status >= 500) {
            throw new TemporaryArchiveOrgException('Archive.org is temporarily unavailable.');
        }

        if ($status >= 400) {
            throw new InvalidArchiveOrgResponseException(
                'Archive.org save request failed with HTTP ' . $status . '.'
            );
        }

        $jobId = ArchiveOrgParser::extractJobId($body);

        if ($jobId === null) {
            throw new InvalidArchiveOrgResponseException('Archive.org did not return a job id.');
        }

        return [
            'jobId' => $jobId,
            'observedDailyLimit' => $observedLimit,
        ];
    }

    public function getSaveStatus(string $jobId): array
    {
        $payload = $this->requestJson(ArchiveOrgEndpoints::saveStatusUrl($jobId));
        $status = isset($payload['status']) ? (string) $payload['status'] : '';

        if ($status === '') {
            throw new InvalidArchiveOrgResponseException('Archive.org status payload is missing the status.');
        }

        return [
            'status' => $status,
            'message' => isset($payload['message']) ? (string) $payload['message'] : '',
            'statusExt' => isset($payload['status_ext']) ? (string) $payload['status_ext'] : null,
        ];
    }

    public function getAvailabilitySnapshot(string $url): ?array
    {
        $payload = $this->requestJson(ArchiveOrgEndpoints::availabilityUrl($url));

        return ArchiveOrgParser::extractAvailabilitySnapshot($payload);
    }

    public function getLatestCdxCapture(string $url): ?array
    {
        $query = [
            'url' => $url,
            'limit' => 1,
            'filter' => 'statuscode:200',
            'output' => 'json',
            'fl' => 'timestamp,original',
            'from' => (new DateTimeImmutable('-1 year', new DateTimeZone(Craft::$app->getTimeZone())))
                ->format('Y'),
        ];

        $payload = $this->requestJson(ArchiveOrgEndpoints::cdxUrl($query));

        if (!is_array($payload)) {
            throw new InvalidArchiveOrgResponseException('Archive.org CDX payload is invalid.');
        }

        /** @var array<int, array<int, string>> $payload */
        return ArchiveOrgParser::extractLatestCdxCapture($payload);
    }

    /**
     * @return array<mixed>
     */
    private function requestJson(string $url): array
    {
        try {
            $response = $this->client->get($url);
        } catch (GuzzleException $exception) {
            throw new TemporaryArchiveOrgException($exception->getMessage(), 0, $exception);
        }

        $status = $response->getStatusCode();

        if ($status === 429 || $status >= 500) {
            throw new TemporaryArchiveOrgException('Archive.org is temporarily unavailable.');
        }

        if ($status >= 400) {
            throw new InvalidArchiveOrgResponseException(
                'Archive.org request failed with HTTP ' . $status . '.'
            );
        }

        $decoded = Json::decodeIfJson((string) $response->getBody());

        if (!is_array($decoded)) {
            throw new InvalidArchiveOrgResponseException('Archive.org did not return valid JSON.');
        }

        /** @var array<string, mixed> $decoded */
        return $decoded;
    }
}
