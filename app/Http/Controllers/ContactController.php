<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreContactRequest;
use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag; // 配列操作用(@store)
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
        $request->flash(); // "store"されるまで入力値を保持
        $category = Category::find($validated['category_id']);
        $tags = Tag::findMany($validated['tag_ids'] ?? []); // $tag_idsが設定されていなければnull

        return view('contact.confirm', compact(['validated', 'category', 'tags']));
    }

    public function store(StoreContactRequest $request)
    {
        $validated = $request->validated();
        $contact = Contact::create(Arr::except($validated, ['tag_ids'])); // $tag_ids以外の項目を登録

        // $tag_idsが空じゃないなら、中間テーブルに外部キーの挿入
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
