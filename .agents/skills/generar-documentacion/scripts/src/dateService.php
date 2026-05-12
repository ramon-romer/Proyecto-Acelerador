<?php
declare(strict_types=1);

namespace GenerarDocumentacion\DateService;

use DateTimeImmutable;
use DateTimeZone;

function getTodayDate(string $timezone = 'Europe/Madrid'): string
{
    $now = new DateTimeImmutable('now', new DateTimeZone($timezone));
    return $now->format('Y-m-d');
}

