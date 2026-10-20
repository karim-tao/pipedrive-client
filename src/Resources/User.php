<?php

namespace Pipedrive\Client\Resources;

/**
 * @property-read int|null $id
 * @property-read string|null $name
 * @property-read string|null $email
 * @property-read int|null $company_id
 * @property-read string|null $company_name
 * @property-read string|null $company_domain
 */
final readonly class User extends RawObjectResource {}
