<?php

namespace App\Http\Controllers\api\admin\contact;

use App\Http\Controllers\concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\ContactUs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactController extends Controller
{
    use ApiResponse;

    /**
     * عرض كل رسائل التواصل
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search'   => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 10;
        $search  = $validated['search'] ?? null;

        $contacts = ContactUs::query()
            ->when($search, function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('subject', 'like', "%{$search}%")
                      ->orWhere('message', 'like', "%{$search}%");
                });
            })
            ->with(['user:id,name,email'])
            ->latest()
            ->paginate($perPage);

        return $this->success($contacts, 'Contacts fetched successfully.');
    }

    /**
     * عرض رسالة واحدة
     */
    public function show(int $id): JsonResponse
    {
        $contact = ContactUs::with(['user:id,name,email'])->find($id);

        if (!$contact) {
            return $this->error('Contact not found.', 404);
        }

        return $this->success($contact, 'Contact fetched successfully.');
    }

    /**
     * إنشاء رسالة تواصل جديدة
     * ملاحظة: اسم الميثود عندك update لكن وظيفتها هنا create/store
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'     => ['nullable', 'string', 'max:255'],
            'email'    => ['nullable', 'email:rfc,dns', 'max:255'],
            'subject'  => ['nullable', 'string', 'max:1000'],
            'message'  => ['required', 'string', 'max:10000'],
        ]);

        $contact = ContactUs::create([
            'user_id' => $validated['user_id'] ?? null,
            'name'    => $validated['name'] ?? null,
            'email'   => $validated['email'] ?? null,
            'subject' => $validated['subject'] ?? null,
            'message' => $validated['message'],
        ]);

        $contact->load(['user:id,name,email']);

        return $this->success($contact, 'Contact created successfully.', 201);
    }

    /**
     * حذف رسالة تواصل
     */
    public function destroy(int $id): JsonResponse
    {
        $contact = ContactUs::find($id);

        if (!$contact) {
            return $this->error('Contact not found.');
        }

        $contact->delete();

        return $this->success(null, 'Contact deleted successfully.');
    }
}
