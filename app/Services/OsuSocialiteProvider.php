<?php

namespace App\Services;

use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\ProviderInterface;
use Laravel\Socialite\Two\User;

class OsuSocialiteProvider extends AbstractProvider implements ProviderInterface
{
    /**
     * The scopes being requested.
     *
     * @var array<int, string>
     */
    protected $scopes = ['identify'];

    /**
     * The separating character for the requested scopes.
     *
     * @var string
     */
    protected $scopeSeparator = ' ';

    /**
     * Get the authentication URL for the provider.
     *
     * @param  string  $state
     * @return string
     */
    protected function getAuthUrl($state)
    {
        return $this->buildAuthUrlFromBase('https://osu.ppy.sh/oauth/authorize', $state);
    }

    /**
     * Get the token URL for the provider.
     *
     * @return string
     */
    protected function getTokenUrl()
    {
        return 'https://osu.ppy.sh/oauth/token';
    }

    /**
     * Get the raw user for the given access token.
     *
     * @param  string  $token
     * @return array<string, mixed>
     */
    protected function getUserByToken($token)
    {
        $response = $this->getHttpClient()->get('https://osu.ppy.sh/api/v2/me', [
            'headers' => [
                'Accept' => 'application/json',
                'Authorization' => 'Bearer '.$token,
            ],
        ]);

        return json_decode($response->getBody(), true);
    }

    /**
     * Map the raw user array to a Socialite User instance.
     *
     * @param  array<string, mixed>  $user
     * @return User
     */
    protected function mapUserToObject(array $user)
    {
        return (new User)->setRaw($user)->map([
            'id' => $user['id'],
            'nickname' => $user['username'],
            'name' => $user['username'],
            'email' => null, // osu! doesn't provide email in identify scope
            'avatar' => $user['avatar_url'],
        ]);
    }

    /**
     * Get the POST fields for the token request.
     *
     * @param  string  $code
     * @return array<string, mixed>
     */
    protected function getTokenFields($code)
    {
        return array_merge(parent::getTokenFields($code), [
            'grant_type' => 'authorization_code',
        ]);
    }
}
