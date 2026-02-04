<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class GeminiAIService {
    protected static $apiKey;

    protected static $baseUrl;

    public static function init() {
        self::$apiKey = env('GEMINI_API_KEY');
        self::$baseUrl = env('GEMINI_API_URL', 'https://api.gemini.ai/v1');
    }

    /**
     * Create embedding for text
     */
    public static function createEmbedding(string $text): array {
        self::init();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$apiKey,
            'Content-Type' => 'application/json',
        ])->post(self::$baseUrl.'/embeddings', [
            'model' => 'gemini-text-embedding-001',
            'input' => $text,
        ]);

        $data = $response->json();

        // Assuming Gemini returns embedding in $data['embedding']
        return $data['embedding'] ?? [];
    }

    /**
     * Generate answer using context
     */
    public static function generateAnswer(string $question, string $context): string {
        self::init();

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.self::$apiKey,
            'Content-Type' => 'application/json',
        ])->post(self::$baseUrl.'/completions', [
            'model' => 'gemini-text-001',
            'prompt' => "Answer the question based on the context below:\n\nContext:\n$context\n\nQuestion:\n$question\n\nAnswer:",
            'max_tokens' => 500,
        ]);

        $data = $response->json();

        // Assuming Gemini returns text in $data['choices'][0]['text']
        return $data['choices'][0]['text'] ?? 'No answer';
    }
}
