<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\Concerns\AppliesListQuery;

/**
 * Base controller for operator Inertia actions (surface A).
 */
abstract class BaseController extends Controller
{
    use AppliesListQuery;
}
