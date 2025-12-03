<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Psr\Log\LoggerInterface;

/**
 * Redirect-based Google OAuth flow for WebView compatibility
 * Works in Facebook Messenger, Instagram, and other in-app browsers
 */
class GoogleOAuthRedirectController extends AbstractController
{
    private string $clientId;
    private string $clientSecret;
    private string $redirectUri;

    public function __construct()
    {
        $this->clientId = $_ENV['GOOGLE_CLIENT_ID'] ?? '';
        $this->clientSecret = $_ENV['GOOGLE_CLIENT_SECRET'] ?? '';
        $this->redirectUri = $_ENV['GOOGLE_OAUTH_REDIRECT_URI'] ?? '';
    }

    /**
     * Step 1: Redirect user to Google OAuth consent screen
     */
    #[Route('/auth/google', name: 'google_oauth_start', methods: ['GET'])]
    public function redirectToGoogle(): Response
    {
        // Build Google OAuth URL with proper scopes
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'access_type' => 'online',
            'prompt' => 'select_account', // Always show account picker
        ];

        $googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

        return $this->redirect($googleAuthUrl);
    }

    /**
     * Step 2: Handle callback from Google with authorization code
     */
    #[Route('/auth/google/callback', name: 'google_oauth_callback', methods: ['GET'])]
    public function handleGoogleCallback(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em,
        TokenStorageInterface $tokenStorage,
        LoggerInterface $logger
    ): Response
    {
        // Get authorization code from query params
        $code = $request->query->get('code');
        $error = $request->query->get('error');

        // Handle user cancellation or errors
        if ($error || !$code) {
            $this->addFlash('error', 'Google login was cancelled or failed. Please try again.');
            return $this->redirectToRoute('app_login_page');
        }

        try {
            // Exchange authorization code for access token
            $tokenData = $this->exchangeCodeForToken($code);

            if (!$tokenData || !isset($tokenData['id_token'])) {
                throw new \Exception('Failed to obtain access token from Google');
            }

            // Verify and decode the ID token
            $userInfo = $this->verifyIdToken($tokenData['id_token']);

            if (!$userInfo || !isset($userInfo['email'])) {
                throw new \Exception('Failed to verify Google ID token');
            }

            // Find or create user
            $user = $userRepository->findOneBy(['email' => $userInfo['email']]);

            if (!$user) {
                // Create new user
                $user = new User();
                $user->setEmail($userInfo['email']);
                $user->setFirstName($userInfo['given_name'] ?? '');
                $user->setLastName($userInfo['family_name'] ?? '');
                $user->setRoles(['ROLE_USER']);
                $user->setIsVerified(true); // Google-verified email

                // Set a random password (Google OAuth users don't use password login)
                $user->setPassword(bin2hex(random_bytes(32)));

                $em->persist($user);
                $em->flush();

                $logger->info('New user created via Google OAuth', [
                    'email' => $user->getEmail(),
                    'id' => $user->getId()
                ]);
            }

            // Log the user in
            $session = $request->getSession();
            if (!$session->isStarted()) {
                $session->start();
            }

            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
            $tokenStorage->setToken($token);
            $session->set('_security_main', serialize($token));

            $this->addFlash('success', 'Successfully logged in with Google!');

            // Redirect based on role
            if (in_array('ROLE_ADMIN', $user->getRoles())) {
                return $this->redirectToRoute('admin_dashboard');
            }

            return $this->redirectToRoute('booking_index');

        } catch (\Exception $e) {
            $logger->error('Google OAuth error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->addFlash('error', 'Google login failed: ' . $e->getMessage());
            return $this->redirectToRoute('app_login_page');
        }
    }

    /**
     * Exchange authorization code for access token
     */
    private function exchangeCodeForToken(string $code): ?array
    {
        $tokenUrl = 'https://oauth2.googleapis.com/token';

        $postData = [
            'code' => $code,
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'redirect_uri' => $this->redirectUri,
            'grant_type' => 'authorization_code',
        ];

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$response) {
            return null;
        }

        return json_decode($response, true);
    }

    /**
     * Verify Google ID token and extract user info
     */
    private function verifyIdToken(string $idToken): ?array
    {
        try {
            $client = new \Google\Client(['client_id' => $this->clientId]);
            $payload = $client->verifyIdToken($idToken);

            return $payload ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
