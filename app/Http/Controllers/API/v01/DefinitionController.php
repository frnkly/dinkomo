<?php
/**
 * Copyright Di Nkomo(TM) 2015, all rights reserved
 *
 * @version 0.1
 * @brief   Handles definition-related API requests.
 */
namespace App\Http\Controllers\API\v01;

use DB;
use URL;
use Auth;
use Lang;
use Request;
use Session;
use Redirect;
use Validator;

use App\Http\Requests;
use App\Models\Language;
use App\Models\Definition;
use App\Models\Translation;
use App\Models\Definitions\Word;
use Illuminate\Support\Arr;
use App\Http\Controllers\Controller;


class DefinitionController extends Controller
{
    public function __construct()
    {
        // Enable the auth middleware.
		// $this->middleware('auth', ['except' => ['show', 'search', 'exists']]);
    }

    /**
     * Returns a definition resource.
     *
     * @param string $id    Unique ID of definition.
     * @return object
     */
    public function show($id)
    {
        // Retrieve definition object
        if ($definition = Definition::find($id)) {
            return $definition;
        }

        return response('Definition Not Found.', 404);
    }

    /**
     * Finds definitions matching a title (exact match).
     *
     * @param string $definitionType
     * @param string $title
     * @return Response
     */
    public function findByTitle($definitionType, $title)
    {
        // Performance check
        $title = trim(preg_replace('/[\s+]/', ' ', strip_tags($title)));
        if (strlen($title) < 2) {
            return response('Query Too Short.', 400);
        }

        // TODO: add definition type to where clause.
        // ...

        // Lookup definitions with a specific title
        $definitions = Definition::where('title', '=', $title)->get();

        return $definitions ?: response('Definition Not Found.', 404);
    }

	/**
	 * Store a newly created resource in storage.
	 *
	 * @return Response
	 */
	public function store()
    {
        // Instantiate by definition type.
        switch (Request::input('type'))
        {
            case Definition::TYPE_WORD:
                $definition = new Word;
                break;

            default:
                return response('Invalid definition type.', 400);
        }

        $definition->state = Definition::STATE_VISIBLE;

        // Retrieve data for new definition.
        $data = Request::only(['title', 'alt_titles', 'sub_type']);

        // Create the record in the database.
        return $this->save($definition, $data);
    }

    /**
     * Shortcut to save a definition model.
     *
     * @param \App\Models\Definition $definition
     * @param array $data
     * @return Response
     */
	public function save($definition, array $data = [])
    {
        // Validate incoming data.
        $validation = Definition::validate($data);
        if ($validation->fails())
        {
            // Return first message as error hint.
            return response($validation->messages()->first(), 400);
        }

        // Add definition to database.
        $definition->fill($data);
        if (!$definition->save()) {
            return response('Could Not Save Definition.', 500);
        }

        // Add language relations.
        $languageCodes = Request::input('languages');
        if (is_array($languageCodes))
        {
            $languageIDs = [];

            foreach ($languageCodes as $langCode)
            {
                if ($lang = Language::findByCode($langCode)) {
                    $languageIDs[] = $lang->id;
                }
            }

            $definition->languages()->sync($languageIDs);
        }

        // Add translation relations.
        $rawTranslations = Request::input('translations');
        if (is_array($rawTranslations))
        {
            $translations = [];

            foreach ($rawTranslations as $foreign => $data)
            {
                $data['language'] = $foreign;
                $translations[] = new Translation($data);
            }

            $definition->translations()->saveMany($translations);
        }

        return $definition;
	}

    /**
     * Update the specified resource in storage.
     *
     * @param  int $id
     * @throws \Exception
     * @return Response
     */
	public function update($id)
	{
        // TODO ...

        return $this->error(501);
	}

    /**
     * Remove the specified resource from storage.
     *
     * @param  int $id
     * @throws \Exception
     * @return Response
     */
	public function destroy($id)
	{
        // TODO ...

        return $this->error(501);
	}
}
