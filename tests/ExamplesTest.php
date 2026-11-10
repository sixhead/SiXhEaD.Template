<?php

declare(strict_types=1);

namespace SiXhEaD\Template\Tests;

use Memcached;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Redis;
use RedisException;

/**
 * Runs each example script in its own PHP process, the same way a web request
 * runs it, and compares the output with tests/fixtures/<name>.html.
 */
final class ExamplesTest extends TestCase
{
    private const string EXAMPLES = __DIR__ . '/../examples';

    private const string FIXTURES = __DIR__ . '/fixtures';

    /**
     * @return iterable<string, array{string, array<string, string>, array<string, string>}>
     */
    public static function examples(): iterable
    {
        yield 'simple' => ['simple', [], []];
        yield 'block-chrome' => ['block', [], ['HTTP_USER_AGENT' => 'Mozilla/5.0 Chrome/130.0']];
        yield 'block-firefox' => ['block', [], ['HTTP_USER_AGENT' => 'Mozilla/5.0 Firefox/132.0']];
        yield 'block-other' => ['block', [], ['HTTP_USER_AGENT' => 'curl/8.0']];
        yield 'block-advance-guest' => ['block-advance', [], ['REMOTE_ADDR' => '192.0.2.1']];
        yield 'block-advance-member' => ['block-advance', ['member' => '1'], []];
        yield 'loop-in-loop' => ['loop-in-loop', [], []];
        yield 'other1' => ['other1', [], []];
        yield 'other2' => ['other2', [], []];
        yield 'table' => ['table', [], []];
        yield 'table-item-6' => ['table', ['item' => '6'], []];
        yield 'table-item-6-fix' => ['table', ['item' => '6', 'fix' => '1'], []];
        yield 'table-item-8-fix' => ['table', ['item' => '8', 'fix' => '1'], []];
    }

    /**
     * @param array<string, string> $get
     * @param array<string, string> $server
     */
    #[DataProvider('examples')]
    public function testExampleMatchesFixture(string $script, array $get, array $server): void
    {
        $fixture = self::FIXTURES . '/' . $this->dataName() . '.html';
        self::assertStringEqualsFile($fixture, $this->runExample($script, $get, $server));
    }

    public function testTableItemIsLimitedTo100(): void
    {
        $html = $this->runExample('table', ['item' => '500'], []);
        self::assertSame(100, substr_count($html, '<td>'));
        self::assertStringContainsString('<td>100</td>', $html);
        self::assertStringNotContainsString('<td>101</td>', $html);
    }

    /**
     * Example 1 caches the whole page. Example 2 caches only the list, so the
     * `<h3>$now</h3>` heading is rendered fresh on every request.
     *
     * @return iterable<string, array{string, bool}>
     */
    public static function memcachedExamples(): iterable
    {
        yield 'memcached1' => ['memcached1', true];
        yield 'memcached2' => ['memcached2', false];
    }

    #[DataProvider('memcachedExamples')]
    #[Group('integration')]
    #[RequiresPhpExtension('memcached')]
    public function testMemcachedExampleServesCachedData(string $script, bool $caches_page): void
    {
        $memcached = new Memcached();
        $memcached->addServer(getenv('MEMCACHED_HOST') ?: 'memcached', 11211);
        $memcached->delete("example_{$script}");
        if (!in_array($memcached->getResultCode(), [Memcached::RES_SUCCESS, Memcached::RES_NOTFOUND], true)) {
            self::markTestSkipped('Memcached server is not reachable: ' . $memcached->getResultMessage());
        }
        $this->assertServesCachedData($script, $caches_page);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function redisExamples(): iterable
    {
        yield 'redis1' => ['redis1', true];
        yield 'redis2' => ['redis2', false];
    }

    #[DataProvider('redisExamples')]
    #[Group('integration')]
    #[RequiresPhpExtension('redis')]
    public function testRedisExampleServesCachedData(string $script, bool $caches_page): void
    {
        try {
            $redis = new Redis(['host' => getenv('REDIS_HOST') ?: 'redis', 'port' => 6379, 'connectTimeout' => 1.0]);
            $redis->del("example_{$script}");
        } catch (RedisException $e) {
            self::markTestSkipped('Redis server is not reachable: ' . $e->getMessage());
        }
        $this->assertServesCachedData($script, $caches_page);
    }

    private function assertServesCachedData(string $script, bool $caches_page): void
    {
        $first = $this->runExample($script, [], []);
        sleep(1);
        $second = $this->runExample($script, [], []);

        $time = '\d{4}-\d\d-\d\d \d\d:\d\d:\d\d';
        self::assertMatchesRegularExpression("/<li>5\\. {$time}<\\/li>/", $first);
        if ($caches_page) {
            self::assertSame($first, $second);
            return;
        }
        preg_match_all("/<li>\\d\\. ({$time})<\\/li>/", $first, $first_items);
        preg_match_all("/<li>\\d\\. ({$time})<\\/li>/", $second, $second_items);
        self::assertSame($first_items[1], $second_items[1]);
        self::assertNotSame($first, $second);
    }

    /**
     * @param array<string, string> $get
     * @param array<string, string> $server
     */
    private function runExample(string $script, array $get, array $server): string
    {
        $code = '$_GET = json_decode($argv[2], true);'
            . ' $_SERVER = array_merge($_SERVER, json_decode($argv[3], true));'
            . ' require $argv[1];';
        $command = [
            PHP_BINARY,
            '-d', 'error_reporting=-1',
            '-d', 'display_errors=stderr',
            '-d', 'log_errors=0',
            '-r', $code,
            '--',
            self::EXAMPLES . "/{$script}.php",
            json_encode((object) $get, JSON_THROW_ON_ERROR),
            json_encode((object) $server, JSON_THROW_ON_ERROR),
        ];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, self::EXAMPLES);
        self::assertIsResource($process);
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit_code = proc_close($process);

        self::assertSame('', $stderr, "{$script}.php wrote errors");
        self::assertSame(0, $exit_code, "{$script}.php exit code");
        return $stdout;
    }
}
