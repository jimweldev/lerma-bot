<?php

namespace App\Jobs;

use App\Models\Rag\RagFile;
use App\Services\RagFileProcessingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessRagFile implements ShouldQueue {
    use Queueable;

    public function __construct(
        public RagFile $ragFile,
    ) {}

    public function handle(RagFileProcessingService $service): void {
        $service->process($this->ragFile);
    }
}
