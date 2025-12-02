<?php

namespace App\Grpc;

use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use App\Grpc\Auth\V1\GoogleLoginRequest;
use App\Grpc\Auth\V1\LoginResponse;
use App\Grpc\Auth\V1\RegisterRequest;
use App\Grpc\Auth\V1\LoginRequest;
use App\Grpc\Auth\V1\ForgotPasswordRequest;
use App\Grpc\Auth\V1\ForgotPasswordResponse;
use App\Grpc\Auth\V1\ResetPasswordRequest;

interface AuthServiceInterface extends ServiceInterface
{
    const NAME = "auth.v1.AuthService";

    public function GoogleLogin(ContextInterface $ctx, GoogleLoginRequest $request): LoginResponse;
    public function Register(ContextInterface $ctx, RegisterRequest $request): LoginResponse;
    public function Login(ContextInterface $ctx, LoginRequest $request): LoginResponse;
    public function ForgotPassword(ContextInterface $ctx, ForgotPasswordRequest $request): ForgotPasswordResponse;
    public function ResetPassword(ContextInterface $ctx, ResetPasswordRequest $request): LoginResponse;
}