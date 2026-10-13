<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasAuditFields
{
    protected static function bootHasAuditFields(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && empty($model->created_by)) {
                $model->created_by = Auth::id();
            }
            if (Auth::check() && empty($model->updated_by)) {
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    public function setDeletedBy(): void
    {
        if (Auth::check()) {
            $this->forceFill(['deleted_by' => Auth::id()])->save();
        }
    }
}
