<?php
/**
 * Copyright Di Nkomo(TM) 2016, all rights reserved
 *
 */
namespace App\Http\Controllers\Admin;

use Lang;
use Session;
use Exception;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\SoftDeletes;

class BaseController extends Controller
{
    /**
     * @var string
     */
    protected $name;

    /**
     *
     */
    protected $queryLimit = 20;

    /**
     *
     */
    protected $supportedOrderColumns = [];

    /**
     *
     */
    protected $defaultOrderColumn = 'id';

    /**
     *
     */
    protected $defaultOrderDirection = 'desc';

    /**
     * @param Illuminate\Http\Request $request
     */
    public function __construct(Request $request)
    {
        // Performance check.
        if (!$this->name) {
            throw new Exception('Invalid controller name.');
        }

        $this->request = $request;
    }

    /**
     * Displays a listing of the resource
     *
     * @return View
     */
    public function index()
    {
        // Query builder.
        $builder = $this->getModel();

        // Add trashed items.
        if (in_array(SoftDeletes::class, class_uses_recursive(get_class($builder)))) {
            $builder = $builder->withTrashed();
        }

        // Query parameters
        $total = $builder->count();

        $limit = (int) $this->getParam('limit', $this->defaultQueryLimit);
        $limit = max($limit, 1);
        $limit = min($limit, $total);
        $this->setParam('limit', $limit);

        $limits = [];
        if ($total > 10)    $limits[10] = 10;
        if ($total > 20)    $limits[20] = 20;
        if ($total > 30)    $limits[30] = 30;
        if ($total > 50)    $limits[50] = 50;
        if ($total > 100)    $limits[100] = 100;
        $limits[$total] = $total;

        $orders = collect($this->supportedOrderColumns);
        $order = $this->getParam('order', $this->defaultOrderColumn);
        $order = $orders->has($order) ? $order : $this->defaultOrderColumn;
        // $order = in_array($order, $this->supportedOrderColumns) ? $order : $this->defaultOrderColumn;
        $this->setParam('order', $order);

        $dirs = collect(['asc' => 'ascending', 'desc' => 'descending']);
        $dir = strtolower($this->getParam('dir', $this->defaultOrderDirection));
        $dir = $dirs->has($dir) ? $dir : $this->defaultOrderDirection;
        // $dir = in_array($dir, ['asc', 'desc']) ? $dir : $this->defaultOrderDirection;
        $this->setParam('dir', $dir);

        // Paginator
        $page = $this->setParam('page', $this->getParam('page', 1));
        $paginator = $builder->orderBy(snake_case($order), $dir)->paginate($limit, ['*'], 'page', $page);

        return view("admin.{$this->name}.index", compact([
            'total',
            'limit',
            'limits',
            'order',
            'orders',
            'dir',
            'dirs',
            'paginator'
        ]));
    }

	/**
	 * Show the form for editing the specified resource.
	 *
     * @param mixed $id    ID or Eloquent model.
	 * @return Response
	 */
	public function edit($id)
	{
        // If we already have an instance of the model, great.
        if (is_a($id, 'Illuminate\Database\Eloquent\Model'))
        {
            $model = $id;
        }

        // If we have an encoded ID, decode it.
        elseif (!is_numeric($id) && is_string($id) && strlen($id) >= 8)
        {
            $className = $this->getModelClassName();

            if (!$model = $className::find($id)) {
                abort(404);
            }
        }

        // Performance check.
        elseif (!is_numeric($id))
        {
            abort(404);
        }

        // Retrieve the model by ID.
        elseif (!$model = $this->getModel()->find($id))
        {
            abort(404);
        }

        return view("admin.{$this->name}.edit", compact([
            'model'
        ]));
    }

	/**
	 * Update the specified resource in storage.
     *
	 * @param int $id
	 * @return Response
	 */
	public function update($id)
	{
        // Retrieve model.
        $className = $this->getModelClassName();
        if (!$model = $className::find($id)) {
            abort(404);
        }

        // Validate incoming data.
        $rules = (new $className)->validationRules;
        $this->validate($this->request, $rules);

        // Update attributes.
        $model->fill($this->request->only(array_flip($rules)));

        if (!$model->save()) {
            abort(500);
        }

        // Send success message to client, and a thank you.
        Session::push('messages', 'The details for <em>'. ($model->name ?: $model->title) .
            '</em> were successfully saved, thanks :)');

        // Return URI
        switch ($this->request->get('return'))
        {
            case 'edit':
                $return = $model->editUri;
                break;

            case 'finish':
            case 'summary':
                $return = $model->uri;
                break;

            case 'admin':
            default:
                $return = route("admin.{$this->name}.index");
        }

        return redirect($return);
    }

	/**
	 * Removes the specified resource from storage.
	 *
	 * @param int $id
	 * @return Response
	 */
	public function destroy($id)
	{
        // Retrieve model.
        $className = $this->getModelClassName();
        if (!$model = $className::find($id)) {
            abort(404);
        }

        // Delete record
        $name = $model->name ?: $model->title;
        if ($model->delete()) {
            Session::push('messages', '<em>'. $name .'</em> has been succesfully deleted.');
        } else {
            Session::push('messages', 'Could not delete <em>'. $name .'</em>.');
        }

        // Return URI
        switch ($this->request->get('return'))
        {
            case 'home':
                $return = route('home');
                break;

            case 'admin':
            default:
                $return = route("admin.{$this->name}.index");
        }

        return redirect($return);
	}

    /**
     *
     */
    protected function getModelClassName() {
        return '\\App\\Models\\'. ucfirst($this->name);
    }

    /**
     *
     */
    protected function getModel()
    {
        $className = $this->getModelClassName();

        return new $className;
    }

    /**
     * Retrieves a parameter from the request, or the session
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    protected function getParam($key, $default = null)
    {
        return $this->request->get($key, Session::get('admin-'. $this->name .'-'. $key, $default));
    }

    /**
     * Saves a parameter value to the session.
     *
     * @param string $key
     * @param mixed $value
     * @return ??
     */
    protected function setParam($key, $value = null)
    {
        Session::put('admin-'. $this->name .'-'. $key, $value);

        return $value;
    }
}
