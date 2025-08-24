<?php

/**
 * PHPUnit 12 Modernization Tool
 *
 * Converts Pest PHP describe/it structure to PHPUnit class methods with #[Test] attributes.
 *
 * Usage: php scripts/convert-describe-it.php <file-path>
 */
if ($argc < 2) {
    echo "Usage: php scripts/convert-describe-it.php <file-path>\n";
    exit(1);
}

$filePath = $argv[1];

if (!file_exists($filePath)) {
    echo "Error: File not found: $filePath\n";
    exit(1);
}

class DescribeItConverter
{
    private string $content;

    private array $testMethods = [];

    private string $className;

    private string $namespace;

    private array $imports = [];

    private array $modelClasses = [];

    private bool $hasSetUp = false;

    private bool $hasTearDown = false;

    private string $setUpBody = '';

    private string $tearDownBody = '';

    public function __construct(string $filePath)
    {
        $this->content = file_get_contents($filePath);
        $this->extractClassName($filePath);
        $this->extractNamespace($filePath);
    }

    private function extractClassName(string $filePath): void
    {
        $fileName = basename($filePath, '.php');
        $this->className = $fileName;
    }

    private function extractNamespace(string $filePath): void
    {
        // Extract namespace from file path
        $relativePath = str_replace(getcwd().'/', '', dirname($filePath));
        $namespaceParts = explode('/', $relativePath);

        // Convert tests/Unit/... to JTD\FirebaseModels\Tests\Unit\...
        if ($namespaceParts[0] === 'tests') {
            $namespaceParts[0] = 'JTD\\FirebaseModels\\Tests';
            $this->namespace = implode('\\', $namespaceParts);
        } else {
            $this->namespace = 'JTD\\FirebaseModels\\Tests\\Unit';
        }
    }

    public function convert(): string
    {
        $this->extractImports();
        $this->extractModelClasses();
        $this->extractTestMethods();
        $this->extractHooks();

        return $this->generateClassStructure();
    }

    private function extractModelClasses(): void
    {
        // Extract any test model/helper classes defined in the file (before describe blocks)
        $describePos = strpos($this->content, 'describe(');
        if ($describePos === false) {
            return;
        }

        $beforeDescribe = substr($this->content, 0, $describePos);

        // Match classes that extend something OR use traits
        preg_match_all('/class\s+(\w+)(?:\s+extends\s+[^\{]+)?\s*\{.*?\n\}/s', $beforeDescribe, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $this->modelClasses[] = $match[0];
            echo 'Found class: '.$match[1]."\n";
        }
    }

    private function extractImports(): void
    {
        // Extract existing use statements
        preg_match_all('/^use\s+([^;]+);/m', $this->content, $matches);
        $this->imports = $matches[1] ?? [];

        // Add required imports for PHPUnit
        $requiredImports = [
            'JTD\\FirebaseModels\\Tests\\TestSuites\\UnitTestSuite',
            'PHPUnit\\Framework\\Attributes\\Test',
        ];

        foreach ($requiredImports as $import) {
            if (!in_array($import, $this->imports)) {
                $this->imports[] = $import;
            }
        }
    }

    private function extractTestMethods(): void
    {
        // Find all it() functions with better regex to handle nested braces
        $pattern = '/it\([\'"]([^\'\"]+)[\'"],\s*function\s*\(\)\s*\{/';
        preg_match_all($pattern, $this->content, $matches, PREG_OFFSET_CAPTURE);

        echo 'Found '.count($matches[0])." it() functions\n";

        foreach ($matches[0] as $index => $match) {
            $description = $matches[1][$index][0];
            $startPos = $match[1] + strlen($match[0]);

            echo "Processing: $description\n";

            // Find the matching closing brace
            $body = $this->extractMethodBody($startPos);

            if ($body !== null) {
                $methodName = $this->generateMethodName($description);
                $this->testMethods[] = [
                    'name' => $methodName,
                    'description' => $description,
                    'body' => $this->cleanMethodBody($body),
                ];
                echo "  -> Converted to: $methodName\n";
            } else {
                echo "  -> Failed to extract body\n";
            }
        }
    }

    private function extractMethodBody(int $startPos): ?string
    {
        $braceCount = 1;
        $pos = $startPos;
        $length = strlen($this->content);

        while ($pos < $length && $braceCount > 0) {
            $char = $this->content[$pos];
            if ($char === '{') {
                $braceCount++;
            } elseif ($char === '}') {
                $braceCount--;
            }
            $pos++;
        }

        if ($braceCount === 0) {
            // Found matching brace, extract body (excluding the closing brace and });)
            $bodyEnd = $pos - 1;
            $body = substr($this->content, $startPos, $bodyEnd - $startPos);

            return $body;
        }

        return null;
    }

    private function extractHooks(): void
    {
        // Check for beforeEach with better pattern matching
        $beforeEachPattern = '/beforeEach\(function\s*\(\)\s*\{/';
        if (preg_match($beforeEachPattern, $this->content, $matches, PREG_OFFSET_CAPTURE)) {
            $startPos = $matches[0][1] + strlen($matches[0][0]);
            $body = $this->extractMethodBody($startPos);
            if ($body !== null) {
                $this->hasSetUp = true;
                $this->setUpBody = $this->cleanMethodBody($body);
                echo "Found beforeEach hook\n";
            }
        }

        // Check for afterEach with better pattern matching
        $afterEachPattern = '/afterEach\(function\s*\(\)\s*\{/';
        if (preg_match($afterEachPattern, $this->content, $matches, PREG_OFFSET_CAPTURE)) {
            $startPos = $matches[0][1] + strlen($matches[0][0]);
            $body = $this->extractMethodBody($startPos);
            if ($body !== null) {
                $this->hasTearDown = true;
                $this->tearDownBody = $this->cleanMethodBody($body);
                echo "Found afterEach hook\n";
            }
        }
    }

    private function generateMethodName(string $description): string
    {
        // Convert description to snake_case method name
        $name = strtolower($description);

        // Replace common words and clean up
        $replacements = [
            'can ' => '',
            'should ' => '',
            'will ' => '',
            'does ' => '',
            'is ' => '',
            'has ' => '',
            'gets ' => '',
            'sets ' => '',
        ];

        foreach ($replacements as $search => $replace) {
            $name = str_replace($search, $replace, $name);
        }

        // Remove special characters and convert spaces to underscores
        $name = preg_replace('/[^a-z0-9\s]/', '', $name);
        $name = preg_replace('/\s+/', '_', trim($name));

        // Ensure it starts with "it_" or "test_"
        if (!str_starts_with($name, 'it_') && !str_starts_with($name, 'test_')) {
            $name = 'it_'.$name;
        }

        // Ensure unique method names
        $originalName = $name;
        $counter = 1;
        while (in_array($name, array_column($this->testMethods, 'name'))) {
            $name = $originalName.'_'.$counter;
            $counter++;
        }

        return $name;
    }

    private function cleanMethodBody(string $body): string
    {
        // Remove extra indentation and clean up the body
        $lines = explode("\n", $body);
        $cleanLines = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (!empty($trimmed)) {
                // Remove excessive indentation but preserve relative indentation
                $cleanLines[] = '        '.ltrim($line);
            }
        }

        return implode("\n", $cleanLines);
    }

    private function generateClassStructure(): string
    {
        $output = "<?php\n\n";
        $output .= "namespace {$this->namespace};\n\n";

        // Add imports
        foreach ($this->imports as $import) {
            $output .= "use {$import};\n";
        }

        $output .= "\n";

        // Add model classes if any
        foreach ($this->modelClasses as $modelClass) {
            $output .= "// Test model class\n";
            $output .= $modelClass."\n\n";
        }

        $output .= "/**\n";
        $output .= " * {$this->className}\n";
        $output .= " * \n";
        $output .= " * Converted from describe/it structure to PHPUnit class methods.\n";
        $output .= " * Generated by PHPUnit 12 Modernization Tool.\n";
        $output .= " */\n";
        $output .= "class {$this->className} extends UnitTestSuite\n{\n";

        // Add setUp method if needed
        if ($this->hasSetUp) {
            $output .= "    protected function setUp(): void\n";
            $output .= "    {\n";
            $output .= "        parent::setUp();\n";
            $output .= $this->setUpBody."\n";
            $output .= "    }\n\n";
        }

        // Add tearDown method if needed
        if ($this->hasTearDown) {
            $output .= "    protected function tearDown(): void\n";
            $output .= "    {\n";
            $output .= $this->tearDownBody."\n";
            $output .= "        parent::tearDown();\n";
            $output .= "    }\n\n";
        }

        // Add test methods
        foreach ($this->testMethods as $method) {
            $output .= "    #[Test]\n";
            $output .= "    public function {$method['name']}()\n";
            $output .= "    {\n";
            $output .= $method['body']."\n";
            $output .= "    }\n\n";
        }

        $output .= "}\n";

        return $output;
    }
}

// Main execution
try {
    echo "Converting describe/it structure in: $filePath\n";

    $converter = new DescribeItConverter($filePath);
    $convertedContent = $converter->convert();

    // Create backup
    $backupPath = $filePath.'.backup';
    copy($filePath, $backupPath);
    echo "Created backup: $backupPath\n";

    // Write converted content
    file_put_contents($filePath, $convertedContent);
    echo "Conversion complete!\n";
    echo "Original file backed up and new structure written.\n";
} catch (Exception $e) {
    echo 'Error: '.$e->getMessage()."\n";
    exit(1);
}
