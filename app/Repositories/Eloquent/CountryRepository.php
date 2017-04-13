<?php

namespace Coyote\Repositories\Eloquent;

use Coyote\Country;
use Coyote\Repositories\Contracts\CountryRepositoryInterface;

class CountryRepository extends Repository implements CountryRepositoryInterface
{
    /**
     * @return string
     */
    public function model()
    {
        return Country::class;
    }
}
