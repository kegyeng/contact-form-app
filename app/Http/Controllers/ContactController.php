<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContactController extends Controller
{
    public function index(): View
    {
        $categories = Category::orderBy('id')->get();
        $tags = Tag::orderBy('id')->get();

        return view('contact.index', compact('categories', 'tags'));
    }

    public function confirm(StoreContactRequest $request): View
    {
        $validated = $request->validated();
        $request->session()->flashInput(
            array_merge(
                $validated,
                $request->only(['tel1', 'tel2', 'tel3'])
            )
        );
        $category = Category::findOrFail($validated['category_id']);
        $tags = Tag::whereIn('id', $validated['tag_ids'] ?? [])
            ->orderBy('id')
            ->get();

        return view('contact.confirm', compact('validated', 'category', 'tags'));
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $tagIds = $validated['tag_ids'] ?? [];
        unset($validated['tag_ids']);
        DB::transaction(function () use ($validated, $tagIds) {
            $contact = Contact::create($validated);
            $contact->tags()->attach($tagIds);
        });
        $request->session()->forget('_old_input');

        return redirect()->route('contacts.thanks');
    }
}
