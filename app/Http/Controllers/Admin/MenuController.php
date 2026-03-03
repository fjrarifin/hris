<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Menu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MenuController extends Controller
{

    public function index()
    {
        $level = Auth::user()->level;

        $menus = Menu::whereNull('parent_id')
            ->where('is_active', 1)
            ->where(function ($q) use ($level) {
                $q->whereNull('allowed_levels')
                    ->orWhereRaw('FIND_IN_SET(?, allowed_levels)', [$level]);
            })
            ->with(['children' => function ($q) use ($level) {
                $q->where('is_active', 1)
                    ->where(function ($q2) use ($level) {
                        $q2->whereNull('allowed_levels')
                            ->orWhereRaw('FIND_IN_SET(?, allowed_levels)', [$level]);
                    });
            }])
            ->orderBy('order')
            ->get();

        return view('admin.menus.index', compact('menus'));
    }

    public function create()
    {
        $parents = Menu::whereNull('parent_id')->get();
        return view('admin.menus.create', compact('parents'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'           => 'required',
            'route'          => 'nullable',
            'icon'           => 'nullable',
            'parent_id'      => 'nullable',
            'permission_key' => 'nullable',
            'order'          => 'nullable|integer',
        ]);

        Menu::create($data);

        return redirect()->route('admin.menus.index')
            ->with('success', 'Menu berhasil ditambahkan');
    }
}