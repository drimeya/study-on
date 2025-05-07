<?php

namespace App\Command;

use App\Service\TokenService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cleanup-expired-tokens',
    description: 'Очищает истекшие API токены',
)]
class CleanupExpiredTokensCommand extends Command
{
    public function __construct(
        private TokenService $tokenService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->info('Начинаем очистку истекших токенов...');

        try {
            $deletedCount = $this->tokenService->cleanupExpiredTokens();
            
            $io->success(sprintf('Удалено %d истекших токенов', $deletedCount));
            
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Ошибка при очистке токенов: ' . $e->getMessage());
            
            return Command::FAILURE;
        }
    }
}
