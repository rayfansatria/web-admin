<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Institution;
use App\Models\InstitutionSetting;
use App\Models\AppBuild;
use App\Services\AppConfigGenerator;
use App\Jobs\TriggerAppBuildJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class InstitutionController extends Controller
{
    // Halaman daftar (Blade)
    public function index()
    {
        return view('admin.institutions');
    }

    // Halaman create form
    public function create()
    {
        // show empty form
        $institution = new Institution();
        return view('admin.institutions.form', ['institution' => $institution, 'mode' => 'create']);
    }

    // Store new institution
    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:50|unique:institutions,code',
            'name' => 'required|string|max:255',
            'timezone' => 'nullable|string|max:100',
            'logo_url' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active') ? (bool)$request->input('is_active') : true;

        $inst = Institution::create($data);

        // redirect to configure page for further setup
        return redirect()->route('admin.institutions.configure', ['id' => $inst->id]);
    }

    // Halaman edit form (reuse same form view)
    public function edit($id)
    {
        $institution = Institution::findOrFail($id);
        return view('admin.institutions.form', ['institution' => $institution, 'mode' => 'edit']);
    }

    // Update existing institution
    public function update(Request $request, $id)
    {
        $inst = Institution::findOrFail($id);

        $data = $request->validate([
            'code' => 'required|string|max:50|unique:institutions,code,' . $inst->id,
            'name' => 'required|string|max:255',
            'timezone' => 'nullable|string|max:100',
            'logo_url' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active') ? (bool)$request->input('is_active') : false;

        $inst->update($data);

        return redirect()->route('admin.institutions.configure', ['id' => $inst->id]);
    }

    // Halaman configure (Blade)
    public function show($id)
    {
        $inst = Institution::findOrFail($id);
        return view('admin.institution_show', compact('inst'));
    }

    // Halaman feature toggles (Blade)
    public function showFeatures($id)
    {
        $inst = Institution::findOrFail($id);
        return view('admin.institutions.features', ['institutionId' => $id]);
    }

    // API: detail + settings (JSON)
    public function showApi($id)
    {
        $inst = Institution::findOrFail($id);
        $settings = InstitutionSetting::where('institution_id', $id)
            ->get()
            ->mapWithKeys(function ($item) {
                $val = json_decode($item->value, true);
                return [$item->key => $val === null ? $item->value : $val];
            });

        return response()->json([
            'institution' => $inst,
            'settings' => $settings,
            'features' => $inst->features(),
        ]);
    }

    // API: update settings
    public function updateSettings(Request $request, $id)
    {
        $payload = $request->input('settings', []);

        DB::transaction(function () use ($id, $payload) {
            foreach ($payload as $key => $value) {
                $type = is_array($value) ? 'json'
                    : (is_bool($value) ? 'boolean'
                    : (is_int($value) ? 'integer' : 'string'));

                InstitutionSetting::updateOrCreate(
                    ['institution_id' => $id, 'key' => $key],
                    [
                        'value' => $type === 'json' ? json_encode($value) : (string) $value,
                        'value_type' => $type,
                    ]
                );
            }
        });

        return response()->json(['ok' => true]);
    }

    // API: trigger build/generate APK
    public function generateApp(Request $request, $id)
    {
        $platform = $request->input('platform', 'android');

        $generator = new AppConfigGenerator();
        $config = $generator->generateForInstitution($id);

        $build = AppBuild::create([
            'institution_id' => $id,
            'config_snapshot_json' => $config,
            'status' => 'pending',
            'platform' => $platform,
        ]);

        TriggerAppBuildJob::dispatch($build->id);

        return response()->json(['ok' => true, 'build_id' => $build->id]);
    }

    // API: cek status build
    public function buildStatus($buildId)
    {
        $b = AppBuild::findOrFail($buildId);
        return response()->json($b);
    }

    // API: list app builds (filterable by institution_id)
    public function listBuilds(Request $request)
    {
        $query = AppBuild::query();

        if ($request->has('institution_id')) {
            $query->where('institution_id', $request->input('institution_id'));
        }

        $perPage = $request->input('per_page', 15);
        
        return response()->json($query->orderBy('created_at', 'desc')->paginate($perPage));
    }


    public function featuresPage($id)
    {
        $inst = Institution::findOrFail($id);
        return view('admin.institutions.features', ['institution' => $inst]);
    }

}