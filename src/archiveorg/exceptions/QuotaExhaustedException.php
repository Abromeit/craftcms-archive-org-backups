<?php

declare(strict_types=1);

namespace abromeit\archiveorgbackups\archiveorg\exceptions;

final class QuotaExhaustedException extends ArchiveOrgException
{
    public function __construct(
        public readonly ?int $observedLimit,
        string $message = 'Archive.org daily quota exhausted.'
    ) {
        parent::__construct($message);
    }
}
