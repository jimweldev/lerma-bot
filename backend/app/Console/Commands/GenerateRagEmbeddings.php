<?php

namespace App\Console\Commands;

use App\Models\Rag\RagFileChunk;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Console\Command;

class GenerateRagEmbeddings extends Command {
    protected $signature = 'rag:embed';

    protected $description = 'Generate embeddings for all RAG chunks';

    public function handle() {
        $chunks = RagFileChunk::whereNull('embedding')->get();

        $this->info("Found {$chunks->count()} chunks to embed...");

        foreach ($chunks as $chunk) {
            try {
                $embedResponse = Gemini::embeddingModel('text-embedding-004')
                    ->embedContent($chunk->content);

                $chunk->embedding = $embedResponse->embedding->values ?? [];
                $chunk->save();

                $this->info("Embedded chunk {$chunk->id}");
            } catch (\Exception $e) {
                $this->error("Failed chunk {$chunk->id}: ".$e->getMessage());
            }
        }

        $this->info('Done generating embeddings!');
    }
}
