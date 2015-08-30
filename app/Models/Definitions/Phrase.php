<?php namespace App\Models\Definitions;

use DB;
use Log;

use App\Models\Language;
use App\Models\Translation;
use App\Models\Definition;
use Illuminate\Support\Arr;
use cebe\markdown\MarkdownExtra;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\HasParamsTrait as HasParams;
use App\Traits\ExportableResourceTrait as Exportable;
use App\Traits\ImportableResourceTrait as Importable;
use App\Traits\ValidatableResourceTrait as Validatable;
use App\Traits\ObfuscatableResourceTrait as Obfuscatable;

/**
 *
 */
class Phrase extends Definition
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
    }

    /**
     * Retrieves a random phrase.
     *
     * @param string $lang
     * @return mixed
     *
     * TODO: filter by phrase type.
     */
    public static function random($lang = null)
    {
        abort(501, 'App\Models\Definitions\Phrase::random not implemented.');
    }

    /**
     * Does a fulltext search for a phrase.
     *
     * @param string $search
     * @param int $offset
     * @param int $limit
     *
     * TODO: filter by phrase type.
     */
    public static function search($search, $offset = 0, $limit = 1000)
    {
        abort(501, 'App\Models\Definitions\Phrase::search not implemented.');
    }

    /**
     * Gets the list of sub types for this definition.
     */
    public function getSubTypes() {
        return $this->subTypes[Definition::TYPE_PHRASE];
    }
}