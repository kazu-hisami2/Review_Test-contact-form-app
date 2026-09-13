<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Support\Arr;

class ContactController extends Controller
{
    public function index()
    {
        $categories = Category::all();
        $tags = Tag::all();

        return view('contact.index', compact(['categories', 'tags']));
    }

    public function confirm(StoreContactRequest $request)
    {
        $validated = $request->validated();
        $request->flash();
        $category = Category::find($validated['category_id']);
        $tags = Tag::findMany($validated['tag_ids'] ?? []);

        return view('contact.confirm', compact(['validated', 'category', 'tags']));
    }

    public function store(StoreContactRequest $request)
    {
        $validated = $request->validated();
        $contact = Contact::create(Arr::except($validated, ['tag_ids']));

        if (! empty($validated['tag_ids'])) {
            $contact->tags()->sync($validated['tag_ids']);
        }

        return redirect()->route('contacts.thanks');
    }

    public function thanks()
    {
        return view('contact.thanks');
    }
}
