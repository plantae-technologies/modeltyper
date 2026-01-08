<?php

namespace FumeApp\ModelTyper\Actions;

use FumeApp\ModelTyper\Traits\ClassBaseName;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

class WriteRelationship
{
    use ClassBaseName;

    /**
     * Write the relationship to the output.
     *
     * @param  array{name: string, type: string, related:string}  $relation
     * @return array{type: string, name: string}|string
     */
    public function __invoke(array $relation, string $indent = '', bool $jsonOutput = false, bool $optionalRelation = false, bool $plurals = false): array|string
    {
        $case = Config::get('modeltyper.case.relations', 'snake');
        $name = app(MatchCase::class)($case, $relation['name']);

        $relatedModel = $this->getTypescriptModelName($relation['related']);
        $optional = $optionalRelation ? '?' : '';

        $relationType = match ($relation['type']) {
            'BelongsToMany', 'HasMany', 'HasManyThrough', 'MorphToMany', 'MorphMany', 'MorphedByMany' => "{$relatedModel}[]",
            // 'BelongsTo', 'HasOne', 'HasOneThrough', 'MorphOne', 'MorphTo' => Str::singular($relatedModel),
            default => $relatedModel,
        };

        if (in_array($relation['type'], Config::get('modeltyper.custom_relationships.singular', []))) {
            $relationType = Str::singular($relation['type']);
        }

        if (in_array($relation['type'], Config::get('modeltyper.custom_relationships.plural', []))) {
            $relationType = Str::singular($relation['type']);
        }

        if ($jsonOutput) {
            return [
                'name' => "{$name}{$optional}",
                'type' => $relationType,
            ];
        }

        return "{$indent}  {$name}{$optional}: {$relationType}" . PHP_EOL;
    }

    /**
     * Get the TypeScript model name, adding prefix for models in specific namespaces.
     */
    private function getTypescriptModelName(string $className): string
    {
        $baseName = $this->getClassName($className);

        // Add 'Vw' prefix for models in the App\Models\Views namespace
        if (str_contains($className, 'App\\Models\\Views\\')) {
            return "Vw$baseName";
        }

        // Instantiate the model to get the table name
        if (class_exists($className)) {
            $model = new $className;
            $tableName = $model->getTable();
            $prefix = $this->getTablePrefix($tableName);

            // Add 'Utils' prefix for models in the PlantaeUtils namespace
            if (str_contains($className, 'PlantaeUtils\\')) {
                return "Utils{$prefix}{$baseName}";
            }

            return "{$prefix}{$baseName}";
        }

        return $baseName;
    }

    /**
     * Extract the prefix from the table name and convert to PascalCase.
     */
    private function getTablePrefix(string $tableName): string
    {
        // Get the first part before underscore (e.g., 'org' from 'org_fazenda')
        $parts = explode('_', $tableName);

        if (count($parts) > 1) {
            return ucfirst($parts[0]);
        }

        return '';
    }
}
