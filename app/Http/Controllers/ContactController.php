<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Contact;
use App\Models\Tag;
use App\Http\Requests\StoreContactRequest;
use App\Http\Requests\ExportContactRequest;
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

        return redirect('/thanks');
    }

    public function thanks()
    {
        return view('contact.thanks');
    }

    public function export(ExportContactRequest $request)
    {
        $contacts = Contact::with(['category', 'tags'])
            ->search($request)
            ->get();
    
        $csvHeader = [
            'ID','氏名','性別','メール','電話','住所','建物','カテゴリ','内容','作成日時'
        ];
        $temps = [];
        array_push($temps, $csvHeader);

        foreach ($contacts as $contact) {
            $temp = [
                $contact->id,
                $contact->first_name.' '.$contact->last_name,
                $contact->gender_label,
                $contact->email,
                $contact->tel,
                $contact->address,
                $contact->building,
                $contact->category->content,
                $contact->detail,
                $contact->created_at,
            ];
            array_push($temps, $temp);
        }

        $filename = 'contacts_' . now()->format('YmdHis') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($temps) {
            $stream = fopen('php://output', 'w');

            fputs($stream, "\xEF\xBB\xBF");

            foreach ($temps as $row) {
                fputcsv($stream, $row);
            }

            fclose($stream);
        };

        return response()->stream($callback, 200, $headers);
    }
}
