<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Helpers\Auth;
use App\Helpers\Audit;
use App\Helpers\Validator;
use App\Models\User;

final class AuthController extends Controller
{
    public function home(Request $request): never
    {
        $this->redirect(Auth::check() ? '/dashboard' : '/login');
    }

    public function showLogin(Request $request): never
    {
        if (Auth::check()) {
            $this->redirect('/dashboard');
        }
        $this->view('auth/login', [], 'layouts/auth');
    }

    public function login(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'username' => 'required|max:150',
            'password' => 'required|max:255',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            $this->redirect('/login');
        }

        [$ok, $message] = Auth::attempt(
            (string) $request->input('username'),
            (string) $request->input('password'),
            $request,
        );
        if (!$ok) {
            Session::flash('error', $message);
            $this->redirect('/login');
        }

        if ((int) (Auth::user()['must_change_password'] ?? 0) === 1) {
            $this->redirect('/force-password');
        }
        $this->redirect('/dashboard');
    }

    public function logout(Request $request): never
    {
        Auth::logout($request);
        $this->redirect('/login');
    }

    public function showForcePassword(Request $request): never
    {
        $this->view('auth/force_password', [], 'layouts/auth');
    }

    /** Mandatory password change for seeded/reset accounts. */
    public function forcePassword(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'password'         => 'required|password',
            'password_confirm' => 'required',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            $this->redirect('/force-password');
        }
        if ($request->input('password') !== $request->input('password_confirm')) {
            Session::flash('error', 'Passwords do not match.');
            $this->redirect('/force-password');
        }

        $users = new User();
        $users->setPassword((int) Auth::id(), (string) $request->input('password'));
        Audit::log('password_changed', 'auth', Auth::id());

        $user = Auth::user();
        $user['must_change_password'] = 0;
        Session::set('user', $user);
        Session::flash('success', 'Password updated successfully.');
        $this->redirect('/dashboard');
    }
}
