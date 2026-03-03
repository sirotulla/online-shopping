<?php
declare(strict_types=1);

function generate_payment_id(): string {
    // Example: EBG- + 12 hex chars (non-guessable)
    return 'EBG-' . strtoupper(bin2hex(random_bytes(6)));
}
