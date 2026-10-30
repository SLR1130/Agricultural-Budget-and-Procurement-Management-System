<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit() { return view('profile'); }

    public function update(Request $request)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'phone' => ['nullable', 'string', 'max:40'], 'password' => ['nullable', 'string', 'min:8', 'confirmed']]);
        if (blank($data['password'] ?? null)) unset($data['password']); else $data['password'] = Hash::make($data['password']);
        $request->user()->update($data);
        return back()->with('success', 'Profile updated.');
    }
}
