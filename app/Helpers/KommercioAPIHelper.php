<?php

namespace Kommercio\Helpers;

class KommercioAPIHelper
{
    public function getAPIUrl()
    {
        $api_path = config('app.env') == 'master_local'?'http://localhost/kommercio_master_address/public':'https://kommercio.webpresso.co.id';

        return $api_path;
    }

    public function getAPIToken()
    {
        return config('project.kommercio_api_token', config('kommercio.kommercio_api_token'));
    }
}
