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
        set_time_limit(0);

        $authUser = auth()->user();

        try {
            $file = $request->file('file');
            if (!$file) {
                return response()->json([
                    'message' => 'No file uploaded.',
                ], 400);
            }

            // Get file extension
            $extension = strtolower($file->getClientOriginalExtension());

            // Upload file
            $filePath = StorageHelper::uploadFileAs($file, 'rag_files', $extension);
            if (!$filePath) {
                return response()->json([
                    'message' => "Failed to upload {$file->getClientOriginalName()}. File size too large.",
                ], 400);
            }

            // Create RagFile record
            $request->merge([
                'file_path' => $filePath,
                'created_by' => $authUser->id,
            ]);
            $record = RagFile::create($request->all());

            // Get full file path on disk
            $fullPath = Storage::disk('public')->path($filePath);
            $content = '';

            // Extract file content based on type
            if ($extension === 'txt') {
                $content = file_get_contents($fullPath);
            } elseif ($extension === 'pdf') {
                $parser = new Parser;
                $pdf = $parser->parseFile($fullPath);
                $content = $pdf->getText();
            } elseif ($extension === 'docx') {
                $phpWord = IOFactory::load($fullPath);
                foreach ($phpWord->getSections() as $section) {
                    foreach ($section->getElements() as $element) {
                        if (method_exists($element, 'getText')) {
                            $content .= $element->getText()."\n";
                        }
                    }
                }
            } else {
                // Fallback for other types
                $content = file_get_contents($fullPath);
            }

            // Split content into chunks and save
            $chunkCount = RagFileChunkService::createChunks($record->id, $content);

            // Automatically run the artisan command (for all unembedded chunks)
            \Artisan::call('rag:embed');

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
