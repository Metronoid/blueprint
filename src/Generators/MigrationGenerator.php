<?php

namespace Blueprint\Generators;

use Blueprint\Contracts\Generator;
use Blueprint\Models\Model;
use Blueprint\Tree;
use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Symfony\Component\Finder\SplFileInfo;
use Blueprint\Services\DatabaseSchemaService;

class MigrationGenerator extends AbstractClassGenerator implements Generator
{
    const INDENT = '            ';

    const NULLABLE_TYPES = [
        'morphs',
        'uuidMorphs',
    ];

    const ON_DELETE_CLAUSES = [
        'cascade' => "->onDelete('cascade')",
        'restrict' => "->onDelete('restrict')",
        'null' => "->onDelete('set null')",
        'no_action' => "->onDelete('no action')",
    ];

    const ON_UPDATE_CLAUSES = [
        'cascade' => "->onUpdate('cascade')",
        'restrict' => "->onUpdate('restrict')",
        'null' => "->onUpdate('set null')",
        'no_action' => "->onUpdate('no action')",
    ];

    const UNSIGNABLE_TYPES = [
        'bigInteger',
        'decimal',
        'integer',
        'mediumInteger',
        'smallInteger',
        'tinyInteger',
    ];

    const INTEGER_TYPES = [
        'integer',
        'tinyInteger',
        'smallInteger',
        'mediumInteger',
        'bigInteger',
        'unsignedInteger',
        'unsignedTinyInteger',
        'unsignedSmallInteger',
        'unsignedMediumInteger',
        'unsignedBigInteger',
    ];

    protected array $types = ['migrations'];

    private bool $hasForeignKeyConstraints = false;
    
    private ?DatabaseSchemaService $schemaService = null;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct($filesystem);
    }

    protected function getSchemaService(): DatabaseSchemaService
    {
        if ($this->schemaService === null) {
            $this->schemaService = App::make(DatabaseSchemaService::class);
        }
        return $this->schemaService;
    }

    public function output(Tree $tree, $overwrite = false): array
    {
        $tables = ['tableNames' => [], 'pivotTableNames' => [], 'polymorphicManyToManyTables' => []];

        $stub = $this->filesystem->stub('migration.stub');
        /**
         * @var \Blueprint\Models\Model $model
         */
        foreach ($tree->models() as $model) {
            $tables['tableNames'][$model->tableName()] = $this->populateStub($stub, $model);

            if (!empty($model->pivotTables())) {
                foreach ($model->pivotTables() as $pivotSegments) {
                    $pivotTableName = $this->getPivotTableName($pivotSegments);
                    $tables['pivotTableNames'][$pivotTableName] = $this->populatePivotStub($stub, $pivotSegments, $tree->models());
                }
            }

            if (!empty($model->polymorphicManyToManyTables())) {
                foreach ($model->polymorphicManyToManyTables() as $tableName) {
                    $tables['polymorphicManyToManyTables'][Str::lower(Str::plural(Str::singular($tableName) . 'able'))] = $this->populatePolyStub($stub, $tableName);
                }
            }
        }

        return $this->createMigrations($tables, $overwrite);
    }

    protected function populateStub(string $stub, Model $model): string
    {
        $tableName = $model->tableName();
        $tableExists = $this->getSchemaService()->tableExists($tableName);
        
        // Use update stub if table exists, otherwise use create stub
        if ($tableExists) {
            $stub = $this->filesystem->stub('migration-update.stub');
            $stub = str_replace('{{ table }}', $tableName, $stub);
            $stub = str_replace('{{ definition }}', $this->buildUpdateDefinition($model), $stub);
            $stub = str_replace('{{ rollback }}', $this->buildRollbackDefinition($model), $stub);
        } else {
            $stub = str_replace('{{ table }}', $tableName, $stub);
            $stub = str_replace('{{ definition }}', $this->buildDefinition($model), $stub);
        }

        if ($this->hasForeignKeyConstraints) {
            $stub = $this->disableForeignKeyConstraints($stub);
        }

        if ($model->usesCustomDatabaseConnection()) {
            $property = str_replace(
                ['{{ name }}', 'by the model.'],
                [$model->databaseConnection(), 'by the migration.'],
                $this->filesystem->stub('model.connection.stub')
            );

            $stub = Str::replaceFirst('{', '{' . PHP_EOL . $property, $stub);
        }

        return $stub;
    }

    protected function buildDefinition(Model $model): string
    {
        $definition = '';

        // Get all columns and preserve their order as defined in the YAML draft
        $columns = $model->columns();
        $columns = array_values($columns);
        // Move primary key column to the top if it exists
        $primaryKeyIndex = null;
        foreach ($columns as $i => $column) {
            if ($column->name() === $model->primaryKey()) {
                $primaryKeyIndex = $i;
                break;
            }
        }
        if ($primaryKeyIndex !== null && $primaryKeyIndex !== 0) {
            $primaryKey = $columns[$primaryKeyIndex];
            array_splice($columns, $primaryKeyIndex, 1);
            array_unshift($columns, $primaryKey);
        }

        // Track if created_at or updated_at is defined explicitly
        $hasCreatedAt = false;
        $hasUpdatedAt = false;
        foreach ($columns as $column) {
            if ($column->name() === 'created_at') {
                $hasCreatedAt = true;
            }
            if ($column->name() === 'updated_at') {
                $hasUpdatedAt = true;
            }
        }

        /**
         * @var \Blueprint\Models\Column $column
         */
        foreach ($columns as $column) {
            $dataType = $column->dataType();

            if ($column->name() === 'id' && $dataType === 'id') {
                $dataType = 'bigIncrements';
            } elseif ($dataType === 'id') {
                $dataType = 'foreignId';
            }

            // If this column should have a foreign key constraint, output only the chained version and skip the rest
            if ($this->shouldAddForeignKeyConstraint($column)) {
                $this->hasForeignKeyConstraints = true;
                $foreign_modifier = $column->isForeignKey();
                $definition .= $this->buildForeignKey(
                    $column->name(),
                    $foreign_modifier === 'foreign' ? null : $foreign_modifier,
                    $column->dataType(),
                    $column->attributes(),
                    $column->modifiers()
                ) . ';' . PHP_EOL;
                continue;
            }

            $column_definition = self::INDENT;
            if ($dataType === 'bigIncrements') {
                $column_definition .= '$table->id(';
            } elseif ($dataType === 'rememberToken') {
                $column_definition .= '$table->rememberToken(';
            } else {
                $column_definition .= '$table->' . $dataType . "('{$column->name()}'";
            }

            $columnAttributes = $column->attributes();

            if (in_array($dataType, self::INTEGER_TYPES)) {
                $columnAttributes = array_filter(
                    $columnAttributes,
                    fn ($columnAttribute) => !is_numeric($columnAttribute),
                );
            }

            if (!empty($columnAttributes) && !$this->isIdColumnType($column->dataType())) {
                $column_definition .= ', ';

                if (in_array($column->dataType(), ['geography', 'geometry'])) {
                    $columnAttributes[0] = Str::wrap($columnAttributes[0], "'");
                }

                if (in_array($column->dataType(), ['set', 'enum'])) {
                    $column_definition .= json_encode($columnAttributes);
                } else {
                    $column_definition .= implode(', ', $columnAttributes);
                }
            }

            $column_definition .= ')';

            $modifiers = $column->modifiers();

            foreach (
                $modifiers as $modifier
            ) {
                if ($modifier === 'nullable') {
                    $column_definition .= '->nullable()';
                } elseif ($modifier === 'unsigned' && !in_array($dataType, self::UNSIGNABLE_TYPES)) {
                    $column_definition .= '->unsigned()';
                } elseif ($modifier === 'default') {
                    $column_definition .= '->default(' . $this->getDefaultValue($column) . ')';
                } elseif ($modifier === 'index') {
                    $column_definition .= '->index()';
                } elseif ($modifier === 'unique') {
                    $column_definition .= '->unique()';
                } elseif ($modifier === 'primary') {
                    $column_definition .= '->primary()';
                }
                // Do not append foreign key constraints here, handled above
                // elseif ($modifier === 'foreign') {
                //     $column_definition .= $foreign;
                // } elseif (is_string($modifier) && Str::startsWith($modifier, 'foreign:')) {
                //     $column_definition .= $this->buildForeignKey(
                //         $column->name(),
                //         Str::after($modifier, 'foreign:'),
                //         $column->dataType(),
                //         $columnAttributes,
                //         $column->modifiers()
                //     );
                // }
                elseif (is_string($modifier) && Str::startsWith($modifier, 'onDelete:')) {
                    $column_definition .= $this->buildOnDeleteClause(Str::after($modifier, 'onDelete:'));
                } elseif (is_string($modifier) && Str::startsWith($modifier, 'onUpdate:')) {
                    $column_definition .= $this->buildOnUpdateClause(Str::after($modifier, 'onUpdate:'));
                }
            }

            $definition .= $column_definition . ';' . PHP_EOL;
        }

        $relationships = $model->relationships();

        if (array_key_exists('morphTo', $relationships)) {
            foreach ($relationships['morphTo'] as $morphTo) {
                $definition .= self::INDENT . sprintf('$table->morphs(\'%s\');', Str::lower($morphTo)) . PHP_EOL;
            }
        }

        foreach ($model->indexes() as $index) {
            $index_definition = self::INDENT;
            $index_definition .= '$table->' . $index->type();
            if (count($index->columns()) > 1) {
                $index_definition .= "(['" . implode("', '", $index->columns()) . "']);" . PHP_EOL;
            } else {
                $index_definition .= "('{$index->columns()[0]}');" . PHP_EOL;
            }
            $definition .= $index_definition;
        }
        if ($model->usesTimestamps() && !($hasCreatedAt || $hasUpdatedAt)) {
            $definition .= self::INDENT . '$table->' . $model->timestampsDataType() . '();' . PHP_EOL;
        }

        if ($model->usesSoftDeletes()) {
            $definition .= self::INDENT . '$table->' . $model->softDeletesDataType() . '();' . PHP_EOL;
        }

        return trim($definition);
    }

    protected function isIdColumnType(string $dataType): bool
    {
        return in_array($dataType, ['id', 'ulid', 'uuid']);
    }

    private function shouldAddForeignKeyConstraint(\Blueprint\Models\Column $column): bool
    {
        if ($column->name() === 'id') {
            return false;
        }

        if ($column->isForeignKey()) {
            return true;
        }

        return config('blueprint.use_constraints')
            && ($this->isIdColumnType($column->dataType()) && Str::endsWith($column->name(), '_id'));
    }

    protected function buildForeignKey(string $column_name, ?string $on, string $type, array $attributes = [], array $modifiers = []): string
    {
        if (is_null($on)) {
            $table = Str::plural(Str::beforeLast($column_name, '_'));
            $column = Str::afterLast($column_name, '_');
        } elseif (Str::contains($on, '.')) {
            [$table, $column] = explode('.', $on);
        } elseif (Str::contains($on, '\\')) {
            $table = Str::snake(Str::plural(Str::afterLast($on, '\\')));
            $column = Str::afterLast($column_name, '_');
        } else {
            $table = Str::snake(Str::plural($on));
            $column = Str::afterLast($column_name, '_');
        }

        if ($this->isIdColumnType($type) && !empty($attributes)) {
            $table = Str::lower(Str::plural($attributes[0]));
        }

        $on_delete_suffix = $on_update_suffix = null;
        $on_delete_clause = collect($modifiers)->firstWhere('onDelete');
        if (config('blueprint.use_constraints') || $on_delete_clause) {
            $on_delete_clause = $on_delete_clause ? $on_delete_clause['onDelete'] : config('blueprint.on_delete', 'cascade');
            $on_delete_suffix = self::ON_DELETE_CLAUSES[$on_delete_clause];
        }

        $on_update_clause = collect($modifiers)->firstWhere('onUpdate');
        if (config('blueprint.use_constraints') || $on_update_clause) {
            $on_update_clause = $on_update_clause ? $on_update_clause['onUpdate'] : config('blueprint.on_update', 'cascade');
            $on_update_suffix = self::ON_UPDATE_CLAUSES[$on_update_clause];
        }

        if ($this->isIdColumnType($type)) {
            $method = match ($type) {
                'ulid' => 'foreignUlid',
                'uuid' => 'foreignUuid',
                default => 'foreignId',
            };

            $prefix = in_array('nullable', $modifiers)
                ? '$table->' . "{$method}('{$column_name}')->nullable()"
                : '$table->' . "{$method}('{$column_name}')";

            if ($on_delete_clause === 'cascade') {
                $on_delete_suffix = '->cascadeOnDelete()';
            }

            if ($on_update_clause === 'cascade') {
                $on_update_suffix = '->cascadeOnUpdate()';
            }

            if ($column_name === Str::singular($table) . '_' . $column) {
                return self::INDENT . "{$prefix}->constrained(){$on_delete_suffix}{$on_update_suffix}";
            }

            if ($column === 'id') {
                return self::INDENT . "{$prefix}->constrained('{$table}'){$on_delete_suffix}{$on_update_suffix}";
            }

            return self::INDENT . "{$prefix}->constrained('{$table}', '{$column}'){$on_delete_suffix}{$on_update_suffix}";
        }

        return self::INDENT . '$table->foreign' . "('{$column_name}')->references('{$column}')->on('{$table}'){$on_delete_suffix}{$on_update_suffix}";
    }

    protected function isNumericDefault(string $type, string $value): bool
    {
        if (!is_numeric($value)) {
            return false;
        }

        if (Str::startsWith($type, 'unsigned')) {
            $type = Str::after($type, 'unsigned');
        }

        return collect(self::UNSIGNABLE_TYPES)
            ->contains(fn ($value) => strtolower($value) === strtolower($type));
    }

    protected function disableForeignKeyConstraints($stub): string
    {
        $stub = str_replace('Schema::create(', 'Schema::disableForeignKeyConstraints();' . PHP_EOL . PHP_EOL . str_pad(' ', 8) . 'Schema::create(', $stub);

        $stub = str_replace('});', '});' . PHP_EOL . PHP_EOL . str_pad(' ', 8) . 'Schema::enableForeignKeyConstraints();', $stub);

        return $stub;
    }

    protected function getPivotTableName(array $segments): string
    {
        $segments = array_map(fn ($name) => Str::of($name)->before(':')->snake()->value(), $segments);
        sort($segments);

        return strtolower(implode('_', $segments));
    }

    protected function populatePivotStub(string $stub, array $segments, array $models = []): string
    {
        $stub = str_replace('{{ table }}', $this->getPivotTableName($segments), $stub);
        $stub = str_replace('{{ definition }}', $this->buildPivotTableDefinition($segments, $models), $stub);

        if ($this->hasForeignKeyConstraints) {
            $stub = $this->disableForeignKeyConstraints($stub);
        }

        return $stub;
    }

    protected function buildPivotTableDefinition(array $segments, array $models = []): string
    {
        $definition = '';
        foreach ($segments as $segment) {
            $column = Str::before(Str::snake($segment), ':');
            $references = 'id';
            $on = Str::plural($column);
            $foreign = Str::singular($column) . '_' . $references;

            if (config('blueprint.use_constraints')) {
                $this->hasForeignKeyConstraints = true;

                $type = isset($models[$segment]) ? $models[$segment]->idType() : 'id';
                $definition .= $this->buildForeignKey($foreign, $on, $type) . ';' . PHP_EOL;
            } else {
                $definition .= $this->generateForeignKeyDefinition($segment, $foreign, $models);
            }
        }

        return trim($definition);
    }

    /**
     * Generates the foreign key definition for a pivot table.
     *
     * This function generates the foreign key definition for a pivot table in a migration file.
     * It checks if the model exists and its name matches the pivot table segment. If it does,
     * it determines the data type of the primary key and appends the appropriate method call
     * to the `$definition` string. If the model does not exist or its name does not match the
     * pivot table segment, it defaults to appending `$table->foreignId(\'' . $foreignKeyColumnName . '\');`
     * to the `$definition` string. The function then returns the `$definition` string.
     *
     * @param  string  $pivotTableSegment  The segment of the pivot table. e.g 'dive_job' it would be 'Dive' or 'Job'.
     * @param  string  $foreignKeyColumnName  The name of the foreign key column. e.g 'dive_id' or 'job_id'.
     * @param  array  $models  An array of models. e.g ['Dive' => $diveModel, 'Job' => $jobModel].
     * @return string The foreign key definition. e.g '$table->foreignUlid('dive_id');'
     */
    protected function generateForeignKeyDefinition(string $pivotTableSegment, string $foreignKeyColumnName, array $models = []): string
    {
        $definition = self::INDENT;
        if (count($models) > 0 && array_key_exists($pivotTableSegment, $models)) {
            $model = $models[$pivotTableSegment];
            if ($model->name() === $pivotTableSegment) {
                $dataType = $model->columns()[$model->primaryKey()]->dataType();
                $definition .= match ($dataType) {
                    'ulid' => '$table->foreignUlid(\'' . $foreignKeyColumnName . '\');',
                    'uuid' => '$table->foreignUuid(\'' . $foreignKeyColumnName . '\');',
                    default => '$table->foreignId(\'' . $foreignKeyColumnName . '\');',
                };
            }
        } else {
            $definition .= '$table->foreignId(\'' . $foreignKeyColumnName . '\');';
        }
        $definition .= PHP_EOL;

        return $definition;
    }

    protected function buildUpdateDefinition(Model $model): string
    {
        $definition = '';
        $existingColumns = $this->getSchemaService()->getTableColumns($model->tableName());
        $existingColumnDetails = $this->getSchemaService()->getTableColumnDetails($model->tableName());

        // Get all columns and preserve their order as defined in the YAML draft
        $columns = $model->columns();
        $columns = array_values($columns);

        // Track if created_at or updated_at is defined explicitly in YAML
        $hasCreatedAt = false;
        $hasUpdatedAt = false;
        foreach ($columns as $column) {
            if ($column->name() === 'created_at') {
                $hasCreatedAt = true;
            }
            if ($column->name() === 'updated_at') {
                $hasUpdatedAt = true;
            }
        }
        // Also check if they exist in the DB
        $dbHasCreatedAt = in_array('created_at', $existingColumns);
        $dbHasUpdatedAt = in_array('updated_at', $existingColumns);

        /**
         * @var \Blueprint\Models\Column $column
         */
        foreach ($columns as $column) {
            $columnName = $column->name();
            
            // Skip if column already exists and has the same definition
            if (in_array($columnName, $existingColumns)) {
                // TODO: Compare column definitions to see if changes are needed
                continue;
            }

            $dataType = $column->dataType();

            if ($column->name() === 'id' && $dataType === 'id') {
                $dataType = 'bigIncrements';
            } elseif ($dataType === 'id') {
                $dataType = 'foreignId';
            }

            if (in_array($dataType, self::UNSIGNABLE_TYPES) && in_array('unsigned', $column->modifiers())) {
                $dataType = 'unsigned' . ucfirst($dataType);
            }

            if (in_array($dataType, self::NULLABLE_TYPES) && $column->isNullable()) {
                $dataType = 'nullable' . ucfirst($dataType);
            }

            $column_definition = self::INDENT;
            if ($dataType === 'bigIncrements') {
                $column_definition .= '$table->id(';
            } elseif ($dataType === 'rememberToken') {
                $column_definition .= '$table->rememberToken(';
            } else {
                $column_definition .= '$table->' . $dataType . "('{$column->name()}'";
            }

            $columnAttributes = $column->attributes();

            if (in_array($dataType, self::INTEGER_TYPES)) {
                $columnAttributes = array_filter(
                    $columnAttributes,
                    fn ($columnAttribute) => !is_numeric($columnAttribute),
                );
            }

            if (!empty($columnAttributes) && !$this->isIdColumnType($column->dataType())) {
                $column_definition .= ', ';

                if (in_array($column->dataType(), ['geography', 'geometry'])) {
                    $columnAttributes[0] = Str::wrap($columnAttributes[0], "'");
                }

                if (in_array($column->dataType(), ['set', 'enum'])) {
                    $column_definition .= json_encode($columnAttributes);
                } else {
                    $column_definition .= implode(', ', $columnAttributes);
                }
            }

            $column_definition .= ')';

            $modifiers = $column->modifiers();

            $foreign = '';
            $foreign_modifier = $column->isForeignKey();

            if ($this->shouldAddForeignKeyConstraint($column)) {
                $this->hasForeignKeyConstraints = true;
                $foreign = $this->buildForeignKey(
                    $column->name(),
                    $foreign_modifier === 'foreign' ? null : $foreign_modifier,
                    $column->dataType(),
                    $columnAttributes,
                    $column->modifiers()
                );
            }

            foreach (
                $modifiers as $modifier
            ) {
                if ($modifier === 'nullable') {
                    $column_definition .= '->nullable()';
                } elseif ($modifier === 'unsigned' && !in_array($dataType, self::UNSIGNABLE_TYPES)) {
                    $column_definition .= '->unsigned()';
                } elseif ($modifier === 'default') {
                    $column_definition .= '->default(' . $this->getDefaultValue($column) . ')';
                } elseif ($modifier === 'index') {
                    $column_definition .= '->index()';
                } elseif ($modifier === 'unique') {
                    $column_definition .= '->unique()';
                } elseif ($modifier === 'primary') {
                    $column_definition .= '->primary()';
                } elseif ($modifier === 'foreign') {
                    $column_definition .= $foreign;
                } elseif (is_string($modifier) && Str::startsWith($modifier, 'foreign:')) {
                    $column_definition .= $this->buildForeignKey(
                        $column->name(),
                        Str::after($modifier, 'foreign:'),
                        $column->dataType(),
                        $columnAttributes,
                        $column->modifiers()
                    );
                } elseif (is_string($modifier) && Str::startsWith($modifier, 'onDelete:')) {
                    $column_definition .= $this->buildOnDeleteClause(Str::after($modifier, 'onDelete:'));
                } elseif (is_string($modifier) && Str::startsWith($modifier, 'onUpdate:')) {
                    $column_definition .= $this->buildOnUpdateClause(Str::after($modifier, 'onUpdate:'));
                }
            }

            $definition .= $column_definition . ';' . PHP_EOL;
        }

        if ($model->usesTimestamps() && !($hasCreatedAt || $hasUpdatedAt || $dbHasCreatedAt || $dbHasUpdatedAt)) {
            $definition .= self::INDENT . '$table->' . $model->timestampsDataType() . '();' . PHP_EOL;
        }

        return trim($definition);
    }

    protected function buildRollbackDefinition(Model $model): string
    {
        $rollback = '';
        $existingColumns = $this->getSchemaService()->getTableColumns($model->tableName());

        // Get all columns from the model
        $columns = $model->columns();
        $columns = array_values($columns);

        /**
         * @var \Blueprint\Models\Column $column
         */
        foreach ($columns as $column) {
            $columnName = $column->name();
            
            // Only add drop column if the column doesn't exist in the original table
            if (!in_array($columnName, $existingColumns)) {
                $rollback .= self::INDENT . "\$table->dropColumn('{$columnName}');" . PHP_EOL;
            }
        }

        return trim($rollback);
    }

    protected function populatePolyStub(string $stub, string $parentTable): string
    {
        $stub = str_replace('{{ table }}', $this->getPolyTableName($parentTable), $stub);
        $stub = str_replace('{{ definition }}', $this->buildPolyTableDefinition($parentTable), $stub);

        if ($this->hasForeignKeyConstraints) {
            $stub = $this->disableForeignKeyConstraints($stub);
        }

        return $stub;
    }

    protected function getPolyTableName(string $parentTable): string
    {
        return Str::plural(Str::lower(Str::singular($parentTable) . 'able'));
    }

    protected function buildPolyTableDefinition(string $parentTable): string
    {
        $definition = '';

        $references = 'id';
        $on = Str::lower(Str::plural($parentTable));
        $foreign = Str::lower(Str::singular($parentTable)) . '_' . $references;

        if (config('blueprint.use_constraints')) {
            $this->hasForeignKeyConstraints = true;
            $definition .= $this->buildForeignKey($foreign, $on, 'id') . ';' . PHP_EOL;
        } else {
            $definition .= self::INDENT . '$table->foreignId(\'' . $foreign . '\');' . PHP_EOL;
        }

        $definition .= self::INDENT . sprintf('$table->morphs(\'%s\');', Str::lower(Str::singular($parentTable) . 'able')) . PHP_EOL;

        return trim($definition);
    }

    protected function createMigrations(array $tables, $overwrite = false): array
    {
        $total_tables = collect($tables['tableNames'])->merge($tables['pivotTableNames'])->merge($tables['polymorphicManyToManyTables'])->count();
        $sequential_timestamp = \Carbon\Carbon::now()->copy()->subSeconds($total_tables - 1);
        $table_counter = 0;

        foreach ($tables['tableNames'] as $tableName => $data) {
            $timestamp = $sequential_timestamp->copy()->addSeconds($table_counter);
            $path = $this->getTablePath($tableName, $timestamp, $overwrite);
            $action = $this->filesystem->exists($path) ? 'updated' : 'created';
            $this->filesystem->put($path, $data);
            $this->output[$action][] = $path;
            $table_counter++;
        }

        foreach ($tables['pivotTableNames'] as $tableName => $data) {
            $timestamp = $sequential_timestamp->copy()->addSeconds($table_counter);
            $path = $this->getTablePath($tableName, $timestamp, $overwrite);
            $action = $this->filesystem->exists($path) ? 'updated' : 'created';
            $this->filesystem->put($path, $data);
            $this->output[$action][] = $path;
            $table_counter++;
        }

        foreach ($tables['polymorphicManyToManyTables'] as $tableName => $data) {
            $timestamp = $sequential_timestamp->copy()->addSeconds($table_counter);
            $path = $this->getTablePath($tableName, $timestamp, $overwrite);
            $action = $this->filesystem->exists($path) ? 'updated' : 'created';
            $this->filesystem->put($path, $data);
            $this->output[$action][] = $path;
            $table_counter++;
        }

        return $this->output;
    }

    protected function getTablePath($tableName, Carbon $timestamp, $overwrite = false)
    {
        $dir = 'database/migrations/';
        $tableExists = $this->getSchemaService()->tableExists($tableName);
        
        if ($tableExists) {
            $name = '_update_' . $tableName . '_table.php';
        } else {
            $name = '_create_' . $tableName . '_table.php';
        }

        if ($overwrite) {
            $migrations = collect($this->filesystem->files($dir))
                ->filter(fn (SplFileInfo $file) => str_contains($file->getFilename(), $name))
                ->sort();

            if ($migrations->isNotEmpty()) {
                $migration = $migrations->first()->getPathname();

                $migrations->diff($migration)
                    ->each(function (SplFileInfo $file) {
                        $path = $file->getPathname();
                        $this->filesystem->delete($path);
                        $this->output['deleted'][] = $path;
                    });

                return $migration;
            }
        }

        return $dir . $timestamp->format('Y_m_d_His') . $name;
    }

    protected function getClassName(Model $model): string
    {
        $tableExists = $this->getSchemaService()->tableExists($model->tableName());
        
        if ($tableExists) {
            return 'Update' . Str::studly($model->tableName()) . 'Table';
        }
        
        return 'Create' . Str::studly($model->tableName()) . 'Table';
    }

    protected function getDefaultValue($column)
    {
        $default = $column->defaultValue();
        
        if (is_null($default)) {
            return 'null';
        }
        
        if (is_bool($default)) {
            return $default ? 'true' : 'false';
        }
        
        if (is_numeric($default)) {
            return $default;
        }
        
        return "'{$default}'";
    }

    protected function buildOnDeleteClause(string $action): string
    {
        return self::ON_DELETE_CLAUSES[$action] ?? self::ON_DELETE_CLAUSES['cascade'];
    }

    protected function buildOnUpdateClause(string $action): string
    {
        return self::ON_UPDATE_CLAUSES[$action] ?? self::ON_UPDATE_CLAUSES['cascade'];
    }
}
