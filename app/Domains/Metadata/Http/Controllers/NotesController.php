<?php

namespace App\Domains\Metadata\Http\Controllers;

use App\Domains\Metadata\Http\Requests\NotesRequest;
use App\Domains\Metadata\Http\Resources\NoteResource;
use App\Domains\Metadata\Models\Note;
use App\Platform\Http\Controller;
use Illuminate\Http\Request;

/**
 * The company's library of reusable note templates.
 *
 * Reading answers to one ability and writing to another, which is why the
 * gates below are named rather than resolved from the model: a member may be
 * allowed to pick a note for a document without being allowed to edit the
 * library it came from.
 */
class NotesController extends Controller
{
    /**
     * A page of the company's notes, newest first, ten to a page unless the
     * caller asks for a different size. The `type` and `search` narrowings are
     * read off the raw input by the filter scope.
     */
    public function index(Request $request)
    {
        $this->authorize('view notes');

        $perPage = $request->limit ?? 10;

        $notes = Note::latest()
            ->whereCompany()
            ->applyFilters($request->all())
            ->paginate($perPage);

        return NoteResource::collection($notes);
    }

    /**
     * Add a note to the library. The 201 comes from the resource itself, which
     * notices it is wrapping a model that was only just created.
     */
    public function store(NotesRequest $request)
    {
        $this->authorize('manage notes');

        $note = Note::create($request->getNotesPayload());

        $this->demoteRivalDefaults($note);

        return new NoteResource($note);
    }

    public function show(Note $note)
    {
        $this->authorize('view notes', $note);

        return new NoteResource($note);
    }

    /**
     * Edit a note. The demotion sweep runs on the saved state, so switching a
     * note's type and its default flag in one request promotes it inside the
     * type it has just moved to.
     */
    public function update(NotesRequest $request, Note $note)
    {
        $this->authorize('manage notes', $note);

        $note->update($request->getNotesPayload());

        $this->demoteRivalDefaults($note);

        return new NoteResource($note);
    }

    /**
     * Drop a note from the library. Nothing looks for references first —
     * whether a document still names this note is not this endpoint's concern.
     */
    public function destroy(Note $note)
    {
        $this->authorize('manage notes', $note);

        $note->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * A type can only have one default, so promoting one note clears the flag
     * on the rest.
     *
     * KNOWN DEFECT, reproduced on purpose: "the rest" is narrowed by type and
     * by "not this row" and by nothing else — no company narrowing — so saving
     * a default note here also clears the default flag on other tenants' notes
     * of the same type. The correction is scheduled to reach every install at
     * once and is deliberately not made here.
     */
    private function demoteRivalDefaults(Note $note): void
    {
        if (! $note->is_default) {
            return;
        }

        Note::where('id', '!=', $note->id)
            ->where('type', $note->type)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
