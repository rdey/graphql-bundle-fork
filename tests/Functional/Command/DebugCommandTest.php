<?php

declare(strict_types=1);

namespace Redeye\GraphQLBundle\Tests\Functional\Command;

use InvalidArgumentException;
use Redeye\GraphQLBundle\Command\DebugCommand;
use Redeye\GraphQLBundle\Tests\Functional\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use function file_get_contents;
use function sprintf;

class DebugCommandTest extends TestCase
{
    private CommandTester $commandTester;
    private array $logs = [];

    public function setUp(): void
    {
        parent::setUp();
        static::bootKernel(['test_case' => 'mutation']);

        $command = static::$kernel->getContainer()->get(DebugCommand::class);
        $this->commandTester = new CommandTester($command);

        foreach (DebugCommand::getCategories() as $category) {
            $content = (string) file_get_contents(
                sprintf(
                    __DIR__ . '/fixtures/debug/debug-%s.txt',
                    $category
                )
            );

            $this->logs[$category] = $content;
        }
    }

    /**
     * @dataProvider categoryDataProvider
     */
    public function testProcess(array $categories): void
    {
        if (empty($categories)) {
            $categories = DebugCommand::getCategories();
        }

        $this->commandTester->execute(['--category' => $categories]);
        $this->assertSame(0, $this->commandTester->getStatusCode());

        foreach ($categories as $category) {
            $this->assertStringContainsString(
                str_replace('\n', '', $this->logs[$category]),
                $this->commandTester->getDisplay(),
            );
        }
    }

    public function testInvalidFormat(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid category (fake)');
        $this->commandTester->execute([
            '--category' => 'fake',
        ]);
    }

    public function categoryDataProvider(): array
    {
        return [
            [[]],
            [['type']],
            [['query']],
            [['mutation']],
            [['type', 'mutation']],
        ];
    }
}
