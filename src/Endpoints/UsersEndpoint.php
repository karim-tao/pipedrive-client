<?php

namespace Pipedrive\Client\Endpoints;

use Pipedrive\Client\Resources\User;

final class UsersEndpoint extends BaseEndpoint
{
    /**
     * @link https://developers.pipedrive.com/docs/api/v1/Users#getCurrentUser
     */
    public function me(): User
    {
        return new User($this->request('GET', '/api/v1/users/me')->data);
    }
}
