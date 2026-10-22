<?php

namespace App\Http\Traits;

use Yajra\DataTables\Facades\DataTables;
use Illuminate\Database\Eloquent\Builder;

trait AjaxDataTableTrait
{
    /**
     * Wrap an Eloquent Builder in a DataTables JSON response.
     * Pass an optional callback to add/edit columns before make(true).
     */
    protected function dataTableOf(Builder $query, ?callable $callback = null)
    {
        $dt = DataTables::of($query);
        if ($callback) {
            $callback($dt);
        }
        return $dt->make(true);
    }
}
