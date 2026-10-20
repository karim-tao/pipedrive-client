<?php

use Pipedrive\Client\Resources\User;

test('users()->me fetches the authorized user', function () {
    [$client, $http] = pipedriveClientWith([httpResponse(200, [], '{"success":true,"data":{"id":5,"name":"Karim","company_domain":"acme"}}')]);

    $user = $client->users()->me();

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->id)->toBe(5)
        ->and($user->name)->toBe('Karim')
        ->and(isset($user->company_domain))->toBeTrue()
        ->and($user->raw()->company_domain)->toBe('acme')
        ->and((string) $http->requests[0]->getUri())->toBe('https://acme.pipedrive.com/api/v1/users/me');
});
