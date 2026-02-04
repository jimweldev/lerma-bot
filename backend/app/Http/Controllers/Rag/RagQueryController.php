<?php

namespace App\Http\Controllers\Rag;

use App\Http\Controllers\Controller;
use App\Models\Rag\RagFileChunk;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Http\Request;

class RagQueryController extends Controller {
    public function query(Request $request) {
        $request->validate([
            'question' => 'required|string',
        ]);

        $question = $request->input('question');

        try {
            // 1️⃣ Create embeddings for the question
            $embedResponse = Gemini::embeddingModel('text-embedding-004')
                ->embedContent($question);

            $queryEmbedding = $embedResponse->embedding->values ?? [];

            // 2️⃣ Fetch chunks with embeddings and compute similarity
            $chunks = RagFileChunk::whereNotNull('embedding')->get()->map(function ($chunk) use ($queryEmbedding) {
                $vec = json_decode($chunk->embedding, true);
                $chunk->similarity = $this->cosineSimilarity($vec, $queryEmbedding);

                return $chunk;
            });

            $topChunks = $chunks->sortByDesc('similarity')->take(5);
            $context = $topChunks->pluck('content')->implode("\n---\n");

            // 3️⃣ Use Gemini to generate an answer with context
            $response = Gemini::generativeModel(model: 'gemini-2.5-flash-lite')
                ->generateContent([
                    "Answer the question using the context:\n$context\n\nQuestion: $question\nAnswer:",
                ]);

            $answer = $response->text();

            return response()->json([
                'question' => $question,
                'answer' => $answer,
                'chunks_used' => $topChunks->pluck('id'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process RAG query',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function cosineSimilarity(array $a, array $b): float {
        $dot = 0;
        $normA = 0;
        $normB = 0;
        $len = min(count($a), count($b));

        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $normA += $a[$i] ** 2;
            $normB += $b[$i] ** 2;
        }

        if ($normA == 0 || $normB == 0) {
            return 0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
