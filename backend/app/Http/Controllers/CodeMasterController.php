<?php

namespace App\Http\Controllers;

use App\Models\Analysis;
use App\Services\OllamaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;

class CodeMasterController extends Controller
{
    public function status(OllamaService $ollama)
    {
        $history = false;
        try {
            DB::connection()->getPdo();
            $history = config('codemaster.history_enabled') && Schema::hasTable('analyses');
        } catch (\Throwable $e) {
        }

        return response()->json(['ollama' => $ollama->status(), 'history' => $history]);
    }

    public function analyze(Request $request, OllamaService $ollama)
    {
        $data = $request->validate([
            'language' => ['required', 'string', 'max:30'],
            'code' => ['required', 'string', 'max:' . config('codemaster.max_code_chars')],
            'error_message' => ['nullable', 'string', 'max:2000'],
        ]);

        set_time_limit(0);
        $start = microtime(true);

        try {
            $result = $ollama->diagnose($data['language'], $data['code'], $data['error_message'] ?? null);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $ms = (int) ((microtime(true) - $start) * 1000);
        $id = null;

        if (config('codemaster.history_enabled')) {
            try {
                $id = Analysis::create($data + ['result' => $result, 'duration_ms' => $ms])->id;
            } catch (\Throwable $e) {
                report($e); // 資料庫沒開也不影響診斷結果
            }
        }

        return response()->json(['id' => $id, 'result' => $result, 'duration_ms' => $ms]);
    }

    public function history()
    {
        return Analysis::latest()->limit(20)->get()->map(fn ($a) => [
            'id' => $a->id,
            'language' => $a->language,
            'preview' => Str::limit(trim(Str::before($a->code, "\n")), 48),
            'created_at' => $a->created_at->format('m/d H:i'),
        ]);
    }

    public function show(Analysis $analysis)
    {
        return $analysis;
    }

    public function destroy(Analysis $analysis)
    {
        $analysis->delete();

        return response()->noContent();
    }
}
