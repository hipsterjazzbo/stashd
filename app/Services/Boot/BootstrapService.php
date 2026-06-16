<?php

declare(strict_types=1);

namespace App\Services\Boot;

use App\Domain\Command\CommandType;
use App\Domain\Job\JobIntent;
use App\Domain\Support\PrefixedUlid;
use App\Infrastructure\Persistence\CommandRepository;
use App\Infrastructure\Persistence\JobRepository;
use App\Services\Storage\StorageCapabilityChecker;
use App\Services\Storage\StorageRootService;
use Tempest\Database\Config\SQLiteConfig;

final readonly class BootstrapService
{
    public function __construct(
        private StorageRootService $storageRoots,
        private StorageCapabilityChecker $storageChecks,
        private SqliteConfigurator $sqlite,
        private MigrationRunner $migrations,
        private CommandRepository $commands,
        private JobRepository $jobs,
    ) {
    }

    /** @return array{directories_created: list<string>, command_id: string, job_id: string} */
    public function boot(SQLiteConfig $sqliteConfig): array
    {
        $created = $this->storageRoots->ensureDirectories();
        $this->sqlite->configure($sqliteConfig);
        $this->migrations->run($sqliteConfig);
        $this->storageChecks->checkAll();

        $command = $this->commands->create(CommandType::SystemBoot);
        $job = $this->jobs->create(
            intent: JobIntent::Boot,
            commandId: PrefixedUlid::parse((string) $command->id),
            entityType: 'system',
            payload: ['phase' => 'boot'],
        );

        return [
            'directories_created' => $created,
            'command_id' => (string) $command->id,
            'job_id' => (string) $job->id,
        ];
    }
}
