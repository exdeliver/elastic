<?php

namespace Exdeliver\Elastic\Resources;

use Exdeliver\Elastic\Actions\EnvironmentChecker;
use Exdeliver\Elastic\Models\CsvModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

abstract class ElasticSearchResource extends JsonResource implements ElasticResourceContract
{
    public function toArray(Request $request): array
    {
        return ElasticResource::make($this->resource['_source'])
            ->toArray($request);
    }

    public static function builder(CsvModel|Model $model): CsvModel|Builder
    {
        if ($model instanceof CsvModel) {
            return $model;
        }

        return $model::query();
    }

    public static function environment(): string
    {
        if (EnvironmentChecker::is(['develop'])) {
            return 'dev_';
        }

        if (EnvironmentChecker::is(['production'])) {
            return '';
        }

        return 'test_';
    }
}
