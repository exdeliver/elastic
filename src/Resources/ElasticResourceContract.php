<?php

namespace Exdeliver\Elastic\Resources;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

interface ElasticResourceContract
{
    public static function model(): Model;

    public function toElastic(Request $request): array;

    public static function elastic(): array;

    public static function mapping(): array;

    public static function builder(Model $model): Builder;
}
