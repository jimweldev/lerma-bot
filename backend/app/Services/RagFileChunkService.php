<?php

namespace App\Services;

use App\Models\Rag\RagFileChunk;

class RagFileChunkService {
    /**
     * Split file content into chunks and save to DB
     *
     * @return int Number of chunks created
     */
    public static function createChunks(int $ragFileId, string $content, int $chunkSizeChars = 1000) {
        $chunks = str_split($content, $chunkSizeChars);
        $chunkCount = 0;

        foreach ($chunks as $index => $chunkText) {
            RagFileChunk::create([
                'rag_file_id' => $ragFileId,
                'content' => $chunkText,
                'chunk_index' => $index,
                'token_count' => strlen($chunkText), // temporary, can use tokenizer for Gemini AI later
                'metadata' => json_encode(['chunk_size' => strlen($chunkText)]),
            ]);

            $chunkCount++;
        }

        return $chunkCount;
    }
}
