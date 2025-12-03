<?php

namespace App\Controller;

use App\Service\ReCaptchaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Example Controller demonstrating reCAPTCHA v3 integration
 *
 * This controller shows how to verify reCAPTCHA tokens on form submissions
 */
class ExampleReCaptchaController extends AbstractController
{
    private ReCaptchaService $recaptchaService;

    public function __construct(ReCaptchaService $recaptchaService)
    {
        $this->recaptchaService = $recaptchaService;
    }

    /**
     * Example: Display a form with reCAPTCHA v3
     */
    #[Route('/recaptcha/example', name: 'recaptcha_example')]
    public function showForm(): Response
    {
        return $this->render('recaptcha/example_form.html.twig', [
            'recaptcha_site_key' => $this->recaptchaService->getSiteKey(),
            'recaptcha_enabled' => $this->recaptchaService->isEnabled(),
        ]);
    }

    /**
     * Example: Handle form submission with reCAPTCHA verification
     */
    #[Route('/recaptcha/submit', name: 'recaptcha_submit', methods: ['POST'])]
    public function handleFormSubmit(Request $request): JsonResponse
    {
        // Get the reCAPTCHA token from the request
        $recaptchaToken = $request->request->get('recaptcha_token');

        if (!$recaptchaToken) {
            return new JsonResponse([
                'success' => false,
                'error' => 'reCAPTCHA token is missing'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify the reCAPTCHA token
        $verification = $this->recaptchaService->verify(
            token: $recaptchaToken,
            action: 'contact_form', // Optional: specify the action name
            remoteIp: $request->getClientIp(), // Optional: include user's IP
            minScore: 0.5 // Optional: minimum acceptable score (default is 0.5)
        );

        // Check if verification was successful
        if (!$verification['success']) {
            return new JsonResponse([
                'success' => false,
                'error' => 'reCAPTCHA verification failed',
                'details' => $verification['message'] ?? 'Unknown error',
            ], Response::HTTP_BAD_REQUEST);
        }

        // reCAPTCHA verification passed - process the form
        // ... your form processing logic here ...

        return new JsonResponse([
            'success' => true,
            'message' => 'Form submitted successfully',
            'recaptcha_score' => $verification['score'] ?? null,
        ]);
    }

    /**
     * Example: Contact form with reCAPTCHA protection
     */
    #[Route('/api/contact', name: 'api_contact', methods: ['POST'])]
    public function contactForm(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate reCAPTCHA first
        $recaptchaToken = $data['recaptcha_token'] ?? null;

        if (!$recaptchaToken) {
            return new JsonResponse([
                'success' => false,
                'message' => 'reCAPTCHA verification is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify reCAPTCHA with specific action
        $verification = $this->recaptchaService->verify(
            token: $recaptchaToken,
            action: 'contact_submit',
            remoteIp: $request->getClientIp()
        );

        if (!$verification['success']) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Security verification failed. Please try again.',
                'error_code' => $verification['error'] ?? 'unknown'
            ], Response::HTTP_FORBIDDEN);
        }

        // Low score indicates potential bot
        if (($verification['score'] ?? 0) < 0.5) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Your submission appears suspicious. Please try again later.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Process the contact form
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $message = $data['message'] ?? '';

        // Validate input
        if (empty($name) || empty($email) || empty($message)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'All fields are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // TODO: Send email, save to database, etc.

        return new JsonResponse([
            'success' => true,
            'message' => 'Thank you for contacting us! We will respond shortly.',
            'recaptcha_score' => $verification['score'],
        ]);
    }

    /**
     * Example: Login form with reCAPTCHA protection
     * This can be integrated into your existing LoginController
     */
    #[Route('/api/login-with-recaptcha', name: 'api_login_recaptcha', methods: ['POST'])]
    public function loginWithRecaptcha(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Verify reCAPTCHA
        $recaptchaToken = $data['recaptcha_token'] ?? null;

        if ($this->recaptchaService->isEnabled() && !$recaptchaToken) {
            return new JsonResponse([
                'success' => false,
                'message' => 'reCAPTCHA verification required'
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($this->recaptchaService->isEnabled()) {
            $verification = $this->recaptchaService->verify(
                token: $recaptchaToken,
                action: 'login',
                remoteIp: $request->getClientIp(),
                minScore: 0.4 // Lower threshold for login (adjust as needed)
            );

            if (!$verification['success']) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Security verification failed'
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Proceed with login logic
        $username = $data['username'] ?? '';
        $password = $data['password'] ?? '';

        // TODO: Implement your actual login logic here

        return new JsonResponse([
            'success' => true,
            'message' => 'Login successful',
        ]);
    }
}
