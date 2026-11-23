<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MashpiaRoleMap;
use App\Services\MashpiaRoleMapper;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MashpiaRoleMapController extends Controller
{
    public function index(MashpiaRoleMapper $mapper)
    {
        return view('admin.mashpia-roles', [
            'maps' => MashpiaRoleMap::orderBy('id')->get(),
            'levels' => MashpiaRoleMapper::assignableLevels(),
        ]);
    }

    public function store(Request $request, MashpiaRoleMapper $mapper)
    {
        $data = $this->validated($request, null);
        MashpiaRoleMap::create($data);

        return redirect()->route('admin.mashpia-roles')
            ->with('status', "Added {$data['mashpia_role']} → " . \App\Models\User::roleLabel($data['admin_role']) . '.');
    }

    public function update(Request $request, MashpiaRoleMap $map, MashpiaRoleMapper $mapper)
    {
        $data = $this->validated($request, $map->id);
        $map->update($data);

        return redirect()->route('admin.mashpia-roles')
            ->with('status', "Saved {$map->mashpia_role} → " . \App\Models\User::roleLabel($map->admin_role) . '.');
    }

    public function destroy(MashpiaRoleMap $map)
    {
        $name = $map->mashpia_role;
        $map->delete();

        return redirect()->route('admin.mashpia-roles')->with('status', "Removed {$name} from the list.");
    }

    protected function validated(Request $request, ?int $ignoreId): array
    {
        $data = $request->validate([
            'mashpia_role' => ['required', 'string', 'max:80'],
            'admin_role' => ['required', Rule::in(array_keys(MashpiaRoleMapper::assignableLevels()))],
            'can_request_admin' => ['sometimes', 'boolean'],
        ]);
        $data['mashpia_role'] = trim(preg_replace('/\s+/u', ' ', $data['mashpia_role']));
        $data['can_request_admin'] = $request->boolean('can_request_admin');

        if ($data['admin_role'] === 'hq_admin') {
            throw ValidationException::withMessages([
                'admin_role' => 'No Mashpia role can be set to HQ admin. People with an HQ role on Mashpia become HQ staff and can ask for HQ admin.',
            ]);
        }

        $key = MashpiaRoleMapper::normalize($data['mashpia_role']);
        $clash = MashpiaRoleMap::query()->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->get()
            ->first(fn ($row) => MashpiaRoleMapper::normalize($row->mashpia_role) === $key);
        if ($clash) {
            throw ValidationException::withMessages([
                'mashpia_role' => "{$clash->mashpia_role} is already on the list (names are matched without worrying about capital letters or extra spaces).",
            ]);
        }

        return $data;
    }
}
