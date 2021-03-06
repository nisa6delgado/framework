<?php

/**
 * Authentication with Google, require google/apiclient.
 */
class Google
{
    /**
     * Instance of the Google_Client class.
     *
     * $instance object
     */
    public $instance;

    /**
     * Redirect uri
     *
     * $redirect string
     */
    public $redirect = 'http://localhost:8080/login/google';

    /**
     * Initialize the class to use from a global function.
     *
     * @return Google
     */
    public static function init()
    {
        $class = new static;

        $google_client = new Google_Client();

        $google_client->setClientId(config('client_id'));
        $google_client->setClientSecret(config('client_secret'));

        $google_client->addScope('email');
        $google_client->addScope('profile');

        $class->instance = $google_client;

        return $class;
    }

    /**
     * Login with Google account.
     *
     * @return redirect
     */
    public function login()
    {
        $client = $this->instance;

        $client->setRedirectUri($this->redirect);

        $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
        $client->setAccessToken($token['access_token']);

        $google_oauth = new Google_Service_Oauth2($client);
        $google_account_info = $google_oauth->userinfo->get();
        $email =  $google_account_info->email;
        $name =  $google_account_info->name;

        $user = App\Models\User::firstOrCreate(
            [
                'email' => $email,
                'oauth' => 'Google',
            ],
            ['name' => $name]
        );

        $_SESSION['id']          = $user->id;
        $_SESSION['name']        = $user->name;
        $_SESSION['email']       = $user->email;
        $_SESSION['role']        = $user->role;
        $_SESSION['permissions'] = $user->permissions;

        return redirect('/');
    }

    /**
     * Create URL to log in to Google.
     *
     * @return string
     */
    public function url()
    {
    	$this->instance->setRedirectUri($this->redirect);
    	echo $this->instance->createAuthUrl();
    }
}
