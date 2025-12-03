<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:diagnose-email',
    description: 'Diagnose email configuration and SMTP connectivity',
)]
class DiagnoseEmailCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('Email Configuration Diagnostics');

        // Check environment variables
        $io->section('1. Environment Variables');
        $vars = [
            'MAILER_DSN' => getenv('MAILER_DSN'),
            'MAILER_FROM_EMAIL' => getenv('MAILER_FROM_EMAIL'),
            'MAILER_FROM_NAME' => getenv('MAILER_FROM_NAME'),
            'APP_URL' => getenv('APP_URL'),
        ];

        foreach ($vars as $key => $value) {
            if ($value) {
                // Mask password in DSN
                $displayValue = $key === 'MAILER_DSN'
                    ? preg_replace('/:[^:@]+@/', ':****@', $value)
                    : $value;
                $io->success("$key: $displayValue");
            } else {
                $io->error("$key: NOT SET");
            }
        }

        // Check PHP extensions
        $io->section('2. PHP Extensions for SMTP');
        $extensions = ['openssl', 'sockets', 'curl', 'mbstring', 'iconv'];
        foreach ($extensions as $ext) {
            if (extension_loaded($ext)) {
                $io->success("✓ $ext");
            } else {
                $io->error("✗ $ext (MISSING)");
            }
        }

        // Check OpenSSL
        $io->section('3. OpenSSL Configuration');
        if (extension_loaded('openssl')) {
            $io->text('OpenSSL Version: ' . OPENSSL_VERSION_TEXT);
            $io->text('Stream transports: ' . implode(', ', stream_get_transports()));
        }

        // Check network connectivity to Gmail SMTP
        $io->section('4. Gmail SMTP Connectivity Test');
        $host = 'smtp.gmail.com';
        $port = 587;

        $io->text("Testing connection to $host:$port...");

        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);

        if ($socket) {
            $io->success("✓ Connected to $host:$port");
            fclose($socket);
        } else {
            $io->error("✗ Cannot connect to $host:$port - Error: $errstr ($errno)");
        }

        // Try TLS connection
        $io->section('5. TLS/SSL Connection Test');
        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $socket = @stream_socket_client(
            "ssl://$host:465",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if ($socket) {
            $io->success("✓ SSL connection to $host:465 successful");
            fclose($socket);
        } else {
            $io->error("✗ SSL connection failed: $errstr ($errno)");
        }

        // Parse and validate MAILER_DSN
        $io->section('6. MAILER_DSN Analysis');
        $dsn = getenv('MAILER_DSN');
        if ($dsn) {
            if (preg_match('/^([^:]+):\/\/([^:]+):([^@]+)@(.+)$/', $dsn, $matches)) {
                $protocol = $matches[1];
                $username = urldecode($matches[2]);
                $password = $matches[3];
                $server = $matches[4];

                $io->text("Protocol: $protocol");
                $io->text("Username: $username");
                $io->text("Password length: " . strlen($password) . " characters");
                $io->text("Server: $server");

                if (strlen($password) !== 16) {
                    $io->warning("Gmail App Passwords should be exactly 16 characters!");
                    $io->text("Your password is " . strlen($password) . " characters");
                }

                if (strpos($username, '@') === false) {
                    $io->error("Username should include @gmail.com");
                }
            } else {
                $io->error("Cannot parse MAILER_DSN format");
            }
        }

        $io->section('7. Recommendations');
        $io->text([
            'If Gmail authentication is failing:',
            '',
            '1. Visit https://myaccount.google.com/security',
            '2. Ensure 2-Factor Authentication is ENABLED',
            '3. Go to https://myaccount.google.com/apppasswords',
            '4. Generate a NEW App Password specifically for "Mail"',
            '5. Update MAILER_DSN with the new 16-character password',
            '6. Remove any spaces from the password',
            '',
            'Common issues:',
            '- App password was revoked or expired',
            '- 2FA not enabled on Google account',
            '- "Less secure app access" blocking (should not be an issue with app passwords)',
            '- Account security alerts from Google',
        ]);

        return Command::SUCCESS;
    }
}
