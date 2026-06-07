<?php
declare(strict_types=1);

namespace Quenza\Core\Controller;

use Quenza\Core\Auth\AuthManager;
use Quenza\Core\Auth\RegistrationService;
use Quenza\Core\Http\Request;
use Quenza\Core\Http\Response;
use Quenza\Core\Session\SessionManager;
use Quenza\Core\View\TwigRenderer;

final class AuthController
{
    public function __construct(
        private readonly TwigRenderer $view,
        private readonly AuthManager $auth,
        private readonly RegistrationService $registration,
        private readonly SessionManager $session,
    ) {
    }

    public function showLogin(Request $request): Response
    {
        return Response::html($this->view->render('auth/login.twig', [
            'page_title' => trans('auth.login'),
        ]));
    }

    public function login(Request $request): Response
    {
        $email = (string) $request->input('email', '');
        $password = (string) $request->input('password', '');
        $result = $this->auth->attempt($email, $password, $request->ipAddress());

        if (!$result->successful) {
            $this->session->flashErrors($result->errors !== [] ? $result->errors : ['email' => $result->message]);
            $this->session->flashInput(['email' => $email]);
            $this->session->flash('status', $result->message);

            return Response::redirect('/login');
        }

        $this->session->flash('status', $result->message);

        return Response::redirect('/admin');
    }

    public function showRegister(Request $request): Response
    {
        return Response::html($this->view->render('auth/register.twig', [
            'page_title' => trans('auth.register'),
        ]));
    }

    public function register(Request $request): Response
    {
        $payload = $request->only(['full_name', 'email']);
        $result = $this->registration->register(
            (string) $request->input('full_name', ''),
            (string) $request->input('email', ''),
            (string) $request->input('password', ''),
            (string) $request->input('password_confirmation', ''),
            $request->ipAddress(),
        );

        if (!$result->successful) {
            $this->session->flashErrors($result->errors !== [] ? $result->errors : ['email' => $result->message]);
            $this->session->flashInput($payload);
            $this->session->flash('status', $result->message);

            return Response::redirect('/register');
        }

        $this->session->flash('status', $result->message);

        return Response::redirect('/admin');
    }

    public function logout(Request $request): Response
    {
        $this->auth->logout();
        $this->session->flash('status', trans('auth.logout_success'));

        return Response::redirect('/login');
    }
}
