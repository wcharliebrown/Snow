<?php
/**
 * Snow Framework Test Runner
 * A lightweight test framework for Snow (no external dependencies)
 */

class SnowTestRunner {
    private array $tests = [];
    private int $passed = 0;
    private int $failed = 0;
    private int $skipped = 0;
    private array $failures = [];

    public function describe(string $suite, callable $fn): void {
        echo "\n\033[1;34m=== $suite ===\033[0m\n";
        $fn($this);
    }

    public function it(string $name, callable $fn): void {
        try {
            $fn($this);
            echo "  \033[32m✓\033[0m $name\n";
            $this->passed++;
        } catch (SkipException $e) {
            echo "  \033[33m○\033[0m $name [SKIPPED: {$e->getMessage()}]\n";
            $this->skipped++;
        } catch (AssertionException $e) {
            echo "  \033[31m✗\033[0m $name\n";
            echo "    \033[31m→ {$e->getMessage()}\033[0m\n";
            $this->failed++;
            $this->failures[] = ['suite' => '', 'test' => $name, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            echo "  \033[31m✗\033[0m $name\n";
            echo "    \033[31m→ " . get_class($e) . ': ' . $e->getMessage() . "\033[0m\n";
            $this->failed++;
            $this->failures[] = ['suite' => '', 'test' => $name, 'error' => get_class($e) . ': ' . $e->getMessage()];
        }
    }

    public function assertEqual($expected, $actual, string $message = ''): void {
        if ($expected !== $actual) {
            $exp = var_export($expected, true);
            $act = var_export($actual, true);
            throw new AssertionException($message ?: "Expected $exp but got $act");
        }
    }

    public function assertNotEqual($expected, $actual, string $message = ''): void {
        if ($expected === $actual) {
            $val = var_export($expected, true);
            throw new AssertionException($message ?: "Expected value not to equal $val");
        }
    }

    public function assertTrue($value, string $message = ''): void {
        if (!$value) {
            $val = var_export($value, true);
            throw new AssertionException($message ?: "Expected true but got $val");
        }
    }

    public function assertFalse($value, string $message = ''): void {
        if ($value) {
            $val = var_export($value, true);
            throw new AssertionException($message ?: "Expected false but got $val");
        }
    }

    public function assertNull($value, string $message = ''): void {
        if ($value !== null) {
            $val = var_export($value, true);
            throw new AssertionException($message ?: "Expected null but got $val");
        }
    }

    public function assertNotNull($value, string $message = ''): void {
        if ($value === null) {
            throw new AssertionException($message ?: "Expected non-null value but got null");
        }
    }

    public function assertContains(string $needle, string $haystack, string $message = ''): void {
        if (strpos($haystack, $needle) === false) {
            throw new AssertionException($message ?: "Expected '$haystack' to contain '$needle'");
        }
    }

    public function assertNotContains(string $needle, string $haystack, string $message = ''): void {
        if (strpos($haystack, $needle) !== false) {
            throw new AssertionException($message ?: "Expected '$haystack' not to contain '$needle'");
        }
    }

    public function assertCount(int $expectedCount, array $array, string $message = ''): void {
        $actual = count($array);
        if ($expectedCount !== $actual) {
            throw new AssertionException($message ?: "Expected count $expectedCount but got $actual");
        }
    }

    public function assertGreaterThan($min, $actual, string $message = ''): void {
        if ($actual <= $min) {
            throw new AssertionException($message ?: "Expected $actual to be greater than $min");
        }
    }

    public function assertIsArray($value, string $message = ''): void {
        if (!is_array($value)) {
            $type = gettype($value);
            throw new AssertionException($message ?: "Expected array but got $type");
        }
    }

    public function assertIsInt($value, string $message = ''): void {
        if (!is_int($value)) {
            $type = gettype($value);
            throw new AssertionException($message ?: "Expected int but got $type");
        }
    }

    public function assertIsString($value, string $message = ''): void {
        if (!is_string($value)) {
            $type = gettype($value);
            throw new AssertionException($message ?: "Expected string but got $type");
        }
    }

    public function assertMatchesRegex(string $pattern, string $value, string $message = ''): void {
        if (!preg_match($pattern, $value)) {
            throw new AssertionException($message ?: "Expected '$value' to match pattern '$pattern'");
        }
    }

    public function skip(string $reason): void {
        throw new SkipException($reason);
    }

    public function summary(): void {
        $total = $this->passed + $this->failed + $this->skipped;
        echo "\n\033[1m=== TEST RESULTS ===\033[0m\n";
        echo "Total:   $total\n";
        echo "\033[32mPassed:  {$this->passed}\033[0m\n";
        if ($this->failed > 0) {
            echo "\033[31mFailed:  {$this->failed}\033[0m\n";
        }
        if ($this->skipped > 0) {
            echo "\033[33mSkipped: {$this->skipped}\033[0m\n";
        }

        if (!empty($this->failures)) {
            echo "\n\033[1;31mFailed Tests:\033[0m\n";
            foreach ($this->failures as $f) {
                echo "  - {$f['test']}\n";
                echo "    {$f['error']}\n";
            }
        }

        echo "\n";
        if ($this->failed === 0) {
            echo "\033[1;32m✓ All tests passed!\033[0m\n\n";
            exit(0);
        } else {
            echo "\033[1;31m✗ {$this->failed} test(s) failed.\033[0m\n\n";
            exit(1);
        }
    }
}

class AssertionException extends RuntimeException {}
class SkipException extends RuntimeException {}
