<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Support\AnnouncementImageStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');

        $sortColumns = [
            'title' => 'title',
            'published_at' => 'published_at',
            'status' => 'is_active',
            'dibuat' => 'created_at',
        ];

        $sort = (string) $request->query('sort', 'dibuat');
        if (! array_key_exists($sort, $sortColumns)) {
            $sort = 'dibuat';
        }
        $dir = $request->query('dir', 'desc') === 'asc' ? 'asc' : 'desc';

        $query = Announcement::query()
            ->with('creator:id,name')
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when(in_array($status, ['active', 'inactive'], true), function ($query) use ($status) {
                $query->where('is_active', $status === 'active');
            })
            ->orderBy($sortColumns[$sort], $dir)
            ->orderByDesc('id');

        $paginator = $query->paginate(20)->withQueryString();

        return Inertia::render('Admin/Announcement/Index', [
            'announcements' => $paginator->getCollection()->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'excerpt' => $a->excerpt,
                'image_url' => $a->image_url,
                'is_active' => $a->is_active,
                'status' => $a->is_active ? 'Aktif' : 'Nonaktif',
                'published_at' => $a->published_at?->format('d M Y H:i'),
                'created_at' => $a->created_at?->format('d M Y H:i'),
                'creator_name' => $a->creator?->name ?? '-',
            ]),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'search' => $search,
            'status' => $status,
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Announcement/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = AnnouncementImageStorage::store($request->file('image'), $validated['title'] ?? null);
        }

        Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'image_path' => $imagePath,
            'is_active' => $request->boolean('is_active', true),
            'published_at' => $validated['published_at'] ?? null,
            'created_by' => $request->user()?->id,
        ]);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil ditambahkan.');
    }

    public function edit(Announcement $announcement): Response
    {
        return Inertia::render('Admin/Announcement/Edit', [
            'announcement' => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'image_path' => $announcement->image_path,
                'image_url' => $announcement->image_url,
                'is_active' => $announcement->is_active,
                'published_at' => $announcement->published_at?->format('Y-m-d\TH:i'),
            ],
        ]);
    }

    public function update(Request $request, Announcement $announcement): RedirectResponse
    {
        $validated = $this->validatePayload($request);

        $oldImage = $announcement->image_path;
        $newImage = $oldImage;
        $removeImage = $request->boolean('remove_image');

        if ($request->hasFile('image')) {
            $newImage = AnnouncementImageStorage::store($request->file('image'), $validated['title'] ?? null);
        } elseif ($removeImage) {
            $newImage = null;
        }

        $announcement->update([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'image_path' => $newImage,
            'is_active' => $request->boolean('is_active'),
            'published_at' => $validated['published_at'] ?? null,
        ]);

        if ($oldImage !== $newImage) {
            AnnouncementImageStorage::delete($oldImage);
        }

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil diperbarui.');
    }

    public function destroy(Announcement $announcement): RedirectResponse
    {
        $imagePath = $announcement->image_path;
        $announcement->delete();
        AnnouncementImageStorage::delete($imagePath);

        return redirect()
            ->route('admin.announcements.index')
            ->with('success', 'Pengumuman berhasil dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ]);
    }
}
