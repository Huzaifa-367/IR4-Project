<?php

namespace App\Console\Commands;

use App\Services\Backup\ExportAllService;
use Illuminate\Console\Command;

final class ExportAllCommand extends Command
{
    protected $signature = 'ir4:export-all
                            {--key= : Client encryption key (base64/passphrase); generated if omitted}';

    protected $description = 'Produce the encrypted end-of-project handover archive (DOC-19)';

    public function handle(ExportAllService $exports): int
    {
        $result = $exports->run($this->option('key') ?: null);

        $this->info("Export written: {$result['archive_path']} ({$result['bytes']} bytes)");
        $this->warn('Client encryption key (store securely, shown once):');
        $this->line($result['key']);
        $this->line("Export id: {$result['export_id']}");

        return self::SUCCESS;
    }
}
