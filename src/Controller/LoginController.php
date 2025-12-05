<?php

namespace App\Controller;

use App\Entity\User;
use App\Grpc\AuthService;
use App\Grpc\Auth\V1\RegisterRequest;
use App\Grpc\Auth\V1\LoginRequest;
use App\Grpc\Auth\V1\ForgotPasswordRequest;
use App\Grpc\Auth\V1\ResetPasswordRequest;
use App\Grpc\Auth\V1\GoogleLoginRequest;
use App\Repository\UserRepository;
use App\Service\ReCaptchaService;
use Doctrine\ORM\EntityManagerInterface;
use Spiral\RoadRunner\GRPC\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class LoginController extends AbstractController
{
    private ReCaptchaService $recaptchaService;

    public function __construct(ReCaptchaService $recaptchaService)
    {
        $this->recaptchaService = $recaptchaService;
    }
    // ==========================================
    //               HTML PAGES
    // ==========================================

    #[Route('/login', name: 'app_login_page')]
    public function index(AuthenticationUtils $authenticationUtils): Response
    {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'google_client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '',
            'recaptcha_site_key' => $this->recaptchaService->getSiteKey(),
            'recaptcha_enabled' => $this->recaptchaService->isEnabled(),
        ]);
    }

    #[Route('/register', name: 'app_register_page')]
    public function registerPage(): Response
    {
        return $this->render('registration/register.html.twig', [
            'recaptcha_site_key' => $this->recaptchaService->getSiteKey(),
            'recaptcha_enabled' => $this->recaptchaService->isEnabled(),
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password_page')]
    public function forgotPasswordPage(): Response
    {
        return $this->render('login/forgot_password.html.twig');
    }

    #[Route('/reset-password', name: 'app_reset_password_page')]
    public function resetPasswordPage(Request $request): Response
    {
        $token = $request->query->get('token');

        if (!$token) {
            return $this->redirectToRoute('app_login_page');
        }

        return $this->render('login/reset_password.html.twig', [
            'token' => $token
        ]);
    }

    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        // Symfony handles this automatically
        throw new \Exception('This should never be reached!');
    }

    // ==========================================
    //             API ENDPOINTS (JSON)
    // ==========================================

    #[Route('/api/register', methods: ['POST'])]
    public function registerApi(
        Request               $request,
        AuthService           $authService,
        UserRepository        $userRepository,
        TokenStorageInterface $tokenStorage
    ): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!$data) throw new \Exception("No JSON data received.");

            // Verify reCAPTCHA if enabled
            if ($this->recaptchaService->isEnabled()) {
                $recaptchaToken = $data['recaptcha_token'] ?? null;

                if (!$recaptchaToken) {
                    return $this->json([
                        'error' => 'Security verification is required. Please try again.'
                    ], Response::HTTP_FORBIDDEN);
                }

                $verification = $this->recaptchaService->verify(
                    token: $recaptchaToken,
                    action: 'register',
                    remoteIp: $request->getClientIp(),
                    minScore: 0.5
                );

                if (!$verification['success']) {
                    return $this->json([
                        'error' => 'Security verification failed. Please try again or contact support if the problem persists.',
                        'details' => $verification['message'] ?? 'Verification failed'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            $grpcReq = new RegisterRequest();
            $grpcReq->setEmail($data['email'] ?? '');
            $grpcReq->setPassword($data['password'] ?? '');
            $grpcReq->setUsername($data['username'] ?? '');
            $grpcReq->setFirstName($data['first_name'] ?? '');
            $grpcReq->setLastName($data['last_name'] ?? '');

            $response = $authService->Register(new Context([]), $grpcReq);

            // Log the user into the session after registration
            $user = $userRepository->findOneBy(['email' => $data['email']]);
            if ($user) {
                // Manually log in the user by setting the security token
                $session = $request->getSession();
                $session->start();

                // Create authentication token
                $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

                // Store in session with proper key
                $session->set('_security_main', serialize($token));

                // Regenerate session ID for security
                $session->migrate(true);
            }

            return $this->json([
                'status' => 'success',
                'app_token' => $response->getAppToken(),
                'redirect_url' => $this->generateUrl('booking_index')
            ]);

        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/login', methods: ['POST'])]
    public function loginApi(
        Request               $request,
        AuthService           $authService,
        UserRepository        $userRepository,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Verify reCAPTCHA if enabled
            if ($this->recaptchaService->isEnabled()) {
                $recaptchaToken = $data['recaptcha_token'] ?? null;

                if (!$recaptchaToken) {
                    return $this->json([
                        'error' => 'Security verification is required. Please refresh the page and try again.'
                    ], Response::HTTP_FORBIDDEN);
                }

                $verification = $this->recaptchaService->verify(
                    token: $recaptchaToken,
                    action: 'login',
                    remoteIp: $request->getClientIp(),
                    minScore: 0.4  // Lower threshold for login
                );

                if (!$verification['success']) {
                    return $this->json([
                        'error' => 'Suspicious activity detected. Please try again or contact support.',
                        'details' => $verification['message'] ?? 'Verification failed'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            $email = $data['email'] ?? '';
            $password = $data['password'] ?? '';

            // Find user
            $user = $userRepository->findOneBy(['email' => $email]);

            if (!$user) {
                return $this->json(['error' => 'Invalid credentials'], 401);
            }

            // Verify password
            if (!$passwordHasher->isPasswordValid($user, $password)) {
                return $this->json(['error' => 'Invalid credentials'], 401);
            }

            // Call gRPC service for token generation
            $grpcReq = new LoginRequest();
            $grpcReq->setEmail($email);
            $grpcReq->setPassword($password);
            $response = $authService->Login(new Context([]), $grpcReq);

            // Manually log in the user by setting the security token
            $session = $request->getSession();
            $session->start();

            // Create authentication token
            $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

            // Store in session with proper key
            $session->set('_security_main', serialize($token));

            // Regenerate session ID for security
            $session->migrate(true);

            return $this->json([
                'status' => 'success',
                'app_token' => $response->getAppToken(),
                'redirect_url' => $this->generateUrl('booking_index')
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 401);
        }
    }

    #[Route('/api/forgot-password', methods: ['POST'])]
    public function forgotPasswordApi(Request $request, AuthService $authService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $grpcReq = new ForgotPasswordRequest();
            $grpcReq->setEmail($data['email'] ?? '');

            $response = $authService->ForgotPassword(new Context([]), $grpcReq);

            return $this->json([
                'status' => 'success',
                'message' => $response->getMessage()
            ]);

        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/api/reset-password', methods: ['POST'])]
    public function resetPasswordApi(Request $request, AuthService $authService): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $grpcReq = new ResetPasswordRequest();
            $grpcReq->setToken($data['token'] ?? '');
            $grpcReq->setNewPassword($data['password'] ?? '');

            $response = $authService->ResetPassword(new Context([]), $grpcReq);

            return $this->json([
                'status' => 'success',
                'app_token' => $response->getAppToken()
            ]);

        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }

    #[Route('/api/google-login', methods: ['POST'])]
    public function googleLoginApi(
        Request $request,
        AuthService $authService,
        UserRepository $userRepository
    ): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $tokenFromBrowser = $data['token'] ?? null;

            if (!$tokenFromBrowser) {
                return $this->json(['error' => 'No token provided'], 400);
            }

            // Verify reCAPTCHA if enabled
            if ($this->recaptchaService->isEnabled()) {
                $recaptchaToken = $data['recaptcha_token'] ?? null;

                if (!$recaptchaToken) {
                    return $this->json([
                        'error' => 'Security verification is required. Please try again.'
                    ], Response::HTTP_FORBIDDEN);
                }

                $verification = $this->recaptchaService->verify(
                    token: $recaptchaToken,
                    action: 'google_login',
                    remoteIp: $request->getClientIp(),
                    minScore: 0.4  // Lower threshold for social login
                );

                if (!$verification['success']) {
                    return $this->json([
                        'error' => 'Security verification failed. Please try again.',
                        'details' => $verification['message'] ?? 'Verification failed'
                    ], Response::HTTP_FORBIDDEN);
                }
            }

            $grpcReq = new GoogleLoginRequest();
            $grpcReq->setIdToken($tokenFromBrowser);

            $response = $authService->GoogleLogin(new Context([]), $grpcReq);

            // Extract email from Google token to find user
            $client = new \Google\Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '']);
            $payload = $client->verifyIdToken($tokenFromBrowser);

            if ($payload && isset($payload['email'])) {
                $user = $userRepository->findOneBy(['email' => $payload['email']]);

                if ($user) {
                    // Manually log in the user by setting the security token
                    $session = $request->getSession();
                    $session->start();

                    // Create authentication token
                    $token = new UsernamePasswordToken($user, 'main', $user->getRoles());

                    // Store in session with proper key
                    $session->set('_security_main', serialize($token));

                    // Regenerate session ID for security
                    $session->migrate(true);
                }
            }

            return $this->json([
                'status' => 'success',
                'app_token' => $response->getAppToken(),
                'redirect_url' => $this->generateUrl('booking_index')
            ]);

        } catch (\Throwable $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}