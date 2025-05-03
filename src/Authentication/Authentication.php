<?php

namespace Rcalicdan\Ci4Larabridge\Authentication;

use CodeIgniter\Session\Session;
use Rcalicdan\Ci4Larabridge\Blade\BladeService;
use Rcalicdan\Ci4Larabridge\Models\User as BridgeUser;

class Authentication
{
    /**
     * Session service instance
     *
     * @var Session
     */
    protected $session;

    /**
     * Currently authenticated user
     *
     * @var \Illuminate\Database\Eloquent\Model|null
     */
    protected $user = null;

    /**
     * The user model class to use
     *
     * @var class-string
     */
    protected $userModel;

    public function __construct()
    {
        // pick App\Models\User if it exists, otherwise default to the bridge user
        $this->userModel = class_exists(\App\Models\User::class)
            ? \App\Models\User::class
            : BridgeUser::class;

        $this->session = \Config\Services::session();
    }

    /**
     * Get the currently authenticated user
     */
    public function user(): ?\Illuminate\Database\Eloquent\Model
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $userId = $this->session->get('auth_user_id');
        if (! $userId) {
            return null;
        }

        // use whichever User class we resolved in __construct
        $this->user = $this->userModel::find($userId);

        return $this->user;
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return ! $this->check();
    }

    public function attempt(array $credentials): bool
    {
        $model = $this->userModel;
        $user  = $model::where('email', $credentials['email'])->first();

        if ($user && password_verify($credentials['password'], $user->password)) {
            return $this->login($user);
        }

        return false;
    }

    public function login($user): bool
    {
        $this->session->set('auth_user_id', $user->id);
        $this->user = $user;
        $this->session->regenerate(true);

        return true;
    }

    public function logout(): bool
    {
        $this->user = null;
        $this->session->remove('auth_user_id');
        $this->session->regenerate(true);
        
        return true;
    }
}
