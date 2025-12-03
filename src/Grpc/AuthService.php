<?php

namespace App\Grpc;

use App\Entity\User;
use App\Grpc\Auth\V1\GoogleLoginRequest;
use App\Grpc\Auth\V1\LoginResponse;
use App\Grpc\Auth\V1\RegisterRequest;
use App\Grpc\Auth\V1\LoginRequest;
use App\Grpc\Auth\V1\ForgotPasswordRequest;
use App\Grpc\Auth\V1\ForgotPasswordResponse;
use App\Grpc\Auth\V1\ResetPasswordRequest;
use Doctrine\ORM\EntityManagerInterface;
use Google\Client;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Spiral\RoadRunner\GRPC\Exception\GRPCException;
use Spiral\RoadRunner\GRPC\StatusCode;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

class AuthService implements AuthServiceInterface
{
    public function __construct(
        private EntityManagerInterface      $entityManager,
        private JWTTokenManagerInterface    $jwtManager,
        private UserPasswordHasherInterface $passwordHasher,
        #[Autowire(env: 'GOOGLE_CLIENT_ID')]
        private string                      $googleClientId,
        private MailerInterface             $mailer,
        private LoggerInterface             $logger,
        #[Autowire(env: 'APP_URL')]
        private string                      $appUrl = 'http://localhost',
        #[Autowire(env: 'MAILER_FROM_EMAIL')]
        private string                      $fromEmail = 'noreply@localhost.com',
        #[Autowire(env: 'MAILER_FROM_NAME')]
        private string                      $fromName = 'Your App'
    )
    {
    }

    // --- 1. Google Login ---
    public function GoogleLogin(ContextInterface $ctx, GoogleLoginRequest $request): LoginResponse
    {
        $idToken = $request->getIdToken();
        $client = new Client(['client_id' => $this->googleClientId]);

        try {
            $payload = $client->verifyIdToken($idToken);
        } catch (\Exception $e) {
            throw new \Exception("Google Verification Error: " . $e->getMessage());
        }

        if (!$payload) {
            throw new \Exception("Invalid Google Token");
        }

        $email = $payload['email'];
        $repo = $this->entityManager->getRepository(User::class);
        $user = $repo->findOneBy(['email' => $email]);

        if (!$user) {
            $user = new User();
            $user->setEmail($email);
            $user->setUsername($email);
            $user->setPassword($this->passwordHasher->hashPassword($user, bin2hex(random_bytes(16))));
            $user->setRoles(['ROLE_USER']);
            $this->entityManager->persist($user);
            $this->entityManager->flush();
        }

        $token = $this->jwtManager->create($user);
        $response = new LoginResponse();
        $response->setAppToken($token);

        return $response;
    }

    // --- 2. Register ---
    public function Register(ContextInterface $ctx, RegisterRequest $request): LoginResponse
    {
        $repo = $this->entityManager->getRepository(User::class);

        // Check if email exists
        if ($repo->findOneBy(['email' => $request->getEmail()])) {
            throw new GRPCException("Email already exists", StatusCode::ALREADY_EXISTS);
        }

        // Check if username exists
        if ($repo->findOneBy(['username' => $request->getUsername()])) {
            throw new GRPCException("Username already exists", StatusCode::ALREADY_EXISTS);
        }

        $user = new User();
        $user->setEmail($request->getEmail());
        $user->setUsername($request->getUsername());
        $user->setFirstName($request->getFirstName());
        $user->setLastName($request->getLastName());

        $hashed = $this->passwordHasher->hashPassword($user, $request->getPassword());
        $user->setPassword($hashed);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);
        $response = new LoginResponse();
        $response->setAppToken($token);
        return $response;
    }

    // --- 3. Login ---
    public function Login(ContextInterface $ctx, LoginRequest $request): LoginResponse
    {
        $repo = $this->entityManager->getRepository(User::class);
        $user = $repo->findOneBy(['email' => $request->getEmail()]);

        if (!$user || !$this->passwordHasher->isPasswordValid($user, $request->getPassword())) {
            throw new GRPCException("Invalid credentials", StatusCode::UNAUTHENTICATED);
        }

        $token = $this->jwtManager->create($user);
        $response = new LoginResponse();
        $response->setAppToken($token);
        return $response;
    }

    // --- 4. Forgot Password (SENDS REAL EMAIL) ---
    public function ForgotPassword(ContextInterface $ctx, ForgotPasswordRequest $request): ForgotPasswordResponse
    {
        $requestEmail = $request->getEmail();

        $this->logger->info('[PASSWORD RESET] Request received', [
            'email' => $requestEmail,
            'timestamp' => date('Y-m-d H:i:s')
        ]);

        $repo = $this->entityManager->getRepository(User::class);
        $user = $repo->findOneBy(['email' => $requestEmail]);

        $response = new ForgotPasswordResponse();

        // Security best practice: Always return success even if email doesn't exist
        if (!$user) {
            $this->logger->warning('[PASSWORD RESET] User not found', [
                'email' => $requestEmail
            ]);
            $response->setSuccess(true);
            $response->setMessage("If an account exists with this email, a password reset link has been sent.");
            return $response;
        }

        $this->logger->info('[PASSWORD RESET] User found, generating token', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'username' => $user->getUsername()
        ]);

        // Generate secure random token (64 characters)
        $token = bin2hex(random_bytes(32));
        $user->setResetToken($token);

        $this->entityManager->flush();

        $this->logger->debug('[PASSWORD RESET] Token saved to database', [
            'user_id' => $user->getId(),
            'token_length' => strlen($token)
        ]);

        // Build reset link
        $resetLink = $this->appUrl . "/reset-password?token=" . $token;

        // Get user's name for personalization
        $userName = $user->getFirstName() ?: $user->getUsername();

        $this->logger->info('[PASSWORD RESET] Preparing email', [
            'from' => $this->fromEmail,
            'from_name' => $this->fromName,
            'to' => $user->getEmail(),
            'subject' => 'Password Reset Request',
            'reset_link' => $resetLink
        ]);

        // Create beautiful HTML email
        $email = (new Email())
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('Password Reset Request')
            ->html($this->getEmailTemplate($userName, $resetLink, $token));

        try {
            $this->logger->info('[PASSWORD RESET] Attempting to send email via SMTP', [
                'mailer_dsn' => getenv('MAILER_DSN') ? 'SET' : 'NOT SET',
                'transport' => 'Gmail SMTP'
            ]);

            // SEND REAL EMAIL VIA GMAIL - THIS HAPPENS SYNCHRONOUSLY NOW
            $this->mailer->send($email);



            $this->logger->info('[PASSWORD RESET] ✅ EMAIL SENT SUCCESSFULLY!', [
                'to' => $user->getEmail(),
                'token_preview' => substr($token, 0, 10) . '...',
                'reset_link' => $resetLink
            ]);

            $response->setSuccess(true);
            $response->setMessage("If an account exists with this email, a password reset link has been sent.");
        } catch (\Exception $e) {
            $this->logger->error('[PASSWORD RESET] ❌ EMAIL FAILED', [
                'error' => $e->getMessage(),
                'error_class' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'to' => $user->getEmail(),
                'from' => $this->fromEmail,
                'mailer_dsn' => getenv('MAILER_DSN')
            ]);

            throw new GRPCException("Failed to send email. Please try again later.", StatusCode::INTERNAL);
        }

        return $response;
    }

    // --- 5. Reset Password ---
    public function ResetPassword(ContextInterface $ctx, ResetPasswordRequest $request): LoginResponse
    {
        $repo = $this->entityManager->getRepository(User::class);
        $user = $repo->findOneBy(['resetToken' => $request->getToken()]);

        if (!$user) {
            throw new GRPCException("Invalid or expired reset token", StatusCode::INVALID_ARGUMENT);
        }

        // Update password
        $hashed = $this->passwordHasher->hashPassword($user, $request->getNewPassword());
        $user->setPassword($hashed);

        // Clear reset token
        $user->setResetToken(null);

        $this->entityManager->flush();

        // Auto-login user after password reset
        $token = $this->jwtManager->create($user);
        $response = new LoginResponse();
        $response->setAppToken($token);

        return $response;
    }

    /**
     * Generate HTML email template for password reset
     */
    private function getEmailTemplate(string $userName, string $resetLink, string $token): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            margin-bottom: 20px;
            color: #333;
        }
        .message {
            font-size: 16px;
            color: #555;
            margin-bottom: 30px;
            line-height: 1.8;
        }
        .button-container {
            text-align: center;
            margin: 35px 0;
        }
        .reset-button {
            display: inline-block;
            padding: 16px 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            font-size: 16px;
            transition: transform 0.2s;
        }
        .reset-button:hover {
            transform: translateY(-2px);
        }
        .alternative {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 14px;
            color: #666;
        }
        .alternative p {
            margin: 10px 0;
        }
        .alternative-link {
            word-break: break-all;
            color: #667eea;
            text-decoration: none;
        }
        .token-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            word-break: break-all;
            margin: 10px 0;
            border-left: 4px solid #667eea;
        }
        .footer {
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            color: #999;
            font-size: 14px;
            border-top: 1px solid #e9ecef;
        }
        .warning {
            margin-top: 25px;
            padding: 15px;
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
            font-size: 14px;
            color: #856404;
        }
        .security-info {
            margin-top: 20px;
            font-size: 13px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔐 Password Reset Request</h1>
        </div>

        <div class="content">
            <div class="greeting">
                Hello {$userName},
            </div>

            <div class="message">
                We received a request to reset the password for your account. If you made this request,
                click the button below to reset your password:
            </div>

            <div class="button-container">
                <a href="{$resetLink}" class="reset-button">Reset My Password</a>
            </div>

            <div class="alternative">
                <p><strong>Button not working?</strong> Copy and paste this link into your browser:</p>
                <a href="{$resetLink}" class="alternative-link">{$resetLink}</a>
            </div>

            <div class="warning">
                <strong>⚠️ Security Notice:</strong><br>
                This password reset link will expire after being used once. If you didn't request a
                password reset, please ignore this email or contact support if you have concerns.
            </div>

            <div class="security-info">
                <p><strong>For your security:</strong></p>
                <ul style="margin: 10px 0; padding-left: 20px;">
                    <li>This link can only be used once</li>
                    <li>Never share this link with anyone</li>
                    <li>We will never ask for your password via email</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated message from {$this->fromName}</p>
            <p>If you didn't request this password reset, please ignore this email.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}