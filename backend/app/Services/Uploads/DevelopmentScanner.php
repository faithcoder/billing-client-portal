<?php

namespace App\Services\Uploads;

use App\Contracts\MalwareScanner;
use Illuminate\Validation\ValidationException;

class DevelopmentScanner implements MalwareScanner
{
    public function scan(string $path): void
    {
        abort_unless(app()->environment(['local', 'testing']) && config('portal.scanner') === 'fake', 503);
        $data = file_get_contents($path);
        // Defense in depth only; explicitly NOT a production malware scanner.
        if (preg_match('/<\?php|<script|\/JavaScript|\/JS\b|\/Launch|\/EmbeddedFile|\/OpenAction|EICAR-STANDARD-ANTIVIRUS/i', $data)) {
            throw ValidationException::withMessages(['file' => ['Unsafe file content.']]);
        }
    }
}
