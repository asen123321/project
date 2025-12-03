<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:test-email',
    description: 'Test email sending functionality',
)]
class TestEmailCommand extends Command
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(env: 'MAILER_FROM_EMAIL')]
        private string $fromEmail = 'noreply@localhost.com',
        #[Autowire(env: 'MAILER_FROM_NAME')]
        private string $fromName = 'Your App'
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Email address to send test email to');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $toEmail = $input->getArgument('to');

        $io->title('Testing Email Configuration');
        $io->info("From: {$this->fromName} <{$this->fromEmail}>");
        $io->info("To: {$toEmail}");

        try {
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($toEmail)
                ->subject('Test Email from Symfony')
                ->html('<h1>Email Test Successful!</h1><p>Your email configuration is working correctly.</p>');

            $this->mailer->send($email);

            $io->success('Email sent successfully! Check your inbox.');
            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to send email: ' . $e->getMessage());
            $io->note('Error details: ' . $e->getTraceAsString());
            return Command::FAILURE;
        }
    }
}
