<?php

namespace App\Http\Controllers;

use App\Library;
use App\Thing;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ThingsController extends Controller {

    protected $thing;

    /**
     * Validation error messages.
     *
     * @static array
     */
    protected $messages = [
        'name.required' => 'Internt navn må fylles ut.',
        'name.unique' => 'Typen finnes allerede.',

        'email_name_nob.required' => 'Ubestemt form på bokmål må fylles ut.',
        'email_name_definite_nob.required' => 'Bestemt form på bokmål må fylles ut.',

        'email_name_eng.required' => 'Ubestemt form på engelsk må fylles ut.',
        'email_name_definite_eng.required' => 'Bestemt form på engelsk må fylles ut.',
    ];

    public function __construct(Thing $thing)
    {
        $this->thingFactory = $thing;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Response
     */
    public function getIndex(Request $request)
    {
        $library_id = \Auth::user()->id;

        $things = $this->thingFactory
            ->with('items.loans');

        if ($request->input('mine')) {
            $things->whereHas('libraries', function ($query) use ($library_id) {
                $query->where('library_id', '=', $library_id);
            });
        }

        $things = $things->orderBy('name')
            ->get();

        if ($request->ajax()) {
            return response()->json($things);
        }

        return response()->view('things.index', array(
            'things' => $things
        ));
    }

    /**
     * Display a listing of the resource.
     *
     * @param Library $library
     * @return Response
     */
    public function getAvailableJson(Library $library)
    {
        $things = $this->thingFactory
            ->with('items.loans')
            ->where('library_id', null)
            ->orWhere('library_id', $library->id)
            ->get();

        $out = [];
        foreach ($things as $t) {
            $out[] = [
                'name' => $t->name,
                'disabled' => $t->disabled,
                'num_items' => $t->num_items,
                'available_items' => $t->availableItems(),
            ];
        }

        return response()->json($out);
    }

    /**
     * Display a listing of the resource.
     *
     * @param Library $library
     * @return Response
     */
    public function getAvailable(Library $library)
    {
        return response()->view('things.available', [
            'library_id' => $library->id,
        ]);
    }

    /**
     * Display the specified resource.
     *
     * @param Thing $thing
     * @return Response
     */
    public function getShow(Thing $thing)
    {
        return response()->view('things.show', array(
            'thing' => $thing,
        ));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Thing $thing
     * @return Response
     */
    public function getEdit(Thing $thing)
    {
        return response()->view('things.edit', array(
            'thing' => $thing,
        ));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param Thing $thing
     * @param Request $request
     * @return Response
     */
    public function postUpdate(Thing $thing, Request $request)
    {
        \Validator::make($request->all(), [
            'name' => 'required|unique:things,name' . ($thing->id ? ',' . $thing->id : ''),
            'email_name_nob' => 'required',
            'email_name_eng' => 'required',
            'email_name_definite_nob' => 'required',
            'email_name_definite_eng' => 'required',
        ], $this->messages)->validate();

        $thing->name = $request->input('name');
        $thing->email_name_nob = $request->input('email_name_nob');
        $thing->email_name_eng = $request->input('email_name_eng');
        $thing->email_name_definite_nob = $request->input('email_name_definite_nob');
        $thing->email_name_definite_eng = $request->input('email_name_definite_eng');
        $thing->num_items = $request->input('num_items') ?: 0;
        $thing->disabled = $request->input('disabled') == 'on';
        $thing->send_reminders = $request->input('send_reminders') == 'on';

        if (!$thing->save()) {
            return redirect()->action('ThingsController@getEdit', $thing->id ?: '_new')
                ->withErrors($thing->errors)
                ->withInput();
        }

        return redirect()->action('ThingsController@getShow', $thing->id)
            ->with('status', 'Tingen ble lagret!');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Thing $thing
     * @return Response
     */
    public function getDestroy(Thing $thing)
    {
        if (count($thing->allLoans()) != 0) {
            return redirect()->action('ThingsController@getShow', $thing->id)
                ->with('status', 'Beklager, kan bare slette ting som ikke har blitt lånt ut enda.');
        }

        $thing->delete();

        return redirect()->action('ThingsController@getIndex')
            ->with('status', 'Tingen «' . $thing->name . '» ble slettet.');
    }

    /**
     * Restore the specified resource.
     *
     * @param Thing $thing
     * @return Response
     */
    public function getRestore(Thing $thing)
    {
        $thing->restore();

        return redirect()->action('ThingsController@getShow', $thing->id)
            ->with('status', 'Tingen «' . $thing->name . '» ble gjenopprettet.');
    }

    /**
     * Toggle thing for my library
     *
     * @param Thing $thing
     * @return Response
     */
    public function toggle(Thing $thing, Request $request)
    {
        $library_id = \Auth::user()->id;

        if ($request->input('value')) {
            $thing->libraries()->attach($library_id);
        } else {
            $thing->libraries()->detach($library_id);
        }

        return response()->json(['status' => 'ok']);
    }

    /**
     * Toggle item requirement for my library
     *
     * @param Thing $thing
     * @return Response
     */
    public function toggleRequireItem(Thing $thing, Request $request)
    {
        $libraryId = \Auth::user()->id;
        $thing->libraries()->updateExistingPivot($libraryId, ['require_item' => !!$request->input('value') ]);

        return response()->json(['status' => 'ok']);
    }

}
