<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\IndexContactRequest;
use App\Http\Requests\Api\V1\StoreContactRequest;
use App\Http\Requests\Api\V1\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Models\Contact;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ContactController extends Controller
{
    public function index(IndexContactRequest $request): AnonymousResourceCollection
    {
        $perPage = $request->input('per_page', 20);

        $contacts = Contact::with(['category', 'tags'])
            ->search($request)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($perPage);

        return ContactResource::collection($contacts);
    }

    public function store(StoreContactRequest $request): ContactResource
    {
        $validated = $request->validated();

        $tagIds = $validated['tag_ids'] ?? [];

        $contact = Contact::create($validated);

        if (! empty($tagIds)) {
            $contact->tags()->attach($tagIds);
        }

        return (new ContactResource($contact->load(['category', 'tags'])))
            ->additional(['message' => 'お問い合わせを作成しました']);
    }

    public function show(Contact $contact): ContactResource
    {
        $contact->load(['category', 'tags']);

        return new ContactResource($contact);
    }

    public function update(UpdateContactRequest $request, Contact $contact): ContactResource
    {
        $validated = $request->validated();

        $contact->update($validated);

        if ($request->has('tag_ids')) {
            $tagIds = $validated['tag_ids'] ?? [];
            $contact->tags()->sync($tagIds);
        }

        return (new ContactResource($contact->load(['category', 'tags'])))
            ->additional(['message' => 'お問い合わせを更新しました']);
    }

    public function destroy(Contact $contact): JsonResponse
    {
        $contact->delete();

        return response()->json(null, 204);
    }
}
