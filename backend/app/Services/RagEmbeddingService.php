<?php

namespace App\Services;

use App\Models\Rag\RagFileChunk;

class RagEmbeddingService {
    /**
     * Generate embeddings for chunks and save them
     */
    public static function embedChunks() {
        $chunks = RagFileChunk::whereNull('embedding')->get(); // chunks without embedding

        foreach ($chunks as $chunk) {
            // Use Gemini AI SDK to generate embeddings
            // This is pseudocode; replace with actual Gemini embedding call
            $embedding = GeminiAI::createEmbedding($chunk->content);

            $chunk->embedding = json_encode($embedding); // save as JSON
            $chunk->save();
        }

        return count($chunks);
    }
}
