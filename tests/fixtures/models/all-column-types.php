<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AllType extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'bigInteger',
        'binary',
        'boolean',
        'char',
        'date',
        'dateTime',
        'dateTimeTz',
        'decimal',
        'double',
        'enum',
        'float',
        'fullText',
        'geometry',
        'geometryCollection',
        'integer',
        'ipAddress',
        'json',
        'jsonb',
        'lineString',
        'longText',
        'macAddress',
        'mediumInteger',
        'mediumText',
        'morphs',
        'ulidMorphs',
        'uuidMorphs',
        'multiLineString',
        'multiPoint',
        'multiPolygon',
        'nullableMorphs',
        'nullableUlidMorphs',
        'nullableUuidMorphs',
        'nullableTimestamps',
        'point',
        'polygon',
        'rememberToken',
        'set',
        'smallInteger',
        'string',
        'text',
        'time',
        'timeTz',
        'timestamp',
        'timestampTz',
        'tinyInteger',
        'unsignedBigInteger',
        'unsignedDecimal',
        'unsignedInteger',
        'unsignedMediumInteger',
        'unsignedSmallInteger',
        'unsignedTinyInteger',
        'ulid',
        'uuid',
        'year',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'bigInteger' => 'integer',
            'boolean' => 'boolean',
            'date' => 'date',
            'dateTime' => 'datetime',
            'dateTimeTz' => 'datetime',
            'decimal' => 'decimal',
            'double' => 'double',
            'float' => 'float',
            'json' => 'array',
            'mediumInteger' => 'integer',
            'nullableTimestamps' => 'timestamp',
            'smallInteger' => 'integer',
            'timestamp' => 'timestamp',
            'timestampTz' => 'timestamp',
            'tinyInteger' => 'integer',
            'unsignedBigInteger' => 'integer',
            'unsignedDecimal' => 'decimal',
            'unsignedInteger' => 'integer',
            'unsignedMediumInteger' => 'integer',
            'unsignedSmallInteger' => 'integer',
            'unsignedTinyInteger' => 'integer',
            'year' => 'integer',
            'id' => 'integer',
        ];
    }
}
