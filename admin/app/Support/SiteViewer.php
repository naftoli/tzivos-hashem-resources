<?php

namespace App\Support;

/** The signed-in person the public site asked about. Email and level only. */
class SiteViewer
{
    public function __construct(
        public string $email,
        public string $role,
        public ?int $userId = null,
        public ?string $mashpiaRole = null,
    ) {}
}
