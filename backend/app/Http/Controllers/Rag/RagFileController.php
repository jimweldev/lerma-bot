<?php

namespace App\Http\Controllers\Rag;

use App\Helpers\DynamicLogger;
use App\Helpers\QueryHelper;
use App\Helpers\StorageHelper;
use App\Http\Controllers\Controller;
use App\Models\Rag\RagFile;
use App\Services\RagFileChunkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use Smalot\PdfParser\Parser;

class RagFileController extends Controller {
    private $logger;

    public function __construct() {
        $this->logger = DynamicLogger::create('laravel.log', 'local');
    }

    public function index(Request $request) {
        $queryParams = $request->all();

        try {
            $query = RagFile::with('creator');
            $type = 'paginate';
            QueryHelper::apply($query, $queryParams, $type);

            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where(function ($query) use ($search) {
                    $query->where('id', 'LIKE', '%'.$search.'%');
                });
            }

            $totalRecords = $query->count();
            $limit = $request->input('limit', 10);
            $page = $request->input('page', 1);
            QueryHelper::applyLimitAndOffset($query, $limit, $page);

            $records = $query->get();

            return response()->json([
                'records' => $records,
                'meta' => [
                    'total_records' => $totalRecords,
                    'total_pages' => ceil($totalRecords / $limit),
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function show($id) {
        $record = RagFile::where('id', $id)->first();

        if (!$record) {
            return response()->json([
                'message' => 'Record not found.',
            ], 404);
        }

        return response()->json($record, 200);
    }

    public function store(Request $request) {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        $authUser = auth()->user();

        try {
            $file = $request->file('file');

            if (!$file) {
                return response()->json([
                    'message' => 'No file uploaded.',
                ], 400);
            }

            // Get extension
            $extension = strtolower($file->getClientOriginalExtension());

            // Upload
            $filePath = StorageHelper::uploadFileAs($file, 'rag_files', $extension);

            if (!$filePath) {
                return response()->json([
                    'message' => "Failed to upload {$file->getClientOriginalName()}.",
                ], 400);
            }

            // Save record
            $request->merge([
                'file_path' => $filePath,
                'created_by' => $authUser->id,
            ]);

            $record = RagFile::create($request->all());

            $fullPath = Storage::disk('public')->path($filePath);
            $content = '';

            /*
            |--------------------------------------------------------------------------
            | TEXT FILE
            |--------------------------------------------------------------------------
            */
            if ($extension === 'txt') {
                $content = file_get_contents($fullPath);
            }

            /*
            |--------------------------------------------------------------------------
            | PDF FILE
            |--------------------------------------------------------------------------
            */
            else if ($extension === 'pdf') {
                // Try normal extraction first
                $parser = new Parser;
                $pdf = $parser->parseFile($fullPath);
                $content = $pdf->getText();

                // If empty → use OCR fallback
                if (empty(trim($content))) {
                    $tempDir = storage_path('app/temp_ocr_'.uniqid());
                    if (!file_exists($tempDir)) {
                        mkdir($tempDir, 0777, true);
                    }

                    $imagePrefix = $tempDir.'/page';

                    // Convert PDF to PNG images
                    exec("pdftoppm -png \"$fullPath\" \"$imagePrefix\"");

                    $images = glob($tempDir.'/page*.png');

                    foreach ($images as $image) {
                        $outputBase = $image; // Tesseract output base name

                        exec("tesseract \"$image\" \"$outputBase\" -l eng");

                        $textFile = $outputBase.'.txt';

                        if (file_exists($textFile)) {
                            $content .= file_get_contents($textFile)."\n";
                            unlink($textFile);
                        }

                        unlink($image);
                    }

                    rmdir($tempDir);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | DOCX FILE
            |--------------------------------------------------------------------------
            */
            else if ($extension === 'docx') {
                $phpWord = IOFactory::load($fullPath);

                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                            $content .= $element->getText()."\n";
                        } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                            foreach ($element->getElements() as $child) {
                                if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                                    $content .= $child->getText();
                                }
                            }
                            $content .= "\n";
                        }
                    }
                }
            }

            /*
            |--------------------------------------------------------------------------
            | FALLBACK
            |--------------------------------------------------------------------------
            */
            else {
                $content = file_get_contents($fullPath);
            }

            // Normalize whitespace
            $content = trim(preg_replace('/\s+/', ' ', $content));

            if (empty($content)) {
                return response()->json([
                    'message' => 'File uploaded but no readable content found.',
                ], 400);
            }

            /*
            |--------------------------------------------------------------------------
            | CREATE CHUNKS
            |--------------------------------------------------------------------------
            */
            $chunkCount = RagFileChunkService::createChunks($record->id, $content);

            /*
            |--------------------------------------------------------------------------
            | EMBEDDING
            |--------------------------------------------------------------------------
            */
            Artisan::call('rag:embed');

            return response()->json([
                'record' => $record,
                'chunks_created' => $chunkCount,
            ], 201);
        } catch (\Exception $e) {
            $this->logger->error('RagFile Store Error: '.$e->getMessage());

            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function update(Request $request, $id) {
        try {
            $record = RagFile::find($id);

            if (!$record) {
                return response()->json([
                    'message' => 'Record not found.',
                ], 404);
            }

            $record->update($request->all());

            return response()->json($record, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    public function destroy($id) {
        try {
            $record = RagFile::find($id);

            if (!$record) {
                return response()->json([
                    'message' => 'Record not found.',
                ], 404);
            }

            $record->delete();

            return response()->json($record, 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred',
                'error' => $e->getMessage(),
            ], 400);
        }
    }
}
