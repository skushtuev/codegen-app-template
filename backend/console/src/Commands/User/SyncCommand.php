<?php

declare(strict_types=1);

namespace Console\Commands\User;

use Common\App\Service\User\Service as UserService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Yiisoft\Yii\Console\ExitCode;

/** Mirrors every Kratos identity into the users table. Repairs rows lost by a failed webhook. */
final class SyncCommand extends Command
{
    public function __construct(
        private readonly UserService $userService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Syncs users from Ory Kratos identities.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $synced = $this->userService->syncAllFromKratos();
        } catch (Throwable $e) {
            $io->error($e->getMessage());

            return ExitCode::UNSPECIFIED_ERROR;
        }

        $io->success(sprintf('%d identities synced.', $synced));

        return ExitCode::OK;
    }
}
