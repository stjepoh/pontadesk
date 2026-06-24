<?php
declare(strict_types=1);

namespace App\Models;

final class Client
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $contactPerson = null,
        public ?string $email = null,
        public ?string $phone = null,
        public string $category = 'regular',
        public string $salesStage = 'lead',
        public ?string $industry = null,
        public ?string $hourlyRate = null,
        public bool $trackWork = true,
        public ?string $notes = null,
    ) {
    }
}
