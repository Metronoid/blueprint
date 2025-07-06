<?php

namespace Blueprint\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSchemaService
{
    /**
     * Check if a table exists in the database
     */
    public function tableExists(string $tableName): bool
    {
        return Schema::hasTable($tableName);
    }

    /**
     * Get the current columns for a table
     */
    public function getTableColumns(string $tableName): array
    {
        if (!$this->tableExists($tableName)) {
            return [];
        }

        return Schema::getColumnListing($tableName);
    }

    /**
     * Get the current column details for a table
     */
    public function getTableColumnDetails(string $tableName): array
    {
        if (!$this->tableExists($tableName)) {
            return [];
        }

        // For now, we'll just return basic column info
        // This can be enhanced later to work with different database drivers
        $columns = [];
        $columnList = $this->getTableColumns($tableName);
        
        foreach ($columnList as $columnName) {
            $columns[$columnName] = [
                'exists' => true,
            ];
        }

        return $columns;
    }

    /**
     * Check if a column exists in a table
     */
    public function columnExists(string $tableName, string $columnName): bool
    {
        if (!$this->tableExists($tableName)) {
            return false;
        }

        return Schema::hasColumn($tableName, $columnName);
    }
} 