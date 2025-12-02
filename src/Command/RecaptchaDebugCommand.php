<?php

namespace App\Command;

use App\Service\ReCaptchaService;
use App\Service\ReCaptchaMonitorService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Helper\Table;

#[AsCommand(
    name: 'app:recaptcha:debug',
    description: 'Debug and test reCAPTCHA configuration'
)]
class RecaptchaDebugCommand extends Command
{
    public function __construct(
        private ReCaptchaService $recaptchaService,
        private ReCaptchaMonitorService $monitorService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('test-token', 't', InputOption::VALUE_REQUIRED, 'Test a specific reCAPTCHA token')
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action to verify (default: test)', 'test')
            ->addOption('stats', 's', InputOption::VALUE_NONE, 'Show statistics')
            ->addOption('reset-stats', null, InputOption::VALUE_NONE, 'Reset statistics')
            ->addOption('check-config', 'c', InputOption::VALUE_NONE, 'Check configuration');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔒 reCAPTCHA v3 Debug Tool');

        // Check configuration
        if ($input->getOption('check-config')) {
            return $this->checkConfiguration($io);
        }

        // Show statistics
        if ($input->getOption('stats')) {
            return $this->showStatistics($io);
        }

        // Reset statistics
        if ($input->getOption('reset-stats')) {
            return $this->resetStatistics($io);
        }

        // Test token
        if ($token = $input->getOption('test-token')) {
            $action = $input->getOption('action');
            return $this->testToken($io, $token, $action);
        }

        // Default: show overview
        return $this->showOverview($io);
    }

    private function showOverview(SymfonyStyle $io): int
    {
        // Configuration status
        $io->section('Configuration Status');

        $isEnabled = $this->recaptchaService->isEnabled();
        $siteKey = $this->recaptchaService->getSiteKey();

        $io->horizontalTable(
            ['Setting', 'Value'],
            [
                ['Enabled', $isEnabled ? '✅ Yes' : '❌ No'],
                ['Site Key', $siteKey ?: '❌ Not configured'],
                ['Site Key (masked)', $siteKey ? substr($siteKey, 0, 10) . '...' : 'N/A'],
                ['Environment', $_ENV['APP_ENV'] ?? 'unknown'],
            ]
        );

        if (!$isEnabled) {
            $io->warning('reCAPTCHA is DISABLED. All verifications will pass without checking.');
            $io->note('To enable, set RECAPTCHA_SITE_KEY and RECAPTCHA_SECRET_KEY in your .env file');
        } else {
            $io->success('reCAPTCHA is ENABLED and configured');
        }

        // Quick stats
        $io->section('Quick Statistics (Last Hour)');
        $stats = $this->monitorService->getFailureStats(1);

        $io->horizontalTable(
            ['Metric', 'Value'],
            [
                ['Total Verifications', $stats['total_verifications']],
                ['Successful', $stats['successful']],
                ['Failed', $stats['failed']],
                ['Success Rate', $stats['success_rate'] . '%'],
                ['Missing Tokens', $stats['missing_tokens']],
                ['Attacks Detected', $stats['attacks_detected']],
            ]
        );

        // Available commands
        $io->section('Available Commands');
        $io->listing([
            'php bin/console app:recaptcha:debug --check-config   # Check configuration',
            'php bin/console app:recaptcha:debug --stats          # Show detailed statistics',
            'php bin/console app:recaptcha:debug --reset-stats    # Reset statistics',
            'php bin/console app:recaptcha:debug -t TOKEN -a ACTION  # Test a token',
        ]);

        return Command::SUCCESS;
    }

    private function checkConfiguration(SymfonyStyle $io): int
    {
        $io->section('🔍 Configuration Check');

        $checks = [];
        $hasErrors = false;

        // Check 1: Environment variables
        $siteKey = $_ENV['RECAPTCHA_SITE_KEY'] ?? null;
        $secretKey = $_ENV['RECAPTCHA_SECRET_KEY'] ?? null;

        if (empty($siteKey)) {
            $checks[] = ['❌', 'RECAPTCHA_SITE_KEY', 'Not set', 'Set in .env file'];
            $hasErrors = true;
        } else {
            $checks[] = ['✅', 'RECAPTCHA_SITE_KEY', 'Set (' . strlen($siteKey) . ' chars)', 'OK'];
        }

        if (empty($secretKey)) {
            $checks[] = ['❌', 'RECAPTCHA_SECRET_KEY', 'Not set', 'Set in .env file'];
            $hasErrors = true;
        } else {
            $checks[] = ['✅', 'RECAPTCHA_SECRET_KEY', 'Set (' . strlen($secretKey) . ' chars)', 'OK'];
        }

        // Check 2: Service enabled
        if ($this->recaptchaService->isEnabled()) {
            $checks[] = ['✅', 'Service Status', 'Enabled', 'OK'];
        } else {
            $checks[] = ['⚠️', 'Service Status', 'Disabled', 'Keys not configured'];
        }

        // Check 3: Environment
        $env = $_ENV['APP_ENV'] ?? 'unknown';
        $checks[] = ['ℹ️', 'Environment', $env, $env === 'prod' ? 'Production' : 'Non-production'];

        // Check 4: Score thresholds
        $loginThreshold = $_ENV['RECAPTCHA_MIN_SCORE_LOGIN'] ?? 0.4;
        $registerThreshold = $_ENV['RECAPTCHA_MIN_SCORE_REGISTER'] ?? 0.5;
        $bookingThreshold = $_ENV['RECAPTCHA_MIN_SCORE_BOOKING'] ?? 0.5;

        $checks[] = ['ℹ️', 'Login Threshold', $loginThreshold, 'Configured'];
        $checks[] = ['ℹ️', 'Register Threshold', $registerThreshold, 'Configured'];
        $checks[] = ['ℹ️', 'Booking Threshold', $bookingThreshold, 'Configured'];

        // Display table
        $table = new Table($output = $io);
        $table->setHeaders(['Status', 'Check', 'Value', 'Message']);
        $table->setRows($checks);
        $table->render();

        // Check 5: Test connectivity to Google (if enabled)
        if ($this->recaptchaService->isEnabled()) {
            $io->section('🌐 Google API Connectivity Test');

            $io->note('Testing connection to Google reCAPTCHA API...');

            try {
                // Use test token to verify connectivity
                $testToken = 'test_token_for_connectivity';
                $result = $this->recaptchaService->verify($testToken, 'test', null, 0.0);

                if (isset($result['error']) && $result['error'] === 'network-error') {
                    $io->error('❌ Cannot connect to Google reCAPTCHA API');
                    $hasErrors = true;
                } else {
                    $io->success('✅ Successfully connected to Google reCAPTCHA API');
                }
            } catch (\Exception $e) {
                $io->error('❌ Network error: ' . $e->getMessage());
                $hasErrors = true;
            }
        }

        // Security checks
        $io->section('🔐 Security Checks');

        $securityChecks = [];

        // Check if secret key is in version control
        if (file_exists('.env')) {
            $envContent = file_get_contents('.env');
            if (strpos($envContent, 'RECAPTCHA_SECRET_KEY=') !== false &&
                preg_match('/RECAPTCHA_SECRET_KEY=\S+/', $envContent)) {
                $securityChecks[] = ['⚠️', '.env file', 'Contains secret key', 'Should use .env.local instead'];
            } else {
                $securityChecks[] = ['✅', '.env file', 'No secrets', 'OK'];
            }
        }

        // Check .gitignore
        if (file_exists('.gitignore')) {
            $gitignoreContent = file_get_contents('.gitignore');
            if (strpos($gitignoreContent, '.env.local') !== false) {
                $securityChecks[] = ['✅', '.gitignore', 'Includes .env.local', 'OK'];
            } else {
                $securityChecks[] = ['❌', '.gitignore', 'Missing .env.local', 'Add to .gitignore'];
                $hasErrors = true;
            }
        }

        // Check HTTPS in production
        if ($env === 'prod') {
            $securityChecks[] = ['⚠️', 'HTTPS', 'Cannot verify', 'Ensure HTTPS enabled in production'];
        }

        $table = new Table($output = $io);
        $table->setHeaders(['Status', 'Check', 'Result', 'Action']);
        $table->setRows($securityChecks);
        $table->render();

        // Summary
        $io->newLine();
        if ($hasErrors) {
            $io->error('Configuration has issues. Please fix the errors above.');
            return Command::FAILURE;
        } else {
            $io->success('Configuration looks good! ✅');
            return Command::SUCCESS;
        }
    }

    private function showStatistics(SymfonyStyle $io): int
    {
        $io->section('📊 reCAPTCHA Statistics');

        // Overall stats
        $io->title('Overall Statistics (Last 24 Hours)');
        $stats = $this->monitorService->getFailureStats(24);

        $io->definitionList(
            ['Total Verifications' => $stats['total_verifications']],
            ['Successful' => $stats['successful'] . ' ✅'],
            ['Failed' => $stats['failed'] . ' ❌'],
            ['Success Rate' => $stats['success_rate'] . '%'],
            ['Average Score' => $stats['average_score']],
            ['Missing Tokens' => $stats['missing_tokens']],
            ['Attacks Detected' => $stats['attacks_detected']]
        );

        // Stats by action
        $io->newLine();
        $io->title('Statistics by Action');

        $actionStats = $this->monitorService->getStatsByAction();

        $rows = [];
        foreach ($actionStats as $action => $data) {
            $rows[] = [
                $action,
                $data['total'],
                $data['successful'],
                $data['failed'],
                $data['success_rate'] . '%',
                $data['average_score']
            ];
        }

        $table = new Table($output = $io);
        $table->setHeaders(['Action', 'Total', 'Success', 'Failed', 'Success Rate', 'Avg Score']);
        $table->setRows($rows);
        $table->render();

        // Score distribution
        $io->newLine();
        $io->section('📈 Score Distribution');

        $io->note('Score ranges indicate user authenticity:');
        $io->listing([
            '0.9-1.0: Excellent (very likely human)',
            '0.7-0.9: Good (likely human)',
            '0.5-0.7: Medium (possibly human)',
            '0.3-0.5: Low (suspicious)',
            '0.0-0.3: Very Low (very likely bot)'
        ]);

        return Command::SUCCESS;
    }

    private function resetStatistics(SymfonyStyle $io): int
    {
        $io->section('🔄 Reset Statistics');

        if (!$io->confirm('Are you sure you want to reset all statistics?', false)) {
            $io->note('Operation cancelled.');
            return Command::SUCCESS;
        }

        $this->monitorService->resetStats();

        $io->success('Statistics have been reset! ✅');

        return Command::SUCCESS;
    }

    private function testToken(SymfonyStyle $io, string $token, string $action): int
    {
        $io->section('🧪 Testing reCAPTCHA Token');

        $io->definitionList(
            ['Token (first 20 chars)' => substr($token, 0, 20) . '...'],
            ['Action' => $action],
            ['Timestamp' => date('Y-m-d H:i:s')]
        );

        if (!$this->recaptchaService->isEnabled()) {
            $io->warning('reCAPTCHA is disabled. Verification will automatically succeed.');
        }

        $io->note('Verifying token with Google...');

        try {
            $result = $this->recaptchaService->verify(
                token: $token,
                action: $action,
                remoteIp: null,
                minScore: 0.0 // Use 0.0 to see actual score
            );

            $io->newLine();

            if ($result['success']) {
                $io->success('✅ Verification SUCCESSFUL');

                $io->definitionList(
                    ['Score' => $result['score'] ?? 'N/A'],
                    ['Action' => $result['action'] ?? 'N/A'],
                    ['Challenge TS' => $result['challenge_ts'] ?? 'N/A'],
                    ['Hostname' => $result['hostname'] ?? 'N/A']
                );

                // Score interpretation
                $score = $result['score'] ?? 0;
                if ($score >= 0.9) {
                    $io->note('Score interpretation: EXCELLENT - Very likely human');
                } elseif ($score >= 0.7) {
                    $io->note('Score interpretation: GOOD - Likely human');
                } elseif ($score >= 0.5) {
                    $io->note('Score interpretation: MEDIUM - Possibly human');
                } elseif ($score >= 0.3) {
                    $io->note('Score interpretation: LOW - Suspicious');
                } else {
                    $io->warning('Score interpretation: VERY LOW - Very likely bot');
                }

                return Command::SUCCESS;

            } else {
                $io->error('❌ Verification FAILED');

                $io->definitionList(
                    ['Error' => $result['error'] ?? 'Unknown'],
                    ['Message' => $result['message'] ?? 'No message'],
                    ['Score' => $result['score'] ?? 'N/A']
                );

                // Common errors
                $error = $result['error'] ?? '';
                match ($error) {
                    'missing-input-response' => $io->note('The token was empty or missing'),
                    'invalid-input-response' => $io->note('The token is invalid or has expired'),
                    'timeout-or-duplicate' => $io->note('The token has expired (>2 minutes) or was already used'),
                    'low-score' => $io->note('The score was below the threshold (likely bot)'),
                    'action-mismatch' => $io->note('The action does not match'),
                    default => null
                };

                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $io->error('❌ Exception occurred during verification');
            $io->writeln('Error: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
