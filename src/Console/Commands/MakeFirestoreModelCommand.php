<?php

namespace JTD\FirebaseModels\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'make:firestore-model')]
class MakeFirestoreModelCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:firestore-model {name : The name of the model}
                           {--collection= : The Firestore collection name}
                           {--fillable= : Comma-separated list of fillable attributes}
                           {--casts= : Comma-separated list of cast attributes (field:type)}
                           {--relationships : Generate relationship methods}
                           {--timestamps=true : Enable timestamps (default: true)}
                           {--force : Overwrite existing model}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new Firestore model class';

    /**
     * The type of class being generated.
     */
    protected $type = 'FirestoreModel';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        if ($this->option('relationships')) {
            return $this->resolveStubPath('/firestore-model-with-relationships.stub');
        }

        return $this->resolveStubPath('/firestore-model.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.'/../../../stubs'.$stub;
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Models';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = $this->files->get($this->getStub());

        return $this->replaceNamespace($stub, $name)
            ->replaceCollection($stub)
            ->replaceFillable($stub)
            ->replaceCasts($stub)
            ->replaceTimestamps($stub)
            ->replaceClass($stub, $name);
    }

    /**
     * Replace the collection name in the stub.
     */
    protected function replaceCollection(string &$stub): static
    {
        $collection = $this->option('collection') ?: $this->getCollectionName();

        $stub = str_replace(
            ['{{ collection }}', '{{collection}}'],
            $collection,
            $stub
        );

        return $this;
    }

    /**
     * Replace the fillable attributes in the stub.
     */
    protected function replaceFillable(string &$stub): static
    {
        $fillable = $this->option('fillable');

        if ($fillable) {
            $attributes = collect(explode(',', $fillable))
                ->map(fn ($attr) => "'".trim($attr)."'")
                ->implode(",\n        ");

            $fillableArray = "[\n        {$attributes},\n    ]";
        } else {
            $fillableArray = "[\n        // Add your fillable attributes here\n    ]";
        }

        $stub = str_replace(
            ['{{ fillable }}', '{{fillable}}'],
            $fillableArray,
            $stub
        );

        return $this;
    }

    /**
     * Replace the casts in the stub.
     */
    protected function replaceCasts(string &$stub): static
    {
        $casts = $this->option('casts');

        if ($casts) {
            $castArray = collect(explode(',', $casts))
                ->mapWithKeys(function ($cast) {
                    [$field, $type] = explode(':', trim($cast));

                    return [trim($field) => trim($type)];
                })
                ->map(fn ($type, $field) => "'{$field}' => '{$type}'")
                ->implode(",\n        ");

            $castsArray = "[\n        {$castArray},\n    ]";
        } else {
            $castsArray = "[\n        // Add your cast attributes here\n        // 'published' => 'boolean',\n        // 'published_at' => 'datetime',\n        // 'tags' => 'array',\n    ]";
        }

        $stub = str_replace(
            ['{{ casts }}', '{{casts}}'],
            $castsArray,
            $stub
        );

        return $this;
    }

    /**
     * Replace the timestamps setting in the stub.
     */
    protected function replaceTimestamps(string &$stub): static
    {
        // Default to true unless explicitly set to false
        $timestampsOption = $this->option('timestamps');
        $timestamps = ($timestampsOption === 'false' || $timestampsOption === false) ? 'false' : 'true';

        $stub = str_replace(
            ['{{ timestamps }}', '{{timestamps}}'],
            $timestamps,
            $stub
        );

        return $this;
    }

    /**
     * Get the collection name from the model name.
     */
    protected function getCollectionName(): string
    {
        $modelName = class_basename($this->getNameInput());

        return Str::snake(Str::pluralStudly($modelName));
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result !== false) {
            $this->displaySuccessMessage();
            $this->displayUsageExamples();
        }

        return $result;
    }

    /**
     * Display success message with model details.
     */
    protected function displaySuccessMessage(): void
    {
        $modelName = $this->getNameInput();
        $collection = $this->option('collection') ?: $this->getCollectionName();

        $this->info("Firestore model [{$modelName}] created successfully!");
        $this->line("Collection: <comment>{$collection}</comment>");

        if ($this->option('fillable')) {
            $this->line("Fillable: <comment>{$this->option('fillable')}</comment>");
        }

        if ($this->option('casts')) {
            $this->line("Casts: <comment>{$this->option('casts')}</comment>");
        }
    }

    /**
     * Display usage examples for the new model.
     */
    protected function displayUsageExamples(): void
    {
        $modelName = $this->getNameInput();
        $modelClass = class_basename($modelName);

        $this->newLine();
        $this->line('<info>Usage Examples:</info>');
        $this->line("use App\\Models\\{$modelClass};");
        $this->newLine();
        $this->line("// Create a new {$modelClass}");
        $this->line("\${$this->getVariableName()} = {$modelClass}::create([");
        $this->line('    // Add your attributes here');
        $this->line(']);');
        $this->newLine();
        $this->line("// Query {$modelClass} records");
        $this->line("\${$this->getVariableName()}s = {$modelClass}::where('field', 'value')->get();");
        $this->newLine();
        $this->line('// Find by ID');
        $this->line("\${$this->getVariableName()} = {$modelClass}::find('document-id');");

        if ($this->option('relationships')) {
            $this->newLine();
            $this->line('// Load with relationships');
            $this->line("\${$this->getVariableName()} = {$modelClass}::with('relationship')->find('document-id');");
        }
    }

    /**
     * Get the variable name for examples.
     */
    protected function getVariableName(): string
    {
        return Str::camel(class_basename($this->getNameInput()));
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return array_merge(parent::getOptions(), [
            ['collection', 'c', InputOption::VALUE_OPTIONAL, 'The Firestore collection name'],
            ['fillable', 'f', InputOption::VALUE_OPTIONAL, 'Comma-separated list of fillable attributes'],
            ['casts', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of cast attributes (field:type)'],
            ['relationships', 'r', InputOption::VALUE_NONE, 'Generate relationship methods'],
            ['timestamps', 't', InputOption::VALUE_NONE, 'Enable timestamps (default: true)'],
        ]);
    }
}
