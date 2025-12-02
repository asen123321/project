<?php

namespace App\Controller;

use App\Grpc\AuthService; // Your gRPC service
use App\Grpc\Auth\V1\GoogleLoginRequest;
use App\Repository\UserRepository;
use Spiral\RoadRunner\GRPC\Context;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class GoogleAuthController extends AbstractController
{
    #[Route('/api/google-login', name: 'api_google_login', methods: ['POST'])]
    public function login(
        Request $request,
        AuthService $authService,
        UserRepository $userRepository,
        TokenStorageInterface $tokenStorage
    ): JsonResponse
    {
        // 1. Get the token sent from JavaScript
        $data = json_decode($request->getContent(), true);
        $tokenFromBrowser = $data['token'] ?? null;

        if (!$tokenFromBrowser) {
            return $this->json(['error' => 'No token provided'], 400);
        }

        // 2. Wrap it in a gRPC Request Object (so we can reuse your existing code!)
        $grpcRequest = new GoogleLoginRequest();
        $grpcRequest->setIdToken($tokenFromBrowser);

        // 3. Create a dummy Context (since we aren't coming via RoadRunner network)
        $ctx = new Context([]);

        try {
            // 4. CALL YOUR SERVICE! This triggers the DB save logic.
            $response = $authService->GoogleLogin($ctx, $grpcRequest);

            // Decode the JWT to get the email (or we could modify AuthService to return user info)
            // For now, let's extract the email from the Google token
            $client = new \Google\Client(['client_id' => $_ENV['GOOGLE_CLIENT_ID'] ?? '']);
            $payload = $client->verifyIdToken($tokenFromBrowser);

            if ($payload && isset($payload['email'])) {
                // Log the user into the session after Google login
                $user = $userRepository->findOneBy(['email' => $payload['email']]);
                if ($user) {
                    // Start session if not already started
                    $session = $request->getSession();
                    if (!$session->isStarted()) {
                        $session->start();
                    }

                    $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
                    $tokenStorage->setToken($token);
                    $session->set('_security_main', serialize($token));
                }
            }

            // 5. Return the JWT to the frontend with booking calendar redirect
            return $this->json([
                'status' => 'success',
                'app_token' => $response->getAppToken(),
                'redirect_url' => $this->generateUrl('booking_index')
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}