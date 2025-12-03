<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:test-password-reset',
    description: 'Simulate password reset flow (generates token and shows reset link)',
)]
class TestPasswordResetCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('email', InputArgument::REQUIRED, 'User email address');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');

        $io->title('Password Reset Flow Test');

        // Step 1: Find user
        $io->section('Step 1: Finding User');
        $repo = $this->entityManager->getRepository(User::class);
        $user = $repo->findOneBy(['email' => $email]);

        if (!$user) {
            $io->error("User with email '$email' not found!");
            $io->note("Available users:");

            $users = $repo->findAll();
            foreach ($users as $u) {
                $io->text("  - {$u->getEmail()} (username: {$u->getUsername()})");
            }

            return Command::FAILURE;
        }

        $io->success("User found!");
        $io->text([
            "ID: {$user->getId()}",
            "Email: {$user->getEmail()}",
            "Username: {$user->getUsername()}",
            "First Name: {$user->getFirstName()}",
            "Last Name: {$user->getLastName()}",
        ]);

        // Step 2: Generate reset token
        $io->section('Step 2: Generating Reset Token');
        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);
        $this->entityManager->flush();

        $io->success("Token generated and saved!");
        $io->text([
            "Token (first 20 chars): " . substr($token, 0, 20) . "...",
            "Token length: " . strlen($token) . " characters",
        ]);

        // Step 3: Build reset link
        $io->section('Step 3: Reset Link');
        $resetLink = getenv('APP_URL') . "/reset-password?token=" . $token;

        $io->success("Reset link generated!");
        $io->text($resetLink);

        // Step 4: Show what email would contain
        $io->section('Step 4: Email Content Preview');
        $userName = $user->getFirstName() ?: $user->getUsername();

        $io->text([
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
            "Email would be sent to: {$user->getEmail()}",
            "Subject: Password Reset Request",
            "",
            "Hello $userName,",
            "",
            "We received a request to reset your password.",
            "Click the button/link below to reset your password:",
            "",
            "🔗 $resetLink",
            "",
            "This link can only be used once.",
            "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━",
        ]);

        // Step 5: Test token validation
        $io->section('Step 5: Verifying Token Can Be Found');
        $userByToken = $repo->findOneBy(['resetToken' => $token]);

        if ($userByToken && $userByToken->getId() === $user->getId()) {
            $io->success("✓ Token can be found in database!");
        } else {
            $io->error("✗ Token not found in database!");
            return Command::FAILURE;
        }

        // Step 6: Simulate password reset
        $io->section('Step 6: Simulating Password Reset');
        $newPassword = 'TestPassword123!';
        $hashedPassword = $this->passwordHasher->hashPassword($user, $newPassword);

        $io->text([
            "New password would be: $newPassword",
            "Hashed: " . substr($hashedPassword, 0, 30) . "...",
        ]);

        // Don't actually change password, just show what would happen
        $io->warning("Not actually changing password (this is just a test)");

        // Summary
        $io->section('Summary');
        $io->success('Password reset flow test completed successfully!');

        $io->text([
            "",
            "To test the ACTUAL flow via gRPC:",
            "",
            "1. Call ForgotPassword with email: $email",
            "   → This will send a REAL email to your inbox",
            "",
            "2. Check your email inbox for the reset link",
            "   → Look in spam folder if not in inbox",
            "",
            "3. Extract the token from the email link",
            "",
            "4. Call ResetPassword with:",
            "   - token: <from email>",
            "   - new_password: YourNewPassword",
            "",
            "5. You'll receive a JWT token for auto-login",
            "",
        ]);

        // Clean up - remove test token
        $user->setResetToken(null);
        $this->entityManager->flush();
        $io->note("Test token removed from database");

        return Command::SUCCESS;
    }
}
