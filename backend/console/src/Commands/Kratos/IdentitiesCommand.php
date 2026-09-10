<?php

declare(strict_types=1);

namespace Console\Commands\Kratos;

use Common\Infra\Kratos\KratosAdminClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;
use Yiisoft\Yii\Console\ExitCode;

/** Lists identities from Kratos. Used to check the connection works. */
final class IdentitiesCommand extends Command
{
    public function __construct(
        private readonly KratosAdminClient $kratos,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Lists identities stored in Ory Kratos.')
            ->addOption('page', null, InputOption::VALUE_REQUIRED, 'Page number.', '1')
            ->addOption('per-page', null, InputOption::VALUE_REQUIRED, 'Identities per page.', '25');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $identities = $this->kratos->listIdentities(
                (int) $input->getOption('page'),
                (int) $input->getOption('per-page'),
            );
        } catch (Throwable $e) {
            $io->error($e->getMessage());
            return ExitCode::UNSPECIFIED_ERROR;
        }

        $io->table(
            ['identity id', 'email', 'name', 'lang', 'active', 'verified'],
            array_map(static fn($i): array => [
                $i->id->value(),
                $i->email->value(),
                $i->name->value() ?? '-',
                $i->language->value,
                $i->active ? 'yes' : 'no',
                $i->emailVerified ? 'yes' : 'no',
            ], $identities),
        );
        $io->success(sprintf('%d identities.', count($identities)));

        return ExitCode::OK;
    }
}
