<?php namespace App\Http\Controllers;

use App\Http\Requests;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ExportableResourceTrait;
use App\Traits\ImportableResourceTrait;
use Illuminate\Http\Request;

/**
 *
 */
class UserController extends Controller {

    use ExportableResourceTrait, ImportableResourceTrait;

    /**
     * @param $format
     * @return mixed
     */
    public static function export($format = 'yaml')
    {
        $data = User::all();
        $formatted = [];

        foreach ($data as $item) {
            $formatted[] = $item->toArray();
        }

        return static::exportToFormat($formatted, $format, false);
    }
}