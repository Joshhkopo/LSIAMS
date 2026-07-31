<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Helpers\Audit;
use App\Helpers\Auth;
use App\Helpers\Upload;
use App\Helpers\Validator;
use App\Models\LoginHistory;
use App\Models\Teacher;
use App\Models\User;

final class ProfileController extends Controller
{
    public function index(Request $request): never
    {
        $teacher = Auth::isAdmin() ? null : (new Teacher())->findByUserId((int) Auth::id());
        $this->view('profile/index', [
            'pageTitle'    => 'My Profile',
            'teacher'      => $teacher,
            'loginHistory' => (new LoginHistory())->forUser((int) Auth::id()),
        ]);
    }

    /** Users may edit their own email/phone/photo — nothing else. */
    public function update(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'email' => 'required|email|max:150',
            'phone' => 'nullable|phone',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }

        $users = new User();
        $userId = (int) Auth::id();
        if ($users->emailExists((string) $request->input('email'), $userId)) {
            $this->fail('Email already in use by another account.', 422);
        }

        $photo = null;
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            [$ok, $path] = Upload::store($_FILES['photo'], 'profiles');
            if (!$ok) {
                $this->fail($path, 422);
            }
            $photo = $path;
        }

        $userData = ['email' => (string) $request->input('email')];
        if ($photo !== null) {
            $userData['photo'] = $photo;
        }
        $users->update($userId, $userData);

        $teacher = (new Teacher())->findByUserId($userId);
        if ($teacher !== null) {
            $teacherData = ['email' => $userData['email'], 'phone' => $request->input('phone')];
            if ($photo !== null) {
                $teacherData['photo'] = $photo;
            }
            (new Teacher())->update((int) $teacher['id'], $teacherData);
        }

        // Refresh session copy.
        $sessionUser = Auth::user();
        $sessionUser['email'] = $userData['email'];
        if ($photo !== null) {
            $sessionUser['photo'] = $photo;
        }
        Session::set('user', $sessionUser);

        Audit::log('profile_updated', 'profile', $userId);
        $this->ok('Profile updated successfully.');
    }

    public function changePassword(Request $request): never
    {
        $v = Validator::make($request->all(), [
            'current_password' => 'required',
            'password'         => 'required|password',
            'password_confirm' => 'required',
        ]);
        if ($v->fails()) {
            $this->fail($v->firstError(), 422, ['errors' => $v->errors()]);
        }
        if ($request->input('password') !== $request->input('password_confirm')) {
            $this->fail('New passwords do not match.', 422);
        }

        $stmt = Database::pdo()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([Auth::id()]);
        $hash = $stmt->fetchColumn();
        if ($hash === false || !password_verify((string) $request->input('current_password'), (string) $hash)) {
            $this->fail('Current password is incorrect.', 403);
        }

        (new User())->setPassword((int) Auth::id(), (string) $request->input('password'));
        Session::regenerate();
        Audit::log('password_changed', 'profile', Auth::id());
        $this->ok('Password changed successfully.');
    }
}
