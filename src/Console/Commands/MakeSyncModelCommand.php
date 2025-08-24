<?php

namespace JTD\FirebaseModels\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Symfony\Component\Console\Input\InputOption;

/**
 * Command to generate sync-enabled Firestore models.
 */
class MakeSyncModelCommand extends GeneratorCommand
{
    /**
     * The name and signature of the console command.
     */
    protected $name = 'make:firestore-sync-model';

    /**
     * The console command description.
     */
    protected $description = 'Create a new sync-enabled Firestore model';

    /**
     * The type of class being generated.
     */
    protected $type = 'Firestore Sync Model';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return __DIR__.'/../../../stubs/firestore-model-sync.stub';
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
            ->replaceTable($stub)
            ->replaceFillable($stub)
            ->replaceHidden($stub)
            ->replaceCasts($stub)
            ->replaceClass($stub, $name);
    }

    /**
     * Replace the collection name in the stub.
     */
    protected function replaceCollection(string &$stub): static
    {
        $collection = $this->option('collection') ?: Str::snake(Str::pluralStudly(class_basename($this->argument('name'))));
        $stub = str_replace('{{ collection }}', $collection, $stub);

        return $this;
    }

    /**
     * Replace the table name in the stub.
     */
    protected function replaceTable(string &$stub): static
    {
        $table = $this->option('table') ?: Str::snake(Str::pluralStudly(class_basename($this->argument('name'))));
        $stub = str_replace('{{ table }}', $table, $stub);

        return $this;
    }

    /**
     * Replace the fillable attributes in the stub.
     */
    protected function replaceFillable(string &$stub): static
    {
        $fillable = $this->option('fillable');

        if ($fillable) {
            $fillableArray = collect(explode(',', $fillable))
                ->map(fn ($field) => "'".trim($field)."'")
                ->implode(",\n        ");
        } else {
            $fillableArray = '// Add your fillable attributes here';
        }

        $stub = str_replace('{{ fillable }}', $fillableArray, $stub);

        return $this;
    }

    /**
     * Replace the hidden attributes in the stub.
     */
    protected function replaceHidden(string &$stub): static
    {
        $hidden = $this->option('hidden');

        if ($hidden) {
            $hiddenArray = collect(explode(',', $hidden))
                ->map(fn ($field) => "'".trim($field)."'")
                ->implode(",\n        ");
        } else {
            $hiddenArray = '// Add your hidden attributes here';
        }

        $stub = str_replace('{{ hidden }}', $hiddenArray, $stub);

        return $this;
    }

    /**
     * Replace the casts in the stub.
     */
    protected function replaceCasts(string &$stub): static
    {
        $casts = $this->option('casts');

        if ($casts) {
            $castsArray = collect(explode(',', $casts))
                ->map(function ($cast) {
                    [$field, $type] = explode(':', trim($cast));

                    return "'".trim($field)."' => '".trim($type)."'";
                })
                ->implode(",\n        ");
        } else {
            $castsArray = '// Add your casts here';
        }

        $stub = str_replace('{{ casts }}', $castsArray, $stub);

        return $this;
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['collection', 'c', InputOption::VALUE_OPTIONAL, 'The Firestore collection name'],
            ['table', 't', InputOption::VALUE_OPTIONAL, 'The local database table name'],
            ['fillable', 'f', InputOption::VALUE_OPTIONAL, 'Comma-separated list of fillable attributes'],
            ['hidden', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of hidden attributes'],
            ['casts', null, InputOption::VALUE_OPTIONAL, 'Comma-separated list of casts (field:type)'],
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result !== false) {
            $this->info('Sync-enabled Firestore model created successfully.');
            $this->newLine();

            // Show next steps
            $this->info('Next steps:');
            $this->line('1. Configure sync mapping in config/firebase-models.php');
            $this->line('2. Create the local database table migration');
            $this->line('3. Run: php artisan firebase:sync:status to verify setup');

            $collection = $this->option('collection') ?: Str::snake(Str::pluralStudly(class_basename($this->argument('name'))));
            $table = $this->option('table') ?: $collection;

            $this->newLine();
            $this->info('Example sync mapping configuration:');
            $this->line("'sync' => [");
            $this->line("    'mappings' => [");
            $this->line("        '{$collection}' => [");
            $this->line("            'table' => '{$table}',");
            $this->line("            'columns' => [");
            $this->line("                // 'firestore_field' => 'local_column',");
            $this->line('            ],');
            $this->line('        ],');
            $this->line('    ],');
            $this->line('],');
        }

        return $result;
    }
}
