<?php
namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppBuild;
use Illuminate\Http\Request;

class BuildCallbackController extends Controller
{
    public function callback(Request $request, $buildId)
    {
        // validate a shared secret in header x-build-secret for security
        $secret = $request->header('x-build-secret');
        if (!$secret || $secret !== config('services.ci.build_secret')) {
            return response()->json(['error'=>'unauthorized'], 401);
        }

        $payload = $request->validate([
            'status'=>'required|string',
            'artifact_url'=>'nullable|url',
            'build_log'=>'nullable|string',
        ]);

        $build = AppBuild::findOrFail($buildId);
        $build->status = $payload['status'];
        if (!empty($payload['artifact_url'])) $build->artifact_url = $payload['artifact_url'];
        if (!empty($payload['build_log'])) $build->build_log = $payload['build_log'];
        $build->finished_at = now();
        $build->save();

        return response()->json(['ok'=>true]);
    }
}