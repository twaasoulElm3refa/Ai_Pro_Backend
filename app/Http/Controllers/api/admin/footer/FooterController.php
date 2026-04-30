<?php

namespace App\Http\Controllers\api\admin\footer;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Footer;
use Illuminate\Http\Request;

class FooterController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $footer = Footer::find(1);

        return $this->success($footer, 'Footer Fetched Successfully');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'facebook' => ['nullable', 'url', 'max:255'],
            'twitter' => ['nullable', 'url', 'max:255'],
            'instagram' => ['nullable', 'url', 'max:255'],
            'linkedin' => ['nullable', 'url', 'max:255'],
            'youtube' => ['nullable', 'url', 'max:255'],
            'google_play' => ['nullable', 'url', 'max:255'],
            'app_store' => ['nullable', 'url', 'max:255'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $request->file('logo')->store('footer', 'public');
        }
        $footer = Footer::find(1);
        $footer->update($data);

        return $this->success($footer, 'Footer Updated Successfully');
    }
}
