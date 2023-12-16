<?php

namespace Exdeliver\Elastic\Resources;

use Exdeliver\Elastic\Models\CsvModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface ElasticResourceContract
{
    public static function model(): Model|CsvModel;

    public function toElastic(Request $request): array;

    public static function elastic(): array;

    public static function mapping(): array;

    public static function builder(CsvModel|Model $model): CsvModel|Builder;
}
